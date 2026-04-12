<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'address')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->text('address')->nullable()->after('last_name');
            });
        }

        if (!Schema::hasColumn('applicants', 'address')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->text('address')->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'address')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }

        if (Schema::hasColumn('applicants', 'address')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
};
