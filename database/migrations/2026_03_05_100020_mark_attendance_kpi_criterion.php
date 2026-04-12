<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('spms_criteria')
            ->whereRaw('LOWER(name) in (?, ?)', ['attendance', 'attendance kpi'])
            ->update([
                'name' => 'Attendance KPI',
                'category' => 'attendance_kpi',
            ]);
    }

    public function down(): void
    {
        DB::table('spms_criteria')
            ->where('category', 'attendance_kpi')
            ->whereRaw('LOWER(name) = ?', ['attendance kpi'])
            ->update([
                'name' => 'Attendance',
                'category' => 'support',
            ]);
    }
};

