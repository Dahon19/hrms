<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leave_balance_year_settings')) {
            return;
        }

        Schema::table('leave_balance_year_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_balance_year_settings', 'eligibility_months')) {
                $table->unsignedSmallInteger('eligibility_months')->nullable()->after('starting_balance');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leave_balance_year_settings')) {
            return;
        }

        Schema::table('leave_balance_year_settings', function (Blueprint $table) {
            if (Schema::hasColumn('leave_balance_year_settings', 'eligibility_months')) {
                $table->dropColumn('eligibility_months');
            }
        });
    }
};
