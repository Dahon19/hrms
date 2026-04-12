<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()
            ->with(['user', 'department'])
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin')->whereNull('archived_at'))
            ->get();

        if ($employees->count() < 3) {
            return;
        }

        $vacationLeaveId = LeaveType::query()->where('name', 'Vacation Leave')->value('id');
        $sickLeaveId = LeaveType::query()->where('name', 'Sick Leave')->value('id');

        if (!$vacationLeaveId || !$sickLeaveId) {
            return;
        }

        $hrHeadUserId = User::query()
            ->whereHas('employee.department', fn ($query) => $query->where('department', 'HR Department'))
            ->whereHas('employee.positions.position', fn ($query) => $query->where('position', 'head'))
            ->value('id');

        $departmentHeadUserId = User::query()
            ->whereHas('employee.department', fn ($query) => $query->where('department', 'College of Information Technology'))
            ->whereHas('employee.positions.position', fn ($query) => $query->where('position', 'head'))
            ->value('id');

        $employeesByEmail = $employees->keyBy(fn (Employee $employee) => strtolower((string) $employee->user?->email));

        $leaveRows = [
            [
                'employee' => $employeesByEmail->get('ivy.ramos@example.com'),
                'leave_type_id' => $vacationLeaveId,
                'start_date' => now()->copy()->subDays(1)->toDateString(),
                'end_date' => now()->copy()->addDays(1)->toDateString(),
                'status' => 'HR Approved',
                'reason' => 'Seeded approved leave for attendance and leave monitoring.',
                'head_reviewed_by' => $departmentHeadUserId,
                'head_reviewed_at' => now()->copy()->subDays(3),
                'hr_reviewed_by' => $hrHeadUserId,
                'hr_reviewed_at' => now()->copy()->subDays(2),
            ],
            [
                'employee' => $employeesByEmail->get('leo.mendoza@example.com'),
                'leave_type_id' => $sickLeaveId,
                'start_date' => now()->copy()->addDays(4)->toDateString(),
                'end_date' => now()->copy()->addDays(5)->toDateString(),
                'status' => 'Pending',
                'reason' => 'Seeded pending leave request.',
                'head_reviewed_by' => null,
                'head_reviewed_at' => null,
                'hr_reviewed_by' => null,
                'hr_reviewed_at' => null,
            ],
            [
                'employee' => $employeesByEmail->get('nina.yu@example.com'),
                'leave_type_id' => $vacationLeaveId,
                'start_date' => now()->copy()->addDays(7)->toDateString(),
                'end_date' => now()->copy()->addDays(8)->toDateString(),
                'status' => 'Approved',
                'reason' => 'Seeded head-approved leave awaiting HR action.',
                'head_reviewed_by' => User::query()
                    ->whereHas('employee.department', fn ($query) => $query->where('department', 'Registrar'))
                    ->whereHas('employee.positions.position', fn ($query) => $query->where('position', 'head'))
                    ->value('id'),
                'head_reviewed_at' => now()->copy()->subDay(),
                'hr_reviewed_by' => null,
                'hr_reviewed_at' => null,
            ],
        ];

        foreach ($leaveRows as $row) {
            if (!$row['employee']) {
                continue;
            }

            LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $row['employee']->id,
                    'leave_type_id' => $row['leave_type_id'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                ],
                [
                    'status' => $row['status'],
                    'reason' => $row['reason'],
                    'attachment_path' => null,
                    'head_reviewed_by' => $row['head_reviewed_by'],
                    'head_reviewed_at' => $row['head_reviewed_at'],
                    'hr_reviewed_by' => $row['hr_reviewed_by'],
                    'hr_reviewed_at' => $row['hr_reviewed_at'],
                    'notes' => null,
                ]
            );
        }
    }
}
