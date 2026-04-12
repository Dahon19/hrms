<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('spms_comments');
        Schema::dropIfExists('spms_configurations');
        Schema::dropIfExists('pds_verification_logs');
    }

    public function down(): void
    {
        Schema::create('spms_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('spms_evaluations')->cascadeOnDelete();
            $table->foreignId('commenter_id')->constrained('users')->cascadeOnDelete();
            $table->enum('stage', ['draft', 'submitted', 'reviewed', 'locked'])->default('submitted');
            $table->text('comment');
            $table->timestamps();

            $table->index(['evaluation_id', 'stage'], 'spms_comments_eval_stage_index');
        });

        Schema::create('spms_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value');
            $table->text('description')->nullable();
            $table->timestamps();
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
};
