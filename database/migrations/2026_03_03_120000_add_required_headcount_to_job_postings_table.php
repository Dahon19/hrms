<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->unsignedInteger('required_headcount')->default(1)->after('status');
        });

        DB::table('job_postings')
            ->whereNull('required_headcount')
            ->update(['required_headcount' => 1]);
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('required_headcount');
        });
    }
};

