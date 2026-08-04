<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'baseline_photo_path')) {
                $table->string('baseline_photo_path')->nullable()->after('cloud_face_id');
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendance_logs MODIFY status ENUM('verified', 'rejected', 'flagged') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendance_logs MODIFY status ENUM('verified', 'rejected') NOT NULL");
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'baseline_photo_path')) {
                $table->dropColumn('baseline_photo_path');
            }
        });
    }
};
