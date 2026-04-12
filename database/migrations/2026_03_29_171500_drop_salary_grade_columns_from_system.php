<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'salary_grade')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('salary_grade');
            });
        }

        if (Schema::hasColumn('job_postings', 'salary_grade')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->dropColumn('salary_grade');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('employees', 'salary_grade')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('salary_grade', 50)->nullable()->after('address');
            });
        }

        if (!Schema::hasColumn('job_postings', 'salary_grade')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->string('salary_grade', 50)->nullable()->after('employment_type');
            });
        }
    }
};
