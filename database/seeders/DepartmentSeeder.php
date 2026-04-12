<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\TravelOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['department' => 'HR Department', 'department_type' => 'Administrative'],
            ['department' => 'Presidents Office', 'department_type' => 'Administrative'],
            ['department' => 'Admissions Office', 'department_type' => 'Administrative'],
            ['department' => 'Accounting Office', 'department_type' => 'Administrative'],
            ['department' => 'Procurement Office', 'department_type' => 'Administrative'],
            ['department' => "Finance & Bursar's Office", 'department_type' => 'Administrative'],

            ['department' => 'College of Information Technology', 'department_type' => 'Academic'],
            ['department' => 'College of Engineering', 'department_type' => 'Academic'],
            ['department' => 'College of Business Administration & Accountancy', 'department_type' => 'Academic'],
            ['department' => 'College of Education', 'department_type' => 'Academic'],
            ['department' => 'College of Criminology', 'department_type' => 'Academic'],
            ['department' => 'College of Nursing & Health Sciences', 'department_type' => 'Academic'],
            ['department' => 'College of Liberal Arts', 'department_type' => 'Academic'],
            ['department' => 'College of Hospitality Management', 'department_type' => 'Academic'],
            ['department' => 'College of Law', 'department_type' => 'Academic'],
            ['department' => 'Graduate School', 'department_type' => 'Academic'],
            ['department' => 'Basic Education Center (BEC)', 'department_type' => 'Academic'],

            ['department' => 'Registrar', 'department_type' => 'Student Services'],
            ['department' => 'Guidance & Counseling Center', 'department_type' => 'Student Services'],
            ['department' => 'Office of Student Affairs', 'department_type' => 'Student Services'],

            ['department' => 'Facilities', 'department_type' => 'Support/Operations'],
            ['department' => 'Management Information Systems (MIS)', 'department_type' => 'Support/Operations'],
            ['department' => 'Library Services', 'department_type' => 'Support/Operations'],
            ['department' => 'Property & Supply Office', 'department_type' => 'Support/Operations'],
            ['department' => 'Campus Security & Safety', 'department_type' => 'Support/Operations'],
            ['department' => 'School Clinic', 'department_type' => 'Support/Operations'],
        ];

        foreach ($departments as $data) {
            Department::updateOrCreate(
                ['department' => $data['department']],
                ['department_type' => $data['department_type']]
            );
        }

        $this->removeDeprecatedDepartments();
    }

    private function removeDeprecatedDepartments(): void
    {
        $replacement = Department::query()
            ->where('department', 'Presidents Office')
            ->first();

        if (!$replacement) {
            return;
        }

        $departmentMap = [
            'Office of the Vice President for Administration' => 'Presidents Office',
            'Office of the Vice President for Academic Affairs' => 'Presidents Office',
            'Finance & Bursar’s Office' => "Finance & Bursar's Office",
        ];

        foreach ($departmentMap as $sourceName => $targetName) {
            $source = Department::query()->where('department', $sourceName)->first();
            $target = Department::query()->where('department', $targetName)->first();

            if (!$source || !$target || $source->is($target)) {
                continue;
            }

            $this->reassignDepartmentReferences($source, $target);
            $source->delete();
        }
    }

    private function reassignDepartmentReferences(Department $source, Department $target): void
    {
        Employee::query()
            ->where('department_id', $source->id)
            ->update(['department_id' => $target->id]);

        if (Schema::hasTable('job_postings')) {
            JobPosting::query()
                ->where('department_id', $source->id)
                ->update(['department_id' => $target->id]);
        }

        if (Schema::hasTable('travel_orders')) {
            TravelOrder::query()
                ->where('department_id', $source->id)
                ->update(['department_id' => $target->id]);
        }

        if (!Schema::hasColumn('positions', 'department_id')) {
            return;
        }

        $sourcePositions = Position::query()
            ->where('department_id', $source->id)
            ->get();

        foreach ($sourcePositions as $sourcePosition) {
            $targetPosition = Position::query()->firstOrCreate(
                [
                    'department_id' => $target->id,
                    'position' => $sourcePosition->position,
                ],
                [
                    'created_at' => $sourcePosition->created_at,
                    'updated_at' => $sourcePosition->updated_at,
                ]
            );

            EmployeePosition::query()
                ->where('position_id', $sourcePosition->id)
                ->update(['position_id' => $targetPosition->id]);

            if (Schema::hasTable('job_postings')) {
                JobPosting::query()
                    ->where('position_id', $sourcePosition->id)
                    ->update(['position_id' => $targetPosition->id]);
            }

            if (Schema::hasTable('travel_orders')) {
                TravelOrder::query()
                    ->where('position_id', $sourcePosition->id)
                    ->update(['position_id' => $targetPosition->id]);
            }
        }

        Position::query()
            ->where('department_id', $source->id)
            ->delete();
    }
}
