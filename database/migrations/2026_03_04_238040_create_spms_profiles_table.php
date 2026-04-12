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
        Schema::create('spms_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('primary_evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secondary_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('self_assessment_enabled')->default(false);
            $table->timestamps();

            $table->unique('employee_id', 'spms_profiles_employee_unique');
            $table->index('primary_evaluator_id', 'spms_profiles_primary_evaluator_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spms_profiles');
    }
};

