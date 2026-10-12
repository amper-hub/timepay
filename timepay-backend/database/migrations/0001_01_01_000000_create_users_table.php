
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
        // Create users only if it doesn't already exist.
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies')
                    ->cascadeOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('password');
                $table->enum('role', ['admin', 'employee'])
                    ->default('employee');
                $table->string('cloud_face_id')->nullable()->index();
                $table->decimal('hourly_rate', 10, 2)->default(0.00);
                $table->decimal('daily_rate', 10, 2)->default(0.00);
                $table->rememberToken();
                $table->timestamps();

                $table->unique(['company_id', 'email']);
            });
        }

        // Create password_reset_tokens only if missing.
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Create sessions only if missing.
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally non-destructive to protect existing production data.
     */
    public function down(): void
    {
        // Do not drop tables that may contain existing production data.
    }
};

