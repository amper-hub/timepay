<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Add the users.company_id foreign key after companies has been created.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('companies')) {
            return;
        }

        $hasCompanyForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool =>
                ($foreignKey['columns'] ?? []) === ['company_id']
                && ($foreignKey['foreign_table'] ?? null) === 'companies'
            );

        if (!$hasCompanyForeignKey) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $hasCompanyForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool =>
                ($foreignKey['columns'] ?? []) === ['company_id']
                && ($foreignKey['foreign_table'] ?? null) === 'companies'
            );

        if ($hasCompanyForeignKey) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
        }
    }
};
