<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('positions', 'department_id')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('id');
            });
        }

        DB::transaction(function () {
            $positions = DB::table('positions')
                ->select('id', 'position', 'created_at', 'updated_at', 'department_id')
                ->orderBy('id')
                ->get();

            foreach ($positions as $position) {
                $departmentIds = DB::table('employee_positions')
                    ->join('employees', 'employees.id', '=', 'employee_positions.employee_id')
                    ->where('employee_positions.position_id', $position->id)
                    ->whereNotNull('employees.department_id')
                    ->pluck('employees.department_id')
                    ->merge(
                        DB::table('job_postings')
                            ->where('position_id', $position->id)
                            ->whereNotNull('department_id')
                            ->pluck('department_id')
                    )
                    ->map(fn ($departmentId) => (int) $departmentId)
                    ->filter(fn ($departmentId) => $departmentId > 0)
                    ->unique()
                    ->values();

                if ($departmentIds->isEmpty()) {
                    continue;
                }

                $primaryDepartmentId = (int) $departmentIds->shift();

                DB::table('positions')
                    ->where('id', $position->id)
                    ->update(['department_id' => $primaryDepartmentId]);

                foreach ($departmentIds as $departmentId) {
                    $newPositionId = DB::table('positions')->insertGetId([
                        'department_id' => $departmentId,
                        'position' => $position->position,
                        'created_at' => $position->created_at,
                        'updated_at' => $position->updated_at,
                    ]);

                    $employeeIds = DB::table('employees')
                        ->where('department_id', $departmentId)
                        ->pluck('id');

                    if ($employeeIds->isNotEmpty()) {
                        DB::table('employee_positions')
                            ->where('position_id', $position->id)
                            ->whereIn('employee_id', $employeeIds)
                            ->update(['position_id' => $newPositionId]);
                    }

                    DB::table('job_postings')
                        ->where('position_id', $position->id)
                        ->where('department_id', $departmentId)
                        ->update(['position_id' => $newPositionId]);
                }
            }
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('cascade');

            $table->unique(['department_id', 'position'], 'positions_department_position_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('positions', 'department_id')) {
            return;
        }

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique('positions_department_position_unique');
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
