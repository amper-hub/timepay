<?php

/* Copy-only MyISAM -> InnoDB rehearsal utility. Never drops a schema. */
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$allowed = ['timepay_repair_copy_20261102', 'timepay_payroll_test_20261102', 'timepay_payroll_test_copy_20261102'];
$mode = $argv[1] ?? '';
$target = $argv[2] ?? '';
if (!in_array($mode, ['schema', 'data', 'archive', 'baseline', 'migrate', 'verify'], true) || !in_array($target, $allowed, true)) {
    fwrite(STDERR, "Usage: php database/repair/prepare_innodb_copy.php schema|data|archive|baseline|migrate|verify ".implode('|', $allowed)."\n");
    exit(2);
}
$config = config('database.connections.mysql');
$source = $config['database'] ?? '';
if (app()->environment() !== 'local' || config('database.default') !== 'mysql'
    || $source !== 'timepay_db' || ($config['host'] ?? '') !== '127.0.0.1' || $target === $source) {
    throw new RuntimeException('Safety guard refused: expected local MySQL source timepay_db at 127.0.0.1.');
}
$pdo = DB::connection('mysql')->getPdo();
$quote = static fn (string $identifier): string => '`'.str_replace('`', '``', $identifier).'`';
$exists = (bool) DB::selectOne('SELECT 1 AS present FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$target]);

if ($mode === 'schema' || $mode === 'data') {
    if ($exists) throw new RuntimeException("Refusing to overwrite existing rehearsal schema {$target}.");
    $pdo->exec('CREATE DATABASE '.$quote($target).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$source]);
    foreach ($tables as $row) {
        $table = $row->TABLE_NAME;
        $pdo->exec('CREATE TABLE '.$quote($target).'.'.$quote($table).' LIKE '.$quote($source).'.'.$quote($table));
        if ($mode === 'data') $pdo->exec('INSERT INTO '.$quote($target).'.'.$quote($table).' SELECT * FROM '.$quote($source).'.'.$quote($table));
        $sourceCount = (int) DB::selectOne('SELECT COUNT(*) AS c FROM '.$quote($source).'.'.$quote($table))->c;
        $targetCount = (int) DB::selectOne('SELECT COUNT(*) AS c FROM '.$quote($target).'.'.$quote($table))->c;
        $expectedCount = $mode === 'data' ? $sourceCount : 0;
        if ($expectedCount !== $targetCount) throw new RuntimeException("Row-count mismatch for {$table}: expected={$expectedCount}, copy={$targetCount}");
        printf("copied %-40s %d rows\n", $table, $targetCount);
    }
    printf("Created %s copy (%s only); source was read only.\n", $target, $mode);
    exit(0);
}
if (!$exists) throw new RuntimeException("Rehearsal schema {$target} does not exist; create it first.");

if ($mode === 'baseline') {
    $existing = (int) DB::selectOne('SELECT COUNT(*) AS c FROM '.$quote($target).'.`payroll_statutory_rules`')->c;
    if ($existing !== 0) throw new RuntimeException('Refusing to seed an already populated statutory-rule table.');
    $pdo->exec('INSERT INTO '.$quote($target).'.`payroll_statutory_rules` SELECT * FROM '.$quote($source).'.`payroll_statutory_rules`');
    $copied = (int) DB::selectOne('SELECT COUNT(*) AS c FROM '.$quote($target).'.`payroll_statutory_rules`')->c;
    printf("Copied %d baseline statutory rules into the isolated test schema.\n", $copied);
    exit(0);
}

if ($mode === 'archive') {
    $orphans = [
        ['payroll_attendance_allocations','payroll_entry_id','payroll_entries','id'],
        ['payroll_entry_attendance_logs','payroll_entry_id','payroll_entries','id'],
        ['payroll_entry_lines','payroll_entry_id','payroll_entries','id'],
    ];
    foreach ($orphans as [$child,$column,$parent,$parentColumn]) {
        $archive = 'repair_archive_'.$child;
        $archiveExists = (bool) DB::selectOne('SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [$target,$archive]);
        if ($archiveExists) throw new RuntimeException("Archive {$archive} already exists; refusing to overwrite it.");
        $pdo->exec('CREATE TABLE '.$quote($target).'.'.$quote($archive).' LIKE '.$quote($target).'.'.$quote($child));
        $join = ' FROM '.$quote($target).'.'.$quote($child).' c LEFT JOIN '.$quote($target).'.'.$quote($parent).' p ON c.'.$quote($column).'=p.'.$quote($parentColumn).' WHERE p.'.$quote($parentColumn).' IS NULL';
        $pdo->exec('INSERT INTO '.$quote($target).'.'.$quote($archive).' SELECT c.*'.$join);
        $count = (int) DB::selectOne('SELECT COUNT(*) AS c FROM '.$quote($target).'.'.$quote($archive))->c;
        $pdo->exec('DELETE c FROM '.$quote($target).'.'.$quote($child).' c LEFT JOIN '.$quote($target).'.'.$quote($parent).' p ON c.'.$quote($column).'=p.'.$quote($parentColumn).' WHERE p.'.$quote($parentColumn).' IS NULL');
        printf("Archived in rehearsal copy: %-38s %d rows\n", $archive, $count);
    }
    echo "Only the named rehearsal copy was changed; source timepay_db was read only.\n";
    exit(0);
}

$conversionOrder = [
    'companies', 'users', 'attendance_logs', 'attendance_records', 'leave_requests', 'attendance_event_responses',
    'payroll_profiles', 'payroll_compensation_histories', 'payroll_profile_items', 'payroll_periods', 'payroll_statutory_rules',
    'payroll_entries', 'payroll_entry_lines', 'payroll_entry_attendance_logs', 'payroll_payments', 'payroll_audits', 'payroll_attendance_allocations',
];
$presentRows = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$target]);
$present = array_map(static fn ($r) => $r->TABLE_NAME, $presentRows);

