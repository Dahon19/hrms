<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TravelOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('travel_orders')
            || !Schema::hasTable('employees')
        ) {
            return;
        }

        $employees = Employee::query()
            ->with(['user', 'department', 'positions.position'])
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin')
                    ->whereNull('archived_at');
            })
            ->get()
            ->keyBy(fn (Employee $employee) => strtolower((string) $employee->user?->email));

        if ($employees->isEmpty()) {
            return;
        }

        $adminUserId = User::query()->where('role', 'admin')->value('id');
        $hrHeadUserId = User::query()
            ->whereHas('employee.department', fn ($query) => $query->where('department', 'HR Department'))
            ->whereHas('employee.positions.position', fn ($query) => $query->where('position', 'head'))
            ->value('id');
        $presidentHeadUserId = User::query()
            ->whereHas('employee.department', fn ($query) => $query->where('department', 'Presidents Office'))
            ->whereHas('employee.positions.position', fn ($query) => $query->where('position', 'head'))
            ->value('id');

        $seedRows = [
            [
                'employee_email' => 'paulo.cruz@example.com',
                'destination' => 'Commission on Higher Education - Regional Office',
                'purpose' => 'Submit HR compliance records and staffing report.',
                'date_from' => Carbon::today()->addDays(2),
                'date_to' => Carbon::today()->addDays(2),
                'transport_mode' => 'Service vehicle',
                'remarks' => 'Seeded draft travel order for edit and submit testing.',
                'status' => TravelOrder::STATUS_DRAFT,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
            ],
            [
                'employee_email' => 'ivy.ramos@example.com',
                'destination' => 'San Fernando Extension Campus',
                'purpose' => 'Coordinate laboratory inventory and curriculum files.',
                'date_from' => Carbon::today()->addDays(10),
                'date_to' => Carbon::today()->addDays(11),
                'transport_mode' => 'Bus',
                'remarks' => 'Seeded submitted travel order awaiting department approval.',
                'status' => TravelOrder::STATUS_SUBMITTED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDay()->setTime(8, 45),
            ],
            [
                'employee_email' => 'leo.mendoza@example.com',
                'destination' => 'Dagupan Partner Institution',
                'purpose' => 'Represent the college in inter-campus faculty coordination.',
                'date_from' => Carbon::today()->addDays(14),
                'date_to' => Carbon::today()->addDays(15),
                'transport_mode' => 'Private vehicle',
                'remarks' => 'Seeded department-approved travel order awaiting HR review.',
                'status' => TravelOrder::STATUS_DEPARTMENT_APPROVED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn () => $hrHeadUserId,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(2)->setTime(9, 0),
                'department_approved_by' => fn () => $this->departmentHeadUserIdForEmployee($employees, 'marc.jenkins@example.com'),
                'department_approved_at' => Carbon::today()->subDay()->setTime(10, 30),
            ],
            [
                'employee_email' => 'rhea.torres@example.com',
                'destination' => 'Baguio Academic Conference Center',
                'purpose' => 'Attend systems planning workshop for department coordinators.',
                'date_from' => Carbon::today()->addDays(18),
                'date_to' => Carbon::today()->addDays(19),
                'transport_mode' => 'Van',
                'remarks' => 'Seeded HR-reviewed travel order awaiting final approval.',
                'status' => TravelOrder::STATUS_HR_REVIEW,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn () => $hrHeadUserId ?? $adminUserId,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(4)->setTime(8, 30),
                'department_approved_by' => fn () => $this->departmentHeadUserIdForEmployee($employees, 'marc.jenkins@example.com'),
                'department_approved_at' => Carbon::today()->subDays(3)->setTime(11, 0),
                'hr_reviewed_by' => fn () => $hrHeadUserId ?? $adminUserId,
                'hr_reviewed_at' => Carbon::today()->subDays(2)->setTime(14, 0),
            ],
            [
                'employee_email' => 'kyle.navarro@example.com',
                'destination' => 'University Partner Campus - Manila',
                'purpose' => 'Deliver signed partnership documents and collect signed copies.',
                'date_from' => Carbon::today()->subDays(1),
                'date_to' => Carbon::today(),
                'transport_mode' => 'Plane',
                'remarks' => 'Seeded approved travel order for attendance official business visibility.',
                'status' => TravelOrder::STATUS_APPROVED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn () => $presidentHeadUserId ?? $hrHeadUserId ?? $adminUserId,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(6)->setTime(8, 15),
                'department_approved_by' => fn () => $this->departmentHeadUserIdForEmployee($employees, 'marc.jenkins@example.com'),
                'department_approved_at' => Carbon::today()->subDays(5)->setTime(9, 45),
                'hr_reviewed_by' => fn () => $hrHeadUserId ?? $adminUserId,
                'hr_reviewed_at' => Carbon::today()->subDays(4)->setTime(13, 15),
                'final_approved_by' => fn () => $presidentHeadUserId ?? $hrHeadUserId ?? $adminUserId,
                'final_approved_at' => Carbon::today()->subDays(3)->setTime(16, 0),
                'approved_at' => Carbon::today()->subDays(3)->setTime(16, 0),
            ],
            [
                'employee_email' => 'tess.molina@example.com',
                'destination' => 'Regional Procurement Office',
                'purpose' => 'Finalize facilities requisition and submit completion documents.',
                'date_from' => Carbon::today()->subDays(12),
                'date_to' => Carbon::today()->subDays(11),
                'transport_mode' => 'Service vehicle',
                'remarks' => 'Seeded completed travel order for history and reports.',
                'status' => TravelOrder::STATUS_COMPLETED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn () => $adminUserId ?? $hrHeadUserId,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(20)->setTime(8, 0),
                'department_approved_by' => fn () => $this->departmentHeadUserIdForEmployee($employees, 'samuel.diaz@example.com'),
                'department_approved_at' => Carbon::today()->subDays(19)->setTime(9, 20),
                'hr_reviewed_by' => fn () => $hrHeadUserId ?? $adminUserId,
                'hr_reviewed_at' => Carbon::today()->subDays(18)->setTime(14, 20),
                'final_approved_by' => fn () => $presidentHeadUserId ?? $hrHeadUserId ?? $adminUserId,
                'final_approved_at' => Carbon::today()->subDays(17)->setTime(10, 40),
                'approved_at' => Carbon::today()->subDays(17)->setTime(10, 40),
                'completed_at' => Carbon::today()->subDays(10)->setTime(17, 0),
            ],
            [
                'employee_email' => 'nina.yu@example.com',
                'destination' => 'Commission on Audit Field Office',
                'purpose' => 'Follow up registrar document validation and records turnover.',
                'date_from' => Carbon::today()->addDays(22),
                'date_to' => Carbon::today()->addDays(22),
                'transport_mode' => 'Bus',
                'remarks' => 'Seeded cancelled travel order.',
                'status' => TravelOrder::STATUS_CANCELLED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(2)->setTime(15, 0),
                'cancelled_at' => Carbon::today()->subDay()->setTime(10, 0),
            ],
            [
                'employee_email' => 'alyssa.lim@example.com',
                'destination' => 'CHED Satellite Office',
                'purpose' => 'Present registrar process updates and policy recommendations.',
                'date_from' => Carbon::today()->addDays(26),
                'date_to' => Carbon::today()->addDays(27),
                'transport_mode' => 'Van',
                'remarks' => 'Seeded rejected travel order.',
                'status' => TravelOrder::STATUS_REJECTED,
                'created_by' => fn (Employee $employee) => $employee->user_id,
                'updated_by' => fn () => $hrHeadUserId ?? $adminUserId,
                'submitted_by' => fn (Employee $employee) => $employee->user_id,
                'submitted_at' => Carbon::today()->subDays(3)->setTime(8, 10),
                'department_approved_by' => fn () => $this->departmentHeadUserIdForEmployee($employees, 'alyssa.lim@example.com'),
                'department_approved_at' => Carbon::today()->subDays(2)->setTime(9, 10),
                'rejected_at' => Carbon::today()->subDay()->setTime(13, 0),
            ],
        ];

        foreach ($seedRows as $row) {
            /** @var Employee|null $employee */
            $employee = $employees->get(strtolower((string) $row['employee_email']));

            if (!$employee || !$employee->user_id) {
                continue;
            }

            $positionId = $employee->positions->pluck('position_id')->filter()->first();
            if (!$positionId) {
                continue;
            }

            TravelOrder::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'destination' => $row['destination'],
                    'date_from' => $row['date_from']->toDateString(),
                    'date_to' => $row['date_to']->toDateString(),
                ],
                [
                    'department_id' => $employee->department_id,
                    'position_id' => $positionId,
                    'purpose' => $row['purpose'],
                    'departure_time' => '08:00',
                    'return_time' => '17:00',
                    'transport_mode' => $row['transport_mode'],
                    'remarks' => $row['remarks'],
                    'status' => $row['status'],
                    'created_by' => value($row['created_by'], $employee),
                    'updated_by' => value($row['updated_by'], $employee),
                    'submitted_by' => value($row['submitted_by'], $employee),
                    'submitted_at' => isset($row['submitted_at']) ? $row['submitted_at'] : null,
                    'department_approved_by' => isset($row['department_approved_by']) ? value($row['department_approved_by'], $employee) : null,
                    'department_approved_at' => $row['department_approved_at'] ?? null,
                    'hr_reviewed_by' => isset($row['hr_reviewed_by']) ? value($row['hr_reviewed_by'], $employee) : null,
                    'hr_reviewed_at' => $row['hr_reviewed_at'] ?? null,
                    'final_approved_by' => isset($row['final_approved_by']) ? value($row['final_approved_by'], $employee) : null,
                    'final_approved_at' => $row['final_approved_at'] ?? null,
                    'approved_at' => $row['approved_at'] ?? null,
                    'rejected_at' => $row['rejected_at'] ?? null,
                    'cancelled_at' => $row['cancelled_at'] ?? null,
                    'completed_at' => $row['completed_at'] ?? null,
                ]
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, Employee>  $employees
     */
    private function departmentHeadUserIdForEmployee($employees, string $headEmail): ?int
    {
        return $employees->get(strtolower($headEmail))?->user_id;
    }
}
