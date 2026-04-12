<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination');
            $table->text('purpose');
            $table->date('date_from');
            $table->date('date_to');
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->string('transport_mode')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 40)->default('draft');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('department_approved_at')->nullable();
            $table->foreignId('hr_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('final_approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['status', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_orders');
    }
};
