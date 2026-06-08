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
            if (Schema::hasColumn('companies', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->change();
            } else {
                $table->decimal('latitude', 10, 8)->nullable()->after('name');
            }

            if (Schema::hasColumn('companies', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->change();
            } else {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }

            if (Schema::hasColumn('companies', 'geofence_radius_meters')) {
                $table->integer('geofence_radius_meters')->default(100)->change();
            } else {
                $table->integer('geofence_radius_meters')->default(100)->after('longitude');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hourly_rate')) {
                $table->decimal('hourly_rate', 8, 2)->default(0.00)->change();
            } else {
                $table->decimal('hourly_rate', 8, 2)->default(0.00)->after('cloud_face_id');
            }

            if (! Schema::hasColumn('users', 'payment_method')) {
                $table->enum('payment_method', ['manual_cash', 'digital_payout'])
                    ->default('manual_cash')
                    ->after('hourly_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable(false)->change();
            }

            if (Schema::hasColumn('companies', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable(false)->change();
            }

            if (Schema::hasColumn('companies', 'geofence_radius_meters')) {
                $table->integer('geofence_radius_meters')->default(150)->change();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->default(0.00)->change();
            }
        });
    }
};
