<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pds_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('pds_profiles', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('pds_profiles', 'correction_requested_at')) {
                $table->timestamp('correction_requested_at')->nullable()->after('hr_remarks');
            }

            if (!Schema::hasColumn('pds_profiles', 'correction_requested_by')) {
                $table->foreignId('correction_requested_by')->nullable()->after('correction_requested_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pds_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('pds_profiles', 'correction_requested_by')) {
                $table->dropConstrainedForeignId('correction_requested_by');
            }

            if (Schema::hasColumn('pds_profiles', 'correction_requested_at')) {
                $table->dropColumn('correction_requested_at');
            }

            if (Schema::hasColumn('pds_profiles', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }
        });
    }
};
