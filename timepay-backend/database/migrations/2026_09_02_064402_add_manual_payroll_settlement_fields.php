<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_logs', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('status')->index();
            }
        });

        if (Schema::hasColumn('users', 'payment_method') && DB::connection()->getDriverName() === 'mysql') {
            DB::table('users')
                ->where('payment_method', 'digital_payout')
                ->update(['payment_method' => 'manual_bank_deposit']);

            DB::statement("ALTER TABLE users MODIFY payment_method ENUM('manual_cash', 'manual_bank_deposit', 'manual_cheque') NOT NULL DEFAULT 'manual_cash'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'payment_method') && DB::connection()->getDriverName() === 'mysql') {
            DB::table('users')
                ->whereIn('payment_method', ['manual_bank_deposit', 'manual_cheque'])
                ->update(['payment_method' => 'manual_cash']);

            DB::statement("ALTER TABLE users MODIFY payment_method ENUM('manual_cash', 'digital_payout') NOT NULL DEFAULT 'manual_cash'");
        }

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'is_paid')) {
                $table->dropColumn('is_paid');
            }
        });
    }
};
