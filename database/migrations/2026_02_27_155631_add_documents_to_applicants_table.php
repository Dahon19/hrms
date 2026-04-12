<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('application_letter_path')->nullable()->after('message');
            $table->string('resume_path')->nullable()->after('application_letter_path');
            $table->string('transcript_path')->nullable()->after('resume_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['application_letter_path', 'resume_path', 'transcript_path']);
        });
    }
};
