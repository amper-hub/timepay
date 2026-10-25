<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->unique();
        });
        Schema::create('attendance_event_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->json('response');
            $table->timestamps();
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->dropColumn('event_id');
        });
        Schema::dropIfExists('attendance_event_responses');
    }
};
