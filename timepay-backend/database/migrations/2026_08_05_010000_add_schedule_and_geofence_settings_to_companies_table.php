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
            if (! Schema::hasColumn('companies', 'geofence_latitude')) {
                $table->decimal('geofence_latitude', 10, 8)->nullable()->after('geofence_radius_meters');
            }

            if (! Schema::hasColumn('companies', 'geofence_longitude')) {
                $table->decimal('geofence_longitude', 11, 8)->nullable()->after('geofence_latitude');
            }

            if (! Schema::hasColumn('companies', 'geofence_radius')) {
                $table->integer('geofence_radius')->nullable()->after('geofence_longitude');
            }

            if (! Schema::hasColumn('companies', 'work_start_time')) {
                $table->time('work_start_time')->nullable()->after('geofence_radius');
            }

            if (! Schema::hasColumn('companies', 'work_end_time')) {
                $table->time('work_end_time')->nullable()->after('work_start_time');
            }

            if (! Schema::hasColumn('companies', 'working_days')) {
                $table->json('working_days')->nullable()->after('work_end_time');
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
                'working_days',
                'work_end_time',
                'work_start_time',
                'geofence_radius',
                'geofence_longitude',
                'geofence_latitude',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
