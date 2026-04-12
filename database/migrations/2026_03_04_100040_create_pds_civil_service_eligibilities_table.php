<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_civil_service_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('eligibility_type');
            $table->string('rating')->nullable();
            $table->date('exam_date')->nullable();
            $table->string('exam_place')->nullable();
            $table->string('license_number')->nullable();
            $table->date('validity_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_civil_service_eligibilities');
    }
};


