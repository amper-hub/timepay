<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The existing geofence migration is recorded in production, but Railway
        // logs show that the column is absent from the live companies table.
        if (! Schema::hasColumn('companies', 'geofence_radius')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->integer('geofence_radius')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Keep the column and any values created after the repair.
    }
};
