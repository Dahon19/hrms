<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->index('date', 'attendance_date_idx');
            $table->index(['status', 'date'], 'attendance_status_date_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                DELETE a1 FROM attendance_anomalies a1
                INNER JOIN attendance_anomalies a2
                    ON a1.employee_id = a2.employee_id
                    AND a1.date = a2.date
                    AND a1.type = a2.type
                    AND a1.id > a2.id
            ");
        } else {
            DB::statement("
                DELETE FROM attendance_anomalies
                WHERE id IN (
                    SELECT a1.id
                    FROM attendance_anomalies a1
                    JOIN attendance_anomalies a2
                        ON a1.employee_id = a2.employee_id
                        AND a1.date = a2.date
                        AND a1.type = a2.type
                        AND a1.id > a2.id
                )
            ");
        }

        Schema::table('attendance_anomalies', function (Blueprint $table) {
            $table->index(['date', 'type'], 'attendance_anomalies_date_type_idx');
            $table->unique(['employee_id', 'date', 'type'], 'attendance_anomalies_unique_daily_type');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_anomalies', function (Blueprint $table) {
            $table->dropUnique('attendance_anomalies_unique_daily_type');
            $table->dropIndex('attendance_anomalies_date_type_idx');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_date_idx');
            $table->dropIndex('attendance_status_date_idx');
        });
    }
};
