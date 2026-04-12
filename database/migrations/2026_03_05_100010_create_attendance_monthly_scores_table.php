<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_monthly_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('total_work_days')->default(0);
            $table->unsignedSmallInteger('total_absences')->default(0);
            $table->unsignedSmallInteger('late_undertime_days')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0);
            $table->decimal('punctuality_rate', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->unsignedTinyInteger('rating')->default(1);
            $table->boolean('attendance_incentive_eligible')->default(false);
            $table->enum('status', ['computed', 'locked'])->default('computed');
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year'], 'attendance_monthly_scores_employee_period_unique');
            $table->index(['month', 'year', 'status'], 'attendance_monthly_scores_period_status_idx');
            $table->index(['employee_id', 'month', 'year'], 'attendance_monthly_scores_employee_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_monthly_scores');
    }
};

