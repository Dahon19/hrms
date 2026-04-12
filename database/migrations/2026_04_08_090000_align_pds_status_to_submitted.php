<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pds_profiles') || !Schema::hasColumn('pds_profiles', 'status')) {
            return;
        }

        DB::table('pds_profiles')
            ->where('status', 'pending_verification')
            ->update(['status' => 'submitted']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE pds_profiles MODIFY status ENUM('draft','submitted','needs_correction','verified') NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('pds_profiles') || !Schema::hasColumn('pds_profiles', 'status')) {
            return;
        }

        DB::table('pds_profiles')
            ->where('status', 'submitted')
            ->whereNotNull('submitted_at')
            ->update(['status' => 'pending_verification']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE pds_profiles MODIFY status ENUM('draft','pending_verification','needs_correction','verified') NOT NULL DEFAULT 'draft'"
        );
    }
};
