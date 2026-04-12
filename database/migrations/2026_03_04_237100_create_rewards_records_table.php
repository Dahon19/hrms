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
        Schema::create('rewards_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('award_type', ['tenure', 'attendance', 'performance']);
            $table->string('award_title');
            $table->date('award_date');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'award_type'], 'rewards_employee_type_index');
            $table->index('award_date', 'rewards_award_date_index');
            $table->unique(['employee_id', 'award_type', 'award_title', 'award_date'], 'rewards_unique_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards_records');
    }
};

