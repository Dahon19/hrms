<?php

namespace App\Domain\TravelOrders\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\TravelOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TravelOrderAttendanceService
{
    public function approvedDateMapForEmployees(array $employeeIds, string $startDate, string $endDate): array
    {
        if (empty($employeeIds) || !TravelOrder::tablesAvailable()) {
            return [];
        }

        $orders = TravelOrder::query()
            ->with(['employee.department'])
            ->approvedForAttendance()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date_from', '<=', $endDate)
            ->whereDate('date_to', '>=', $startDate)
            ->get();

        $map = [];
        foreach ($orders as $order) {
            $rangeStart = Carbon::parse($startDate);
            $rangeEnd = Carbon::parse($endDate);
            $period = CarbonPeriod::create(
                Carbon::parse($order->date_from)->max($rangeStart),
                Carbon::parse($order->date_to)->min($rangeEnd)
            );

            foreach ($period as $date) {
                $map[$order->employee_id][$date->toDateString()] = $order;
            }
        }

        return $map;
    }

    public function mergeTravelRows(Collection $attendanceRows, Collection $employees, string $startDate, string $endDate): Collection
    {
        $approvedMap = $this->approvedDateMapForEmployees($employees->pluck('id')->all(), $startDate, $endDate);
        $rows = $attendanceRows->map(function ($row) use ($approvedMap) {
            $employeeId = (int) $row->employee_id;
            $date = Carbon::parse($row->date)->toDateString();
            if (isset($approvedMap[$employeeId][$date])) {
                $row->status = 'official_business';
                $row->setRelation('travelOrder', $approvedMap[$employeeId][$date]);
            }

            return $row;
        });

        foreach ($employees as $employee) {
            $employeeMap = $approvedMap[(int) $employee->id] ?? [];
            foreach ($employeeMap as $date => $travelOrder) {
                $exists = $rows->contains(fn ($row) => (int) $row->employee_id === (int) $employee->id && Carbon::parse($row->date)->toDateString() === $date);
                if ($exists) {
                    continue;
                }

                $synthetic = new Attendance([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => 'official_business',
                ]);
                $synthetic->exists = false;
                $synthetic->setRelation('employee', $employee);
                $synthetic->setRelation('travelOrder', $travelOrder);
                $rows->push($synthetic);
            }
        }

        return $rows
            ->sortByDesc(fn ($row) => Carbon::parse($row->date)->timestamp)
            ->values();
    }

    public function paginateMergedRows(Collection $rows, int $perPage, int $currentPage, array $options = []): LengthAwarePaginator
    {
        $items = $rows->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $currentPage,
            $options
        );
    }

    public function summarize(Collection $attendanceRows): array
    {
        $summaryByEmployee = $attendanceRows
            ->groupBy('employee_id')
            ->map(function (Collection $rows) {
                $employee = $rows->first()?->employee;

                return (object) [
                    'employee_id' => $employee?->id,
                    'employee' => $employee,
                    'present_days' => $rows->where('status', 'present')->count(),
                    'absent_days' => $rows->where('status', 'absent')->count(),
                    'late_days' => $rows->where('status', 'late')->count(),
                    'official_business_days' => $rows->where('status', 'official_business')->count(),
                    'total_logs' => $rows->count(),
                ];
            })
            ->sortByDesc('present_days')
            ->values();

        return [
            'summary' => $summaryByEmployee,
            'totals' => [
                'employees' => $summaryByEmployee->count(),
                'present_days' => (int) $summaryByEmployee->sum('present_days'),
                'absent_days' => (int) $summaryByEmployee->sum('absent_days'),
                'late_days' => (int) $summaryByEmployee->sum('late_days'),
                'official_business_days' => (int) $summaryByEmployee->sum('official_business_days'),
            ],
        ];
    }

    public function approvedTravelWorkDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        $map = $this->approvedDateMapForEmployees([$employee->id], $start->toDateString(), $end->toDateString());
        $days = 0;

        foreach (array_keys($map[$employee->id] ?? []) as $date) {
            if (!Carbon::parse($date)->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}
