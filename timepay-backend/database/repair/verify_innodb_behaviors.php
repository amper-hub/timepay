<?php

// Transaction, lock, uniqueness, and FK rehearsal against an approved copy only.
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$target = $argv[1] ?? '';
$allowed = ['timepay_repair_copy_20261102', 'timepay_payroll_test_copy_20261102'];
$config = config('database.connections.mysql');
if (!in_array($target, $allowed, true) || app()->environment() !== 'local'
    || config('database.default') !== 'mysql' || ($config['database'] ?? '') !== 'timepay_db'
    || ($config['host'] ?? '') !== '127.0.0.1') {
    throw new RuntimeException('Safety guard refused the database target.');
}
if (!(bool) DB::selectOne('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [$target])) {
    throw new RuntimeException('Target rehearsal schema does not exist.');
}
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? 3306, $target);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$one = new PDO($dsn, $config['username'], $config['password'], $options);
$q = static fn (string $s): string => '`'.str_replace('`', '``', $s).'`';

// Transaction rollback: inserted marker must not survive rollback.
$one->beginTransaction();
$one->prepare('INSERT INTO payroll_statutory_rules (code,name,effective_from,effective_to,configuration,source_url,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute(['repair_rollback_probe','Repair rollback probe','2098-01-01',null,'{}',null,null,'2026-11-02 00:00:00','2026-11-02 00:00:00']);
$one->rollBack();
$count = (int) $one->query("SELECT COUNT(*) FROM payroll_statutory_rules WHERE code='repair_rollback_probe'")->fetchColumn();
if ($count !== 0) throw new RuntimeException('Transaction rollback failed.');
echo "PASS transaction rollback\n";

// Unique index: a duplicate (code,effective_from) must be rejected.
$one->beginTransaction();
$insert = $one->prepare('INSERT INTO payroll_statutory_rules (code,name,effective_from,effective_to,configuration,source_url,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
$values = ['repair_unique_probe','Repair unique probe','2098-02-01',null,'{}',null,null,'2026-11-02 00:00:00','2026-11-02 00:00:00'];
$insert->execute($values);
$duplicateRejected = false;
try { $insert->execute($values); } catch (PDOException $e) { $duplicateRejected = (string) $e->getCode() === '23000'; }
$one->rollBack();
if (!$duplicateRejected) throw new RuntimeException('Expected unique constraint did not reject duplicate.');
echo "PASS unique constraint (code,effective_from)\n";

// A second connection must wait on an InnoDB row lock and time out.
$two = new PDO($dsn, $config['username'], $config['password'], $options);
$id = (int) $one->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
if ($id < 1) throw new RuntimeException('No copied user available for row-lock test.');
$one->beginTransaction();
$one->query("SELECT id FROM users WHERE id={$id} FOR UPDATE")->fetch();
$two->exec('SET SESSION innodb_lock_wait_timeout=1');
$two->beginTransaction();
$lockRejected = false;
try { $two->query("SELECT id FROM users WHERE id={$id} FOR UPDATE")->fetch(); }
catch (PDOException $e) { $lockRejected = in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true); }
$two->rollBack();
$one->rollBack();
if (!$lockRejected) throw new RuntimeException('Second connection did not block on the InnoDB row lock.');
echo "PASS row lock blocks a concurrent writer/locker\n";

// FK behavior: payments RESTRICT entry deletion; deleting entry cascades lines.
$one->beginTransaction();
try {
    $companyId = (int) $one->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetchColumn();
    $userId = (int) $one->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    $one->prepare("INSERT INTO payroll_periods (company_id,period_start,period_end,pay_date,frequency,status,created_by,created_at,updated_at) VALUES (?, '2098-03-01','2098-03-07',NULL,'weekly','draft',?,NOW(),NOW())")->execute([$companyId,$userId]);
    $periodId = (int) $one->lastInsertId();
    $one->prepare("INSERT INTO payroll_entries (company_id,payroll_period_id,user_id,calculation_snapshot,created_at,updated_at) VALUES (?,?,?,'{}',NOW(),NOW())")->execute([$companyId,$periodId,$userId]);
    $entryId = (int) $one->lastInsertId();
    $one->prepare("INSERT INTO payroll_entry_lines (payroll_entry_id,category,code,label,amount,created_at,updated_at) VALUES (?,'earning','probe','FK probe',1,NOW(),NOW())")->execute([$entryId]);
    $one->prepare("INSERT INTO payroll_payments (company_id,payroll_period_id,payroll_entry_id,amount,payment_date,method,reference_number,recorded_by,created_at,updated_at) VALUES (?,?,?,1,'2098-03-08','manual_cash',NULL,?,NOW(),NOW())")->execute([$companyId,$periodId,$entryId,$userId]);
    $restrictRejected = false;
    try { $one->exec("DELETE FROM payroll_entries WHERE id={$entryId}"); }
    catch (PDOException $e) { $restrictRejected = (string) $e->getCode() === '23000'; }
    if (!$restrictRejected) throw new RuntimeException('Payment did not restrict entry deletion.');
    $one->exec("DELETE FROM payroll_payments WHERE payroll_entry_id={$entryId}");
    $one->exec("DELETE FROM payroll_entries WHERE id={$entryId}");
    if ((int) $one->query("SELECT COUNT(*) FROM payroll_entry_lines WHERE payroll_entry_id={$entryId}")->fetchColumn() !== 0) {
        throw new RuntimeException('Entry-line cascade did not occur.');
    }
    echo "PASS payment RESTRICT and entry-line CASCADE\n";
} finally {
    if ($one->inTransaction()) $one->rollBack();
}

echo "All InnoDB behavior probes passed against {$target}; fixture transaction rolled back.\n";
