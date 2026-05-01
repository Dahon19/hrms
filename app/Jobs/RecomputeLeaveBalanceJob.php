<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecomputeLeaveBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $employeeId,
        public readonly int $year,
    ) {}

    public function handle(): void
    {
        $employee = Employee::find($this->employeeId);
        if (! $employee) {
            return;
        }

        // Aggregate approved leave days per type for the year
        $used = LeaveRequest::query()
            ->where('employee_id', $this->employeeId)
            ->where('status', 'HR Approved')
            ->whereYear('start_date', $this->year)
            ->select('leave_type_id', DB::raw('SUM(DATEDIFF(end_date, start_date) + 1) as days_used'))
            ->groupBy('leave_type_id')
            ->pluck('days_used', 'leave_type_id');

        foreach ($used as $leaveTypeId => $daysUsed) {
            LeaveBalance::query()
                ->where('employee_id', $this->employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $this->year)
                ->update(['used_days' => $daysUsed]);
        }

        Log::info('LeaveBalance recomputed', [
            'employee_id' => $this->employeeId,
            'year'        => $this->year,
        ]);
    }
}
