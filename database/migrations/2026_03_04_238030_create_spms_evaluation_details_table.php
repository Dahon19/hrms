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
        Schema::create('spms_evaluation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('spms_evaluations')->cascadeOnDelete();
            $table->foreignId('criteria_id')->constrained('spms_criteria')->cascadeOnDelete();
            $table->decimal('score', 7, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'criteria_id'], 'spms_eval_detail_eval_criteria_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spms_evaluation_details');
    }
};

