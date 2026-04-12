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
        Schema::create('eligibility_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->boolean('eligible_tenure')->default(false);
            $table->boolean('eligible_attendance')->default(false);
            $table->boolean('eligible_performance')->default(false);
            $table->unsignedTinyInteger('tenure_years')->default(0);
            $table->unsignedTinyInteger('tenure_milestone')->nullable();
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->string('attendance_rating', 60)->nullable();
            $table->decimal('spms_score', 5, 2)->nullable();
            $table->string('spms_rating', 60)->nullable();
            $table->json('payload');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['employee_id', 'year'], 'eligibility_cache_employee_year_unique');
            $table->index(['year', 'eligible_tenure', 'eligible_attendance', 'eligible_performance'], 'eligibility_cache_year_eligibility_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eligibility_cache');
    }
};