if ($mode === 'migrate') {
    foreach ($conversionOrder as $table) if (in_array($table, $present, true)) $pdo->exec('ALTER TABLE '.$quote($target).'.'.$quote($table).' ENGINE=InnoDB');
    $orphans = [
        ['payroll_attendance_allocations','payroll_entry_id','payroll_entries','id'],
        ['payroll_entry_attendance_logs','payroll_entry_id','payroll_entries','id'],
        ['payroll_entry_lines','payroll_entry_id','payroll_entries','id'],
    ];
    foreach ($orphans as [$child,$column,$parent,$parentColumn]) {
        if (!in_array($child, $present, true)) continue;
        $sql = 'SELECT COUNT(*) AS c FROM '.$quote($target).'.'.$quote($child).' c LEFT JOIN '.$quote($target).'.'.$quote($parent).' p ON c.'.$quote($column).'=p.'.$quote($parentColumn).' WHERE p.'.$quote($parentColumn).' IS NULL';
        $count = (int) DB::selectOne($sql)->c;
        if ($count > 0) throw new RuntimeException("Cannot add FK: {$child}.{$column} has {$count} orphan(s). Archive and resolve them in this copy first.");
    }
    $fks = [
        ['users','company_id','companies','id','CASCADE'],
        ['attendance_records','company_id','companies','id','CASCADE'], ['attendance_records','user_id','users','id','CASCADE'],
        ['leave_requests','company_id','companies','id','CASCADE'], ['leave_requests','user_id','users','id','CASCADE'],
        ['attendance_logs','user_id','users','id','CASCADE'], ['attendance_logs','company_id','companies','id','CASCADE'],
        ['attendance_event_responses','user_id','users','id','CASCADE'], ['attendance_event_responses','company_id','companies','id','CASCADE'],
        ['payroll_profiles','company_id','companies','id','CASCADE'], ['payroll_profiles','user_id','users','id','CASCADE'],
        ['payroll_compensation_histories','company_id','companies','id','CASCADE'], ['payroll_compensation_histories','user_id','users','id','CASCADE'], ['payroll_compensation_histories','changed_by','users','id','SET NULL'],
        ['payroll_profile_items','company_id','companies','id','CASCADE'], ['payroll_profile_items','user_id','users','id','CASCADE'], ['payroll_profile_items','created_by','users','id','SET NULL'],
        ['payroll_periods','company_id','companies','id','RESTRICT'], ['payroll_periods','created_by','users','id','SET NULL'], ['payroll_periods','calculated_by','users','id','SET NULL'], ['payroll_periods','approved_by','users','id','SET NULL'],
        ['payroll_entries','company_id','companies','id','RESTRICT'], ['payroll_entries','payroll_period_id','payroll_periods','id','RESTRICT'], ['payroll_entries','user_id','users','id','RESTRICT'],
        ['payroll_entry_lines','payroll_entry_id','payroll_entries','id','CASCADE'],
        ['payroll_entry_attendance_logs','payroll_entry_id','payroll_entries','id','CASCADE'], ['payroll_entry_attendance_logs','attendance_log_id','attendance_logs','id','RESTRICT'],
        ['payroll_statutory_rules','created_by','users','id','SET NULL'],
        ['payroll_payments','company_id','companies','id','RESTRICT'], ['payroll_payments','payroll_period_id','payroll_periods','id','RESTRICT'], ['payroll_payments','payroll_entry_id','payroll_entries','id','RESTRICT'], ['payroll_payments','recorded_by','users','id','RESTRICT'],
        ['payroll_audits','company_id','companies','id','RESTRICT'], ['payroll_audits','actor_id','users','id','SET NULL'],
        ['payroll_attendance_allocations','company_id','companies','id','RESTRICT'], ['payroll_attendance_allocations','user_id','users','id','RESTRICT'], ['payroll_attendance_allocations','payroll_period_id','payroll_periods','id','RESTRICT'], ['payroll_attendance_allocations','payroll_entry_id','payroll_entries','id','CASCADE'], ['payroll_attendance_allocations','clock_in_log_id','attendance_logs','id','RESTRICT'], ['payroll_attendance_allocations','clock_out_log_id','attendance_logs','id','RESTRICT'],
    ];
    foreach ($fks as [$child,$column,$parent,$parentColumn,$rule]) {
        if (!in_array($child, $present, true) || !in_array($parent, $present, true)) continue;
        $name = 'repair_fk_'.substr(hash('sha256', implode('|', [$child,$column,$parent,$parentColumn])), 0, 16);
        $sql = 'ALTER TABLE '.$quote($target).'.'.$quote($child).' ADD CONSTRAINT '.$quote($name).' FOREIGN KEY ('.$quote($column).') REFERENCES '.$quote($target).'.'.$quote($parent).' ('.$quote($parentColumn).') ON DELETE '.$rule.' ON UPDATE RESTRICT';
        $pdo->exec($sql);
    }
    echo "Converted tables and installed declared foreign keys in rehearsal schema.\n";
}

$engines = DB::select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME", [$target]);
foreach ($engines as $row) printf("%-40s %s\n", $row->TABLE_NAME, $row->ENGINE);
$constraints = DB::select("SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r USING (CONSTRAINT_SCHEMA, CONSTRAINT_NAME, TABLE_NAME) WHERE k.CONSTRAINT_SCHEMA=? ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME", [$target]);
printf("Foreign-key columns installed: %d\n", count($constraints));
