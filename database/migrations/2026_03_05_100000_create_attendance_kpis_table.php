<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('target_percentage', 5, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['month', 'year'], 'attendance_kpis_month_year_unique');
            $table->index(['is_active', 'year', 'month'], 'attendance_kpis_active_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kpis');
    }
};

