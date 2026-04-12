<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('position_title');
            $table->string('department_office')->nullable();
            $table->string('salary_grade')->nullable();
            $table->string('appointment_status')->nullable();
            $table->enum('sector', ['government', 'private'])->default('government');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_work_experiences');
    }
};


