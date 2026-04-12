<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('spms_cycle_id')->constrained('spms_cycles')->cascadeOnDelete();
            $table->foreignId('spms_evaluation_id')->nullable()->constrained('spms_evaluations')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->decimal('final_spms_score', 5, 2)->nullable();
            $table->string('final_spms_rating', 100)->nullable();
            $table->json('competency_gaps')->nullable();
            $table->text('development_goals')->nullable();
            $table->text('employee_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'spms_cycle_id'], 'idp_employee_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_development_plans');
    }
};
