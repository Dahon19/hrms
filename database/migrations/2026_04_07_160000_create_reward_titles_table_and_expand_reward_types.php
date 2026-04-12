<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_titles', function (Blueprint $table) {
            $table->id();
            $table->string('award_type', 50);
            $table->string('title');
            $table->timestamps();

            $table->unique(['award_type', 'title'], 'reward_titles_type_title_unique');
            $table->index('award_type', 'reward_titles_award_type_index');
        });

        DB::table('reward_titles')->insert([
            [
                'award_type' => 'tenure',
                'title' => 'Service Milestone Recognition',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'award_type' => 'attendance',
                'title' => 'Perfect Attendance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'award_type' => 'performance',
                'title' => 'Performance Excellence',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'award_type' => 'special',
                'title' => 'Special Recognition',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('rewards_records')) {
            DB::statement("
                ALTER TABLE `rewards_records`
                MODIFY `award_type` ENUM('tenure', 'attendance', 'performance', 'special') NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('rewards_records')) {
            DB::statement("
                ALTER TABLE `rewards_records`
                MODIFY `award_type` ENUM('tenure', 'attendance', 'performance') NOT NULL
            ");
        }

        Schema::dropIfExists('reward_titles');
    }
};
