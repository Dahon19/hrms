<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0);
            $table->unsignedInteger('leave_requests')->default(0);
            $table->unsignedInteger('leave_approved')->default(0);
            $table->decimal('document_compliance_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_metrics');
    }
};
