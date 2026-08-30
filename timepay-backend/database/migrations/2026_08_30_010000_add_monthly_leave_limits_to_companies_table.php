<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'monthly_sick_leave_limit')) {
                $table->unsignedInteger('monthly_sick_leave_limit')->default(2)->after('currency');
            }

            if (! Schema::hasColumn('companies', 'monthly_vacation_leave_limit')) {
                $table->unsignedInteger('monthly_vacation_leave_limit')->default(2)->after('monthly_sick_leave_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'monthly_vacation_leave_limit',
                'monthly_sick_leave_limit',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
