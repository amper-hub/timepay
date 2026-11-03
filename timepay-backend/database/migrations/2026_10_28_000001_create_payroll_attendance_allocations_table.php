<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_attendance_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clock_in_log_id')->constrained('attendance_logs')->restrictOnDelete();
            $table->foreignId('clock_out_log_id')->constrained('attendance_logs')->restrictOnDelete();
            $table->dateTime('original_clock_in_at', 6);
            $table->dateTime('original_clock_out_at', 6);
            $table->dateTime('segment_start_at', 6);
            $table->dateTime('segment_end_at', 6);
            $table->unsignedBigInteger('elapsed_seconds');
            $table->char('allocation_key', 64)->unique('payroll_attendance_allocation_key_unique');
            $table->timestamps();

            $table->unique(
                ['clock_in_log_id', 'clock_out_log_id', 'payroll_period_id'],
                'payroll_attendance_shift_period_unique'
            );
            $table->index(['company_id', 'user_id', 'payroll_period_id'], 'payroll_attendance_employee_period_idx');
            $table->index(['clock_in_log_id', 'clock_out_log_id'], 'payroll_attendance_shift_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_allocations');
    }
};
