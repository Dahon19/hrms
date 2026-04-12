<?php

namespace App\Services;

use App\Models\AttendanceAnomaly;
use App\Models\DepartmentMetric;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\ReportRun;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\AccessControl;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class ReportExportService
{
    private const TYPES = [
        'department-metrics',
        'attendance-anomalies',
        'leave-summary',
        'document-expiry',
        'travel-orders',
    ];

    public function export(string $type, ?User $user = null): ReportRun
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported report type.');
        }

        $run = ReportRun::create([
            'type' => $type,
            'status' => 'running',
            'run_at' => now(),
        ]);

        try {
            $csv = $this->buildCsv($type, $user);
            $filename = $type . '-' . now()->format('Ymd_His') . '.csv';
            $path = 'reports/exports/' . $filename;
            Storage::disk('local')->put($path, $csv);

            $run->update([
                'status' => 'completed',
                'file_path' => $path,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'metadata' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        return $run;
    }

    private function buildCsv(string $type, ?User $user = null): string
    {
        $handle = fopen('php://temp', 'r+');
        $excludeAdmin = $user && !$user->isAdmin() && $user->isReadOnlyStaff();
        $scopeToDepartment = $user && AccessControl::isDepartmentLeader($user)
            && !AccessControl::isHrDepartmentLeader($user)
            && !AccessControl::isPresidentDepartmentLeader($user)
            && $user->employee?->department_id;
        $departmentId = $user?->employee?->department_id;

        if ($type === 'department-metrics') {
            fputcsv($handle, [
                'Date',
                'Department',
                'Total Employees',
                'Attendance Rate (%)',
                'Leave Requests',
                'Leave Approved',
                'Document Compliance (%)',
            ]);

            $metrics = DepartmentMetric::with('department')
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->orderByDesc('metric_date')
                ->get();

            foreach ($metrics as $metric) {
                fputcsv($handle, [
                    $metric->metric_date?->toDateString(),
                    $metric->department?->department,
                    $metric->total_employees,
                    $metric->attendance_rate,
                    $metric->leave_requests,
                    $metric->leave_approved,
                    $metric->document_compliance_rate,
                ]);
            }
        }

        if ($type === 'attendance-anomalies') {
            fputcsv($handle, [
                'Date',
                'Employee',
                'Type',
                'Minutes',
            ]);

            $anomalies = AttendanceAnomaly::with('employee')
                ->orderByDesc('date');
            if ($scopeToDepartment) {
                $anomalies->whereHas('employee', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                });
            }
            if ($excludeAdmin) {
                $anomalies->whereHas('employee.user', function ($query) {
                    $query->where('role', '!=', 'admin');
                });
            }
            $anomalies = $anomalies->get();

            foreach ($anomalies as $anomaly) {
                $employeeName = trim(($anomaly->employee->first_name ?? '') . ' ' . ($anomaly->employee->last_name ?? ''));
                fputcsv($handle, [
                    $anomaly->date?->toDateString(),
                    $employeeName,
                    $anomaly->type,
                    $anomaly->minutes,
                ]);
            }
        }

        if ($type === 'leave-summary') {
            fputcsv($handle, [
                'Request ID',
                'Employee',
                'Leave Type',
                'Start Date',
                'End Date',
                'Status',
                'Requested At',
            ]);

            $leaves = LeaveRequest::with(['employee', 'leaveType'])
                ->orderByDesc('created_at');
            if ($scopeToDepartment) {
                $leaves->whereHas('employee', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                });
            }
            if ($excludeAdmin) {
                $leaves->whereHas('employee.user', function ($query) {
                    $query->where('role', '!=', 'admin');
                });
            }
            $leaves = $leaves->get();

            foreach ($leaves as $leave) {
                $employeeName = trim(($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''));
                fputcsv($handle, [
                    $leave->id,
                    $employeeName,
                    $leave->leaveType?->name,
                    $leave->start_date?->toDateString(),
                    $leave->end_date?->toDateString(),
                    $leave->status,
                    $leave->created_at?->toDateTimeString(),
                ]);
            }
        }

        if ($type === 'document-expiry') {
            fputcsv($handle, [
                'Employee',
                'Document',
                'Expires At',
                'Status',
            ]);

            $documents = EmployeeDocument::with(['employee', 'documents'])
                ->whereNotNull('expires_at')
                ->orderBy('expires_at');
            if ($scopeToDepartment) {
                $documents->whereHas('employee', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                });
            }
            if ($excludeAdmin) {
                $documents->whereHas('employee.user', function ($query) {
                    $query->where('role', '!=', 'admin');
                });
            }
            $documents = $documents->get();

            foreach ($documents as $document) {
                $employeeName = trim(($document->employee->first_name ?? '') . ' ' . ($document->employee->last_name ?? ''));
                fputcsv($handle, [
                    $employeeName,
                    $document->documents?->document,
                    $document->expires_at?->toDateString(),
                    $document->status,
                ]);
            }
        }

        if ($type === 'travel-orders') {
            fputcsv($handle, [
                'Travel Order ID',
                'Employee',
                'Department',
                'Position',
                'Destination',
                'Purpose',
                'Date From',
                'Date To',
                'Status',
                'Submitted At',
                'Approved At',
                'Completed At',
                'Cancelled At',
            ]);

            $travelOrders = TravelOrder::with(['employee.department', 'position'])
                ->orderByDesc('date_from');
            if ($scopeToDepartment) {
                $travelOrders->where('department_id', $departmentId);
            }
            if ($excludeAdmin) {
                $travelOrders->whereHas('employee.user', function ($query) {
                    $query->where('role', '!=', 'admin');
                });
            }
            $travelOrders = $travelOrders->get();

            foreach ($travelOrders as $travelOrder) {
                $employeeName = trim(($travelOrder->employee->first_name ?? '') . ' ' . ($travelOrder->employee->last_name ?? ''));
                fputcsv($handle, [
                    $travelOrder->id,
                    $employeeName,
                    $travelOrder->employee?->department?->department,
                    $travelOrder->position?->position,
                    $travelOrder->destination,
                    preg_replace('/\s+/', ' ', (string) $travelOrder->purpose),
                    $travelOrder->date_from?->toDateString(),
                    $travelOrder->date_to?->toDateString(),
                    $travelOrder->status,
                    $travelOrder->submitted_at?->toDateTimeString(),
                    $travelOrder->approved_at?->toDateTimeString(),
                    $travelOrder->completed_at?->toDateTimeString(),
                    $travelOrder->cancelled_at?->toDateTimeString(),
                ]);
            }
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents ?: '';
    }
}
