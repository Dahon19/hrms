<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE spms_cycles SET status = 'reviewed' WHERE status = 'verified'");
        DB::statement("UPDATE spms_evaluations SET status = 'reviewed' WHERE status = 'verified'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE spms_cycles MODIFY status ENUM('draft','submitted','reviewed','locked') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE spms_evaluations MODIFY status ENUM('draft','submitted','reviewed','locked') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE spms_cycles SET status = 'verified' WHERE status = 'reviewed'");
        DB::statement("UPDATE spms_evaluations SET status = 'verified' WHERE status = 'reviewed'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE spms_cycles MODIFY status ENUM('draft','submitted','verified','locked') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE spms_evaluations MODIFY status ENUM('draft','submitted','verified','locked') NOT NULL DEFAULT 'draft'");
        }
    }
};
