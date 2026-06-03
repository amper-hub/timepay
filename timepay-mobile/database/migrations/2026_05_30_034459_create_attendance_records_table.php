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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('latitude_in', 10, 8)->nullable();
            $table->decimal('longitude_in', 11, 8)->nullable();
            $table->decimal('latitude_out', 10, 8)->nullable();
            $table->decimal('longitude_out', 11, 8)->nullable();
            $table->enum('status', ['present', 'late', 'absent'])->default('present');
            $table->timestamps();
            
            // Optimized composite index for multi-tenant queries
            $table->index(['company_id', 'user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
