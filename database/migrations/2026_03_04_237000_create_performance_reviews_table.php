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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('review_year');
            $table->decimal('spms_score', 4, 2);
            $table->enum('rating', [
                'outstanding',
                'very_satisfactory',
                'satisfactory',
                'unsatisfactory',
                'poor',
            ])->default('satisfactory');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'review_year'], 'performance_reviews_employee_year_unique');
            $table->index(['review_year', 'rating'], 'performance_reviews_year_rating_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};

