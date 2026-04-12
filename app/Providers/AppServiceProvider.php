<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\AttendanceAnomaly;
use App\Models\AttendanceKpi;
use App\Models\AttendanceMonthlyScore;
use App\Models\Department;
use App\Models\DepartmentMetric;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentSubcategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeNfc;
use App\Models\EmployeePosition;
use App\Models\EligibilityCache;
use App\Models\IndividualDevelopmentPlan;
use App\Models\PdsChild;
use App\Models\PdsCivilServiceEligibility;
use App\Models\PdsEducation;
use App\Models\PdsFamilyBackground;
use App\Models\PdsOtherInfo;
use App\Models\PdsPersonalInfo;
use App\Models\PdsProfile;
use App\Models\PdsTraining;
use App\Models\PdsVoluntaryWork;
use App\Models\PdsWorkExperience;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ClearanceItem;
use App\Models\OffboardingRecord;
use App\Models\Position;
use App\Models\PerformanceReview;
use App\Models\RewardRecord;
use App\Models\RewardTitle;
use App\Models\ReportRun;
use App\Models\SpmsCriterion;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use App\Models\SpmsEvaluationDetail;
use App\Models\TravelOrder;
use App\Models\TravelOrderAttachment;
use App\Models\TravelOrderTransportation;
use App\Models\User;
use App\Policies\OffboardingRecordPolicy;
use App\Policies\PdsProfilePolicy;
use App\Policies\TravelOrderPolicy;
use App\Observers\AuditObserver;
use App\Services\AccessControl;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFour();

        Gate::before(function ($user, string $ability) {
            if ($user->isAdmin() && !in_array($ability, ['approveItem', 'requestCancellation', 'view-attendance-kpi', 'manage-attendance-kpi'], true)) {
                return true;
            }

            return null;
        });

        Gate::define('view-employees', fn ($user) => $user->canViewData() || AccessControl::isDepartmentSupport($user) || AccessControl::isHrStaff($user));
        Gate::define('view-dashboard', fn (User $user) => AccessControl::canAccessDashboard($user));
        Gate::define('manage-employees', fn ($user) => $user->isAdmin());
        Gate::define('view-departments', fn ($user) => $user->canViewData() || AccessControl::isOrgChartViewer($user));
        Gate::define('manage-departments', fn ($user) => $user->isAdmin());
        Gate::define('view-positions', fn ($user) => $user->canViewData());
        Gate::define('manage-positions', fn ($user) => $user->isAdmin());
        Gate::define('view-documents', fn ($user) => $user->canViewData());
        Gate::define('manage-documents', fn ($user) => $user->isAdmin());
        Gate::define('manage-leave-types', fn ($user) => $user->isAdmin());
        Gate::define('manage-travel-order-transportations', fn ($user) => $user->isAdmin());
        Gate::define('manage-leave-balances', fn ($user) => $user->isAdmin());
        Gate::define('view-leave-balances', fn ($user) => $user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isPresidentHead($user));
        Gate::define('view-employee-documents', fn ($user, Employee $employee) => AccessControl::canViewEmployeeDocuments($user, $employee));
        $canViewAttendanceRecords = fn (User $user) => $user->canViewData() || AccessControl::isHrStaff($user) || (bool) $user->employee;
        $canViewAttendanceCalendar = fn (User $user) => $user->canViewData() || AccessControl::isHrStaff($user);

        Gate::define('view-attendance', fn ($user) => $user->canViewData() || (bool) $user->employee);
        Gate::define('view-attendance-records', $canViewAttendanceRecords);
        Gate::define('view-attendance-calendar', $canViewAttendanceCalendar);
        Gate::define('manage-attendance', fn ($user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-attendance-kpi', fn (User $user) => AccessControl::isHrStaff($user));
        Gate::define('manage-attendance-kpi', fn (User $user) => AccessControl::isHrStaff($user));
        Gate::policy(PdsProfile::class, PdsProfilePolicy::class);
        Gate::define('manage-pds', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-pds', function (User $user, ?Employee $employee = null) {
            if ($user->isAdmin() || AccessControl::isHrHead($user)) {
                return true;
            }

            if (!$employee) {
                return (bool) $user->employee;
            }

            return (int) ($user->employee?->id ?? 0) === (int) $employee->id;
        });
        Gate::define('verify-pds', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('override-pds-lock', fn (User $user) => $user->isAdmin());
        Gate::define('view-rewards', fn (User $user) => (bool) $user->employee || $user->canViewData());
        Gate::define('manage-rewards', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-eligibility-list', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-eligibility', fn (User $user) => (bool) $user->employee || $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-spms', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isHeadOrDean($user) || (bool) $user->employee);
        Gate::define('evaluate-spms', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isHeadOrDean($user));
        Gate::define('manage-spms', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::define('view-idp', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user) || (bool) $user->employee);
        Gate::define('manage-idp', fn (User $user) => $user->isAdmin() || AccessControl::isHrHead($user));
        Gate::policy(OffboardingRecord::class, OffboardingRecordPolicy::class);
        Gate::policy(ClearanceItem::class, OffboardingRecordPolicy::class);
        Gate::policy(TravelOrder::class, TravelOrderPolicy::class);

        $observer = new AuditObserver();
        User::observe($observer);
        Employee::observe($observer);
        EmployeePosition::observe($observer);
        EmployeeNfc::observe($observer);
        EmployeeDocument::observe($observer);
        LeaveRequest::observe($observer);
        Department::observe($observer);
        DepartmentMetric::observe($observer);
        Position::observe($observer);
        LeaveType::observe($observer);
        LeaveBalance::observe($observer);
        DocumentCategory::observe($observer);
        DocumentSubcategory::observe($observer);
        Document::observe($observer);
        Attendance::observe($observer);
        AttendanceAnomaly::observe($observer);
        AttendanceKpi::observe($observer);
        AttendanceMonthlyScore::observe($observer);
        ReportRun::observe($observer);
        PdsProfile::observe($observer);
        PdsPersonalInfo::observe($observer);
        PdsFamilyBackground::observe($observer);
        PdsChild::observe($observer);
        PdsEducation::observe($observer);
        PdsCivilServiceEligibility::observe($observer);
        PdsWorkExperience::observe($observer);
        PdsVoluntaryWork::observe($observer);
        PdsTraining::observe($observer);
        PdsOtherInfo::observe($observer);
        PerformanceReview::observe($observer);
        RewardRecord::observe($observer);
        RewardTitle::observe($observer);
        EligibilityCache::observe($observer);
        IndividualDevelopmentPlan::observe($observer);
        OffboardingRecord::observe($observer);
        ClearanceItem::observe($observer);
        TravelOrder::observe($observer);
        TravelOrderAttachment::observe($observer);
        TravelOrderTransportation::observe($observer);
        SpmsCycle::observe($observer);
        SpmsCriterion::observe($observer);
        SpmsEvaluation::observe($observer);
        SpmsEvaluationDetail::observe($observer);
    }
}


