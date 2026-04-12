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
        Schema::create('spms_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('spms_cycles')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted', 'verified', 'locked'])->default('draft');
            $table->decimal('total_score', 7, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'cycle_id'], 'spms_eval_employee_cycle_unique');
            $table->index(['cycle_id', 'status'], 'spms_eval_cycle_status_index');
            $table->index(['evaluator_id', 'status'], 'spms_eval_evaluator_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spms_evaluations');
    }
};

