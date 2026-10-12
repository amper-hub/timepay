<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->change();
            }

            if (Schema::hasColumn('companies', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Coordinates are legitimately nullable until an employer configures a geofence.
    }
};
