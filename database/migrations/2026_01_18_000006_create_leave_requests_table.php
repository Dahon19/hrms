<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', [
                'Pending',
                'Needs Revision',
                'Approved',
                'Declined',
                'HR Approved',
                'HR Declined',
            ])->default('Pending');
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('head_reviewed_by')->nullable();
            $table->timestamp('head_reviewed_at')->nullable();
            $table->unsignedBigInteger('hr_reviewed_by')->nullable();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('head_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hr_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
