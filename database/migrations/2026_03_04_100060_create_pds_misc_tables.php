<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_voluntary_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('organization_name');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('hours')->default(0);
            $table->string('position_nature')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'date_from', 'date_to']);
        });

        Schema::create('pds_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('hours')->default(0);
            $table->string('training_type')->nullable();
            $table->string('conducted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'date_from', 'date_to']);
        });

        Schema::create('pds_other_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->enum('info_type', ['special_skill', 'recognition', 'membership']);
            $table->text('description');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'info_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_other_infos');
        Schema::dropIfExists('pds_trainings');
        Schema::dropIfExists('pds_voluntary_works');
    }
};


