<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceCalendarService
{
    public function approvedLeaveDateMapForEmployees(array $employeeIds, string $startDate, string $endDate): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $leaves = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'HR Approved')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get();

        $map = [];
        foreach ($leaves as $leave) {
            $rangeStart = Carbon::parse($startDate);
            $rangeEnd = Carbon::parse($endDate);
            $period = CarbonPeriod::create(
                Carbon::parse($leave->start_date)->max($rangeStart),
                Carbon::parse($leave->end_date)->min($rangeEnd)
            );

            foreach ($period as $date) {
                $map[$leave->employee_id][$date->toDateString()] = $leave;
            }
        }

        return $map;
    }

    public function applyLeaveRows(Collection $attendanceRows, Collection $employees, string $startDate, string $endDate): Collection
    {
        $approvedMap = $this->approvedLeaveDateMapForEmployees($employees->pluck('id')->all(), $startDate, $endDate);
        if ($approvedMap === []) {
            return $attendanceRows;
        }

        $rows = $attendanceRows->map(function ($row) use ($approvedMap) {
            $employeeId = (int) $row->employee_id;
            $date = Carbon::parse($row->date)->toDateString();

            if (!isset($approvedMap[$employeeId][$date])) {
                return $row;
            }

            $hasAttendanceLogs = filled($row->morning_time_in)
                || filled($row->morning_time_out)
                || filled($row->afternoon_time_in)
                || filled($row->afternoon_time_out);

            if (!$hasAttendanceLogs && !in_array((string) $row->status, ['official_business', 'holiday'], true)) {
                $row->status = 'excused';
                $row->setRelation('leaveRequest', $approvedMap[$employeeId][$date]);
            }

            return $row;
        });

        foreach ($employees as $employee) {
            $employeeMap = $approvedMap[(int) $employee->id] ?? [];
            foreach ($employeeMap as $date => $leaveRequest) {
                $exists = $rows->contains(
                    fn ($row) => (int) $row->employee_id === (int) $employee->id
                        && Carbon::parse($row->date)->toDateString() === $date
                );

                if ($exists) {
                    continue;
                }

                $synthetic = new Attendance([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => 'excused',
                ]);
                $synthetic->exists = false;
                $synthetic->setRelation('employee', $employee);
                $synthetic->setRelation('leaveRequest', $leaveRequest);
                $rows->push($synthetic);
            }
        }

        return $rows
            ->sortByDesc(fn ($row) => Carbon::parse($row->date)->timestamp)
            ->values();
    }

    public function holidayDateMap(string $startDate, string $endDate): array
    {
        if (!Holiday::tableAvailable()) {
            return [];
        }

        return Holiday::query()
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString())
            ->all();
    }

    public function applyHolidayRows(Collection $attendanceRows, Collection $employees, string $startDate, string $endDate): Collection
    {
        $holidayMap = $this->holidayDateMap($startDate, $endDate);
        if ($holidayMap === []) {
            return $attendanceRows;
        }

        $rows = $attendanceRows->map(function ($row) use ($holidayMap) {
            $date = Carbon::parse($row->date)->toDateString();
            if (!isset($holidayMap[$date])) {
                return $row;
            }

            $row->status = 'holiday';
            $row->setRelation('holiday', $holidayMap[$date]);

            return $row;
        });

        foreach ($employees as $employee) {
            foreach ($holidayMap as $date => $holiday) {
                $exists = $rows->contains(
                    fn ($row) => (int) $row->employee_id === (int) $employee->id
                        && Carbon::parse($row->date)->toDateString() === $date
                );

                if ($exists) {
                    continue;
                }

                $synthetic = new Attendance([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => 'holiday',
                ]);
                $synthetic->exists = false;
                $synthetic->setRelation('employee', $employee);
                $synthetic->setRelation('holiday', $holiday);
                $rows->push($synthetic);
            }
        }

        return $rows
            ->sortByDesc(fn ($row) => Carbon::parse($row->date)->timestamp)
            ->values();
    }

    public function countHolidayWorkDays(Carbon $start, Carbon $end): int
    {
        $holidayMap = $this->holidayDateMap($start->toDateString(), $end->toDateString());

        return collect($holidayMap)
            ->filter(fn (Holiday $holiday) => !$holiday->holiday_date->isWeekend())
            ->count();
    }

    public function approvedLeaveWorkDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        $map = $this->approvedLeaveDateMapForEmployees([$employee->id], $start->toDateString(), $end->toDateString());
        $days = 0;

        foreach (array_keys($map[$employee->id] ?? []) as $date) {
            if (!Carbon::parse($date)->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }

    public function buildCalendarPayload(User $user, Carbon $month): array
    {
        return $this->buildRangePayload(
            $user,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth()
        );
    }

    public function buildRangePayload(User $user, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeEnd->copy()->endOfDay();

        $employeeScope = Employee::query()
            ->with(['department', 'user'])
            ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));

        if (!$user->isAdmin() && !AccessControl::isHrHead($user) && AccessControl::isDepartmentLeader($user)) {
            $employeeScope->where('department_id', $user->employee?->department_id);
        } elseif (!$user->isAdmin() && !$user->canViewData()) {
            $employeeScope->where('id', $user->employee?->id ?? 0);
        }

        $employees = $employeeScope->get()->keyBy('id');
        $employeeIds = $employees->keys()->all();

        $holidays = Holiday::tableAvailable()
            ? Holiday::query()
                ->whereBetween('holiday_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->orderBy('holiday_date')
                ->get()
            : collect();

        $leaveQuery = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->orderBy('start_date');

        $approvedLeaves = (clone $leaveQuery)
            ->where('status', 'HR Approved')
            ->get();

        $pendingLeaves = (clone $leaveQuery)
            ->whereIn('status', ['Pending', 'Approved'])
            ->get();

        $events = collect();
        $dateDetails = [];

        foreach ($holidays as $holiday) {
            $date = $holiday->holiday_date->toDateString();
            $events->push([
                'id' => 'holiday-' . $holiday->id,
                'title' => 'Holiday: ' . $holiday->name,
                'start' => $date,
                'allDay' => true,
                'backgroundColor' => '#b91c1c',
                'borderColor' => '#991b1b',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'category' => 'holiday',
                    'holidayId' => $holiday->id,
                    'holidayName' => $holiday->name,
                    'holidayType' => $holiday->type,
                    'remarks' => $holiday->remarks,
                ],
            ]);

            $dateDetails[$date]['holiday'] = [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'type' => $holiday->type,
                'remarks' => $holiday->remarks,
            ];
        }

        $pushLeaveDetails = function (LeaveRequest $leave, string $bucket) use (&$events, &$dateDetails, $rangeStart, $rangeEnd): void {
            $employeeName = trim(($leave->employee?->first_name ?? '') . ' ' . ($leave->employee?->last_name ?? ''));
            $typeName = $leave->leaveType?->name ?? 'Leave';
            $eventColor = $bucket === 'on_leave' ? '#0f766e' : '#b45309';
            $borderColor = $bucket === 'on_leave' ? '#115e59' : '#92400e';
            $statusLabel = $bucket === 'on_leave' ? 'On Leave' : 'Pending Leave';
            $start = Carbon::parse($leave->start_date)->max($rangeStart);
            $end = Carbon::parse($leave->end_date)->min($rangeEnd);

            $events->push([
                'id' => strtolower($bucket) . '-' . $leave->id,
                'title' => $employeeName . ' - ' . $typeName,
                'start' => $start->toDateString(),
                'end' => $end->copy()->addDay()->toDateString(),
                'allDay' => true,
                'backgroundColor' => $eventColor,
                'borderColor' => $borderColor,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'category' => $bucket,
                    'statusLabel' => $statusLabel,
                    'employee' => $employeeName,
                    'department' => $leave->employee?->department?->department ?? '-',
                    'type' => $typeName,
                ],
            ]);

            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $dateKey = $date->toDateString();
                $dateDetails[$dateKey][$bucket] ??= [];
                $dateDetails[$dateKey][$bucket][] = [
                    'employee' => $employeeName,
                    'department' => $leave->employee?->department?->department ?? '-',
                    'type' => $typeName,
                    'status' => $leave->status,
                    'start' => $leave->start_date?->format('M d, Y'),
                    'end' => $leave->end_date?->format('M d, Y'),
                ];
            }
        };

        foreach ($approvedLeaves as $leave) {
            $pushLeaveDetails($leave, 'on_leave');
        }

        foreach ($pendingLeaves as $leave) {
            $pushLeaveDetails($leave, 'pending_leave');
        }

        $selectedDate = now()->betweenIncluded($rangeStart, $rangeEnd)
            ? now()->toDateString()
            : $rangeStart->copy()->toDateString();

        return [
            'events' => $events->values(),
            'dateDetails' => $dateDetails,
            'selectedDate' => $selectedDate,
            'holidayCount' => $holidays->count(),
            'onLeaveCount' => $approvedLeaves->count(),
            'pendingLeaveCount' => $pendingLeaves->count(),
        ];
    }
}
