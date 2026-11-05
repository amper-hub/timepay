<?php

// Read-only source schema/orphan inventory. Executes SELECT statements only.
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (app()->environment() !== 'local' || config('database.default') !== 'mysql'
    || ($config['database'] ?? '') !== 'timepay_db' || ($config['host'] ?? '') !== '127.0.0.1') {
    throw new RuntimeException('Safety guard refused: expected local timepay_db at 127.0.0.1.');
}
echo "Connection: ".json_encode(DB::selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@default_storage_engine AS default_engine'))."\n";
$payroll = ['payroll_attendance_allocations','payroll_audits','payroll_compensation_histories','payroll_entries','payroll_entry_attendance_logs','payroll_entry_lines','payroll_payments','payroll_periods','payroll_profile_items','payroll_profiles','payroll_statutory_rules'];
$all = array_merge(['companies','users','attendance_logs','attendance_records','leave_requests','attendance_event_responses'], $payroll);
foreach (DB::select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $row) {
    if (in_array($row->TABLE_NAME, $all, true)) printf("ENGINE %-38s %s\n", $row->TABLE_NAME, $row->ENGINE);
}
foreach (DB::select("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX") as $row) {
    if (in_array($row->TABLE_NAME, $payroll, true)) printf("INDEX %-38s %-48s %s %d %s\n", $row->TABLE_NAME, $row->INDEX_NAME, $row->NON_UNIQUE ? 'NONUNIQUE' : 'UNIQUE', $row->SEQ_IN_INDEX, $row->COLUMN_NAME);
}
$fks = DB::select("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='FOREIGN KEY' AND TABLE_NAME LIKE 'payroll_%'");
printf("Payroll foreign-key constraints: %d\n", count($fks));
$queries = [
    ['payroll_attendance_allocations','payroll_entry_id','payroll_entries','id'],
    ['payroll_entry_attendance_logs','payroll_entry_id','payroll_entries','id'],
    ['payroll_entry_lines','payroll_entry_id','payroll_entries','id'],
];
foreach ($queries as [$child,$column,$parent,$parentColumn]) {
    $orphans = DB::select('SELECT c.* FROM `'.$child.'` c LEFT JOIN `'.$parent.'` p ON c.`'.$column.'`=p.`'.$parentColumn.'` WHERE p.`'.$parentColumn.'` IS NULL ORDER BY c.`'.$column.'`');
    echo "ORPHANS {$child}: ".count($orphans)."\n";
    foreach ($orphans as $row) echo json_encode($row, JSON_UNESCAPED_SLASHES)."\n";
}
