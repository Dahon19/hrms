<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\SpmsEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardActivityService
{
    public function build(User $user): array
    {
        return [
            'activities' => $this->buildActivities($user),
            'notifications' => $this->buildNotifications($user),
        ];
    }

    private function buildActivities(User $user): array
    {
        return collect()
            ->merge($this->leaveActivities($user))
            ->merge($this->documentActivities($user))
            ->merge($this->recruitmentActivities($user))
            ->merge($this->spmsActivities($user))
            ->sortByDesc('timestamp')
            ->take(10)
            ->map(function (array $activity) {
                unset($activity['timestamp']);
                return $activity;
            })
            ->values()
            ->all();
    }

    private function buildNotifications(User $user): array
    {
        return $user->notifications()
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (DatabaseNotification $notification) {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'title' => (string) ($data['title'] ?? 'Notification'),
                    'message' => (string) ($data['message'] ?? ''),
                    'date' => optional($notification->created_at)->format('M d, Y h:i A'),
                    'is_unread' => $notification->read_at === null,
                    'href' => route('notifications.redirect', ['notification' => (string) $notification->id]),
                ];
            })
            ->all();
    }

    private function leaveActivities(User $user): Collection
    {
        return $this->visibleLeaveQuery($user)
            ->with(['employee', 'leaveType'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(function (LeaveRequest $leave) {
                $employeeName = trim(($leave->employee?->first_name ?? '') . ' ' . ($leave->employee?->last_name ?? ''));

                return [
                    'title' => 'Leave ' . $leave->status,
                    'message' => ($employeeName !== '' ? $employeeName : 'Employee') . ' • ' . ($leave->leaveType?->name ?? 'Leave'),
                    'timestamp' => optional($leave->updated_at)->timestamp ?? 0,
                    'date' => optional($leave->updated_at)->format('M d, Y h:i A'),
                    'href' => route('leaves.index'),
                ];
            });
    }

    private function documentActivities(User $user): Collection
    {
        if (!Schema::hasTable('employee_documents')) {
            return collect();
        }

        return $this->visibleDocumentQuery($user)
            ->with('employee')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function (EmployeeDocument $document) use ($user) {
                $employeeName = trim(($document->employee?->first_name ?? '') . ' ' . ($document->employee?->last_name ?? ''));
                $href = $user->employee?->id
                    ? route('employee-documents.index', ['employee_id' => $user->employee->id])
                    : route('employee-documents.index');

                return [
                    'title' => 'Document ' . ucfirst((string) $document->status),
                    'message' => ($document->document_name ?? 'Employee document') . ' • ' . ($employeeName !== '' ? $employeeName : 'Employee'),
                    'timestamp' => optional($document->updated_at)->timestamp ?? 0,
                    'date' => optional($document->updated_at)->format('M d, Y h:i A'),
                    'href' => $href,
                ];
            });
    }

    private function recruitmentActivities(User $user): Collection
    {
        if (!$user->canViewData() || !Schema::hasTable('applicants')) {
            return collect();
        }

        return Applicant::query()
            ->with('jobPosting')
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(function (Applicant $applicant) {
                return [
                    'title' => 'Application Submitted',
                    'message' => $applicant->full_name . ' • ' . ($applicant->jobPosting?->title ?? 'Job posting'),
                    'timestamp' => optional($applicant->created_at)->timestamp ?? 0,
                    'date' => optional($applicant->created_at)->format('M d, Y h:i A'),
                    'href' => route('job-postings.applicants'),
                ];
            });
    }

    private function spmsActivities(User $user): Collection
    {
        if (!Schema::hasTable('spms_evaluations')) {
            return collect();
        }

        $query = SpmsEvaluation::query()
            ->with('employee')
            ->latest('updated_at')
            ->limit(3);

        if (!$user->canViewData()) {
            $query->where('employee_id', $user->employee?->id);
        }

        return $query->get()->map(function (SpmsEvaluation $evaluation) use ($user) {
            $employeeName = trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? ''));

            return [
                'title' => 'SPMS ' . ucfirst((string) $evaluation->status),
                'message' => ($employeeName !== '' ? $employeeName : 'Employee') . ' evaluation updated',
                'timestamp' => optional($evaluation->updated_at)->timestamp ?? 0,
                'date' => optional($evaluation->updated_at)->format('M d, Y h:i A'),
                'href' => $user->canViewData() ? route('spms.evaluations.index') : route('spms.my-performance'),
            ];
        });
    }

    private function visibleLeaveQuery(User $user): Builder
    {
        return LeaveRequest::query()
            ->when(!$user->isAdmin(), function (Builder $query) {
                $query->whereHas('employee.user', function (Builder $userQuery) {
                    $userQuery->where('role', '!=', 'admin');
                });
            })
            ->when(!$user->canViewData(), function (Builder $query) use ($user) {
                $query->where('employee_id', $user->employee?->id);
            })
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function visibleDocumentQuery(User $user): Builder
    {
        return EmployeeDocument::query()
            ->when(!$user->isAdmin(), function (Builder $query) {
                $query->whereHas('employee.user', function (Builder $userQuery) {
                    $userQuery->where('role', '!=', 'admin');
                });
            })
            ->when(!$user->canViewData(), function (Builder $query) use ($user) {
                $query->where('employee_id', $user->employee?->id);
            })
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function scopeToDepartment(User $user): bool
    {
        if (!AccessControl::isDepartmentLeader($user)) {
            return false;
        }

        if (AccessControl::isHrDepartmentLeader($user) || AccessControl::isPresidentDepartmentLeader($user)) {
            return false;
        }

        return (bool) $user->employee?->department_id;
    }
}
