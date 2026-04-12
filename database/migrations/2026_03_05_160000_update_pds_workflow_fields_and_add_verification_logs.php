<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE pds_profiles SET status = 'pending_verification' WHERE status = 'completed'");
        DB::statement("UPDATE pds_profiles SET status = 'verified' WHERE status = 'locked'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pds_profiles MODIFY status ENUM('draft','pending_verification','needs_correction','verified') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('pds_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('pds_profiles', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('pds_profiles', 'hr_remarks')) {
                $table->text('hr_remarks')->nullable()->after('verified_by');
            }

            $table->index(['status', 'submitted_at'], 'pds_profiles_status_submitted_idx');
        });

        Schema::create('pds_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->foreignId('hr_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['approved', 'rejected']);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['pds_profile_id', 'created_at'], 'pds_verification_logs_profile_created_idx');
            $table->index(['action', 'created_at'], 'pds_verification_logs_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_verification_logs');

        Schema::table('pds_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('pds_profiles', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('pds_profiles', 'hr_remarks')) {
                $table->dropColumn('hr_remarks');
            }
            $table->dropIndex('pds_profiles_status_submitted_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pds_profiles MODIFY status ENUM('draft','completed','verified','locked') NOT NULL DEFAULT 'draft'");
        }
    }
};
