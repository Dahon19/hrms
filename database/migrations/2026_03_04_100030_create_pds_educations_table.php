<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->enum('education_level', ['elementary', 'secondary', 'vocational', 'college', 'graduate']);
            $table->string('school_name')->nullable();
            $table->string('degree_course')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('highest_level_units')->nullable();
            $table->string('year_graduated')->nullable();
            $table->string('honors_received')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'education_level']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_educations');
    }
};


