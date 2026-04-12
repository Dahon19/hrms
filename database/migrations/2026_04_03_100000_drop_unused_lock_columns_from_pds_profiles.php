<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pds_profiles', 'is_locked')) {
            Schema::table('pds_profiles', function (Blueprint $table): void {
                $table->dropColumn('is_locked');
            });
        }

        if (Schema::hasColumn('pds_profiles', 'locked_at')) {
            Schema::table('pds_profiles', function (Blueprint $table): void {
                $table->dropColumn('locked_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pds_profiles', 'is_locked')) {
            Schema::table('pds_profiles', function (Blueprint $table): void {
                $table->boolean('is_locked')->default(false)->after('status');
            });
        }

        if (! Schema::hasColumn('pds_profiles', 'locked_at')) {
            Schema::table('pds_profiles', function (Blueprint $table): void {
                $table->timestamp('locked_at')->nullable()->after('verified_at');
            });
        }
    }
};
