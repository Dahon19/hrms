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
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('shift_start')->default('08:00:00');
            $table->time('shift_end')->default('17:00:00');
            $table->time('break_start')->default('12:00:00');
            $table->time('break_end')->default('13:00:00');
            $table->integer('grace_minutes')->default(15);
            $table->integer('overtime_threshold_minutes')->default(60);
            $table->boolean('weekend_overtime')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
