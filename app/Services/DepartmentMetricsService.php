<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\DepartmentMetric;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class DepartmentMetricsService
{
    public function generateForDate(Carbon $date): int
    {
        $metricDate = $date->toDateString();
        $departments = Department::all();
        $totalDocs = Document::count();
        $created = 0;

        foreach ($departments as $department) {
            $employeeIds = Employee::where('department_id', $department->id)->pluck('id');
            $totalEmployees = $employeeIds->count();

            $attendanceCount = Attendance::whereIn('employee_id', $employeeIds)
                ->whereDate('date', $metricDate)
                ->count();

            $attendanceRate = $totalEmployees > 0
                ? round(($attendanceCount / $totalEmployees) * 100, 2)
                : 0;

            $leaveRequests = LeaveRequest::whereIn('employee_id', $employeeIds)
                ->whereDate('created_at', $metricDate)
                ->count();

            $leaveApproved = LeaveRequest::whereIn('employee_id', $employeeIds)
                ->whereDate('updated_at', $metricDate)
                ->where('status', 'HR Approved')
                ->count();

            $submittedDocs = EmployeeDocument::whereIn('employee_id', $employeeIds)
                ->whereNotNull('file_path')
                ->count();

            $documentComplianceRate = ($totalEmployees > 0 && $totalDocs > 0)
                ? round(($submittedDocs / ($totalEmployees * $totalDocs)) * 100, 2)
                : 0;

            DepartmentMetric::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'metric_date' => $metricDate,
                ],
                [
                    'total_employees' => $totalEmployees,
                    'attendance_rate' => $attendanceRate,
                    'leave_requests' => $leaveRequests,
                    'leave_approved' => $leaveApproved,
                    'document_compliance_rate' => $documentComplianceRate,
                ]
            );

            $created++;
        }

        return $created;
    }
}
