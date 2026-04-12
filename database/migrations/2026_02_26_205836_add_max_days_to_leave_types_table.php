<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // Maximum days allowed per year for this leave type.
            // NULL = uses the accrual-based VL/SL formula (earned days).
            $table->unsignedTinyInteger('max_days')->nullable()->after('requires_attachment');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('max_days');
        });
    }
};
