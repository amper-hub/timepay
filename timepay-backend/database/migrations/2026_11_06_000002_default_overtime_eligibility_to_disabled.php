<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_profiles', function (Blueprint $table): void {
            $table->boolean('overtime_eligible')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_profiles', function (Blueprint $table): void {
            $table->boolean('overtime_eligible')->default(true)->change();
        });
    }
};
