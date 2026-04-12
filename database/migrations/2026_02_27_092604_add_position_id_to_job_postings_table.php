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
        if (!Schema::hasTable('job_postings')) {
            return;
        }

        Schema::table('job_postings', function (Blueprint $table) {
            if (!Schema::hasColumn('job_postings', 'position_id')) {
                $table->foreignId('position_id')->after('id')->constrained()->onDelete('cascade');
            }
        });

        if (Schema::hasColumn('job_postings', 'title')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->string('title')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('job_postings')) {
            return;
        }

        if (Schema::hasColumn('job_postings', 'position_id')) {
            try {
                Schema::table('job_postings', function (Blueprint $table) {
                    $table->dropForeign(['position_id']);
                });
            } catch (\Throwable $e) {
                // Ignore if foreign key was already dropped manually.
            }

            Schema::table('job_postings', function (Blueprint $table) {
                $table->dropColumn('position_id');
            });
        }

        if (Schema::hasColumn('job_postings', 'title')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->string('title')->nullable(false)->change();
            });
        }
    }
};
