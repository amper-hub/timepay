<?php

// Read-only period 4/pivot inspection. No writes or repair statements.
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (app()->environment() !== 'local' || config('database.default') !== 'mysql'
    || ($connection['database'] ?? '') !== 'timepay_db' || ($connection['host'] ?? '') !== '127.0.0.1') {
    throw new RuntimeException('Safety guard refused: expected local timepay_db at 127.0.0.1.');
}
echo 'CONNECTION '.json_encode(DB::selectOne('SELECT DATABASE() AS db, @@hostname AS host'))."\n";
$period = DB::selectOne('SELECT id,company_id,period_start,period_end,frequency,status FROM payroll_periods WHERE id=4');
echo 'PERIOD '.json_encode($period)."\n";
$employee = DB::selectOne("SELECT id,name,company_id,status FROM users WHERE id=3");
echo 'EMPLOYEE '.json_encode($employee)."\n";
foreach (DB::select("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('payroll_entries','payroll_entry_attendance_logs')") as $row) {
    echo 'ENGINE '.json_encode($row)."\n";
}
foreach (DB::select("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payroll_entry_attendance_logs' ORDER BY INDEX_NAME,SEQ_IN_INDEX") as $row) {
    echo 'INDEX '.json_encode($row)."\n";
}
$entries = DB::select('SELECT id,company_id,payroll_period_id,user_id,worked_minutes,payable_days,status_snapshot FROM payroll_entries LEFT JOIN (SELECT id AS period_ref,status AS status_snapshot FROM payroll_periods) p ON p.period_ref=payroll_period_id WHERE payroll_period_id=4 ORDER BY id');
foreach ($entries as $row) echo 'PERIOD_ENTRY '.json_encode($row)."\n";
$pivots = DB::select('SELECT s.payroll_entry_id,s.attendance_log_id,e.company_id AS entry_company_id,e.user_id AS entry_user_id,e.payroll_period_id,period.status AS period_status,l.company_id AS log_company_id,l.user_id AS log_user_id,l.timestamp AS log_timestamp,l.type AS log_type FROM payroll_entry_attendance_logs s LEFT JOIN payroll_entries e ON e.id=s.payroll_entry_id LEFT JOIN payroll_periods period ON period.id=e.payroll_period_id LEFT JOIN attendance_logs l ON l.id=s.attendance_log_id WHERE e.payroll_period_id=4 OR (s.payroll_entry_id=1) OR (s.attendance_log_id IN (3,4,5,6)) ORDER BY s.payroll_entry_id,s.attendance_log_id');
foreach ($pivots as $row) echo 'PIVOT '.json_encode($row)."\n";
$allocations = DB::select('SELECT id,company_id,user_id,payroll_period_id,payroll_entry_id,clock_in_log_id,clock_out_log_id,elapsed_seconds FROM payroll_attendance_allocations WHERE payroll_period_id=4 ORDER BY id');
foreach ($allocations as $row) echo 'ALLOCATION '.json_encode($row)."\n";
