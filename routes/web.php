<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCalendarController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentTypeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffboardingController;
use App\Http\Controllers\PdsController;
use App\Http\Controllers\EmployeeSearchController;
use App\Http\Controllers\EmployeeNfcController;

use App\Http\Controllers\TravelOrderApprovalController;
use App\Http\Controllers\TravelOrderController;
use App\Http\Controllers\TravelOrderTransportationController;
use App\Http\Controllers\AIController;
use App\Models\Employee;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


Route::get('secure-files/{folder}/{subfolder}/{filename}', function ($folder, $subfolder, $filename) {
    $user = Auth::user();
    if (!$user) {
        abort(403);
    }

    $normalizeDept = function (?string $departmentName): string {
        $normalized = strtolower(trim($departmentName ?? ''));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        return trim(preg_replace('/\s+/', ' ', $normalized));
    };

    $positionName = $user->positionName();
    $isHead = $positionName === 'head';
    $normalizedDept = $normalizeDept($user->employee?->department?->department ?? '');
    $isHrHead = $isHead && $normalizedDept === 'hr department';
    $isPresidentHead = $isHead && $normalizedDept === 'presidents office';

    if (in_array($folder, ['employee_documents', 'leave_attachments', 'travel_order_attachments'], true)) {
        $employeeId = (int) $subfolder;
        $employee = Employee::find($employeeId);
        if (!$employee) {
            abort(403);
        }

        if (!$user->isAdmin() && $employee->user && $employee->user->role === 'admin') {
            abort(403);
        }

        if (!$user->isAdmin() && !$isHrHead && !$isPresidentHead) {
            if ($isHead) {
                $userDeptId = $user->employee?->department_id;
                if (!$userDeptId || (int) $employee->department_id !== (int) $userDeptId) {
                    abort(403);
                }
            } else {
                $userEmployeeId = $user->employee?->id;
                if (!$userEmployeeId || (int) $userEmployeeId !== (int) $employee->id) {
                    abort(403);
                }
            }
        }
    }

    if ($folder === 'reports' && !$user->canViewData()) {
        abort(403);
    }

    $disk = Storage::disk('local');
    $path = $folder . '/' . $subfolder . '/' . $filename;

    if (!$disk->exists($path)) {
        abort(404);
    }

    $file = $disk->get($path);
    $type = $disk->mimeType($path);

    return response($file, 200)->header('Content-Type', $type);
})->where([
    'folder' => '[A-Za-z0-9_\-]+',
    'subfolder' => '[A-Za-z0-9_\-]+',
    'filename' => '.+'
])->name('storage.file')->middleware('auth');

Route::get('/', function () {
    if (Auth::check()) {
        return Auth::user()?->canAccessDashboard()
            ? redirect()->route('dashboard')
            : redirect()->route('attendance.history', [
                'period' => 'weekly',
                'date' => now()->toDateString(),
            ]);
    }

    // Use the full public job portal as the landing experience.
    return app(JobPostingController::class)->portal();
})->name('landing');

Route::get('/jobs', [JobPostingController::class, 'portal'])->name('jobs.portal');
Route::post('/jobs/{jobPosting}/apply', [JobPostingController::class, 'apply'])->name('jobs.apply');
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('api/employees/search', EmployeeSearchController::class)
        ->name('api.employees.search');
    Route::get('api/nfc/latest', [EmployeeNfcController::class, 'latestNfc'])
        ->middleware('throttle:60,1')
        ->name('api.nfc.latest');

    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::get('notifications/{notification}', [NotificationController::class, 'redirect'])
        ->whereUuid('notification')
        ->name('notifications.redirect');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')
        ->name('notifications.mark-read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.mark-all-read');

    Route::put('attendance/settings', [\App\Http\Controllers\AttendanceSettingsController::class, 'update'])
        ->name('attendance.settings.update');
    Route::resource('attendance', AttendanceController::class)
        ->only(['index', 'store', 'update'])
        ->where(['attendance' => '[0-9]+']);
    Route::get('attendance/live', [AttendanceController::class, 'getAttendance'])
        ->name('attendance.live');
    Route::post('attendance/rfid', [EmployeeNfcController::class, 'assign'])
        ->name('attendance.rfid.assign');
    Route::get('attendance/history', [AttendanceController::class, 'history'])
        ->name('attendance.history');
    Route::get('attendance/history/print', [AttendanceController::class, 'printHistory'])
        ->name('attendance.history.print');
    Route::get('attendance/calendar', [AttendanceCalendarController::class, 'index'])
        ->name('attendance.calendar');
    Route::get('attendance/calendar/feed', [AttendanceCalendarController::class, 'feed'])
        ->name('attendance.calendar.feed');
    Route::post('attendance/calendar/holidays', [AttendanceCalendarController::class, 'store'])
        ->name('attendance.calendar.store');
    Route::put('attendance/calendar/holidays/{holiday}', [AttendanceCalendarController::class, 'update'])
        ->name('attendance.calendar.update');
    Route::delete('attendance/calendar/holidays/{holiday}', [AttendanceCalendarController::class, 'destroy'])
        ->name('attendance.calendar.destroy');
    Route::get('attendance/weekly', [AttendanceController::class, 'weekly'])
        ->name('attendance.weekly');
    Route::resource('employees', EmployeeController::class)->except(['create', 'edit']);
    Route::post('employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])
        ->name('employees.reset-password');
    Route::get('offboarding', [OffboardingController::class, 'index'])
        ->name('offboarding.index');
    Route::post('offboarding', [OffboardingController::class, 'store'])
        ->name('offboarding.store');
    Route::get('offboarding/{offboarding}', [OffboardingController::class, 'show'])
        ->name('offboarding.show');
    Route::post('offboarding/{offboarding}/submit', [OffboardingController::class, 'submit'])
        ->name('offboarding.submit');
    Route::patch('offboarding/{offboarding}/items/{item}', [OffboardingController::class, 'updateItem'])
        ->name('offboarding.items.update');
    Route::post('offboarding/{offboarding}/finalize', [OffboardingController::class, 'finalize'])
        ->name('offboarding.finalize');
    Route::post('offboarding/{offboarding}/request-cancellation', [OffboardingController::class, 'requestCancellation'])
        ->name('offboarding.request-cancellation');
    Route::post('offboarding/{offboarding}/approve-cancellation', [OffboardingController::class, 'approveCancellation'])
        ->name('offboarding.approve-cancellation');
    Route::post('offboarding/{offboarding}/reject-cancellation', [OffboardingController::class, 'rejectCancellation'])
        ->name('offboarding.reject-cancellation');
    Route::post('offboarding/{offboarding}/reopen', [OffboardingController::class, 'reopen'])
        ->name('offboarding.reopen');
    Route::post('offboarding/{offboarding}/close', [OffboardingController::class, 'close'])
        ->name('offboarding.close');
    Route::post('offboarding/{offboarding}/remind', [OffboardingController::class, 'remind'])
        ->name('offboarding.remind');
    Route::get('offboarding/{offboarding}/export', [OffboardingController::class, 'export'])
        ->name('offboarding.export');
    Route::get('travel-orders', [TravelOrderController::class, 'index'])
        ->name('travel-orders.index');
    Route::get('travel-orders/create', [TravelOrderController::class, 'create'])
        ->name('travel-orders.create');
    Route::post('travel-orders', [TravelOrderController::class, 'store'])
        ->name('travel-orders.store');
    Route::get('travel-orders/transport-options', [TravelOrderTransportationController::class, 'index'])
        ->middleware('role:admin')
        ->name('travel-orders.transport-options.index');
    Route::post('travel-orders/transport-options', [TravelOrderTransportationController::class, 'store'])
        ->middleware('role:admin')
        ->name('travel-orders.transport-options.store');
    Route::patch('travel-orders/transport-options/{transportation}', [TravelOrderTransportationController::class, 'update'])
        ->middleware('role:admin')
        ->name('travel-orders.transport-options.update');
    Route::delete('travel-orders/transport-options/{transportation}', [TravelOrderTransportationController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('travel-orders.transport-options.destroy');
    Route::get('travel-orders/approvals', [TravelOrderApprovalController::class, 'approvalsIndex'])
        ->name('travel-orders.approvals');
    Route::get('travel-orders/{travel_order}', [TravelOrderController::class, 'show'])
        ->name('travel-orders.show');
    Route::patch('travel-orders/{travel_order}', [TravelOrderController::class, 'update'])
        ->name('travel-orders.update');
    Route::post('travel-orders/{travel_order}/submit', [TravelOrderController::class, 'submit'])
        ->name('travel-orders.submit');
    Route::post('travel-orders/{travel_order}/cancel', [TravelOrderController::class, 'cancel'])
        ->name('travel-orders.cancel');
    Route::post('travel-orders/{travel_order}/remind-pending', [TravelOrderController::class, 'remindPending'])
        ->name('travel-orders.remind-pending');
    Route::post('travel-orders/{travel_order}/complete', [TravelOrderController::class, 'complete'])
        ->name('travel-orders.complete');
    Route::get('travel-orders/{travel_order}/print', [TravelOrderController::class, 'print'])
        ->name('travel-orders.print');
    Route::post('travel-orders/{travel_order}/department-approve', [TravelOrderApprovalController::class, 'departmentApprove'])
        ->name('travel-orders.department-approve');
    Route::post('travel-orders/{travel_order}/department-reject', [TravelOrderApprovalController::class, 'departmentReject'])
        ->name('travel-orders.department-reject');
    Route::post('travel-orders/{travel_order}/hr-approve', [TravelOrderApprovalController::class, 'hrApprove'])
        ->name('travel-orders.hr-approve');
    Route::post('travel-orders/{travel_order}/hr-reject', [TravelOrderApprovalController::class, 'hrReject'])
        ->name('travel-orders.hr-reject');
    Route::post('travel-orders/{travel_order}/final-approve', [TravelOrderApprovalController::class, 'finalApprove'])
        ->name('travel-orders.final-approve');
    Route::post('travel-orders/{travel_order}/final-reject', [TravelOrderApprovalController::class, 'finalReject'])
        ->name('travel-orders.final-reject');
    Route::resource('employee-documents', EmployeeDocumentController::class)->except(['create', 'edit']);
    Route::post('employee-documents/{employeeDocument}/verify', [EmployeeDocumentController::class, 'verify'])
        ->name('employee-documents.verify');
    Route::post('employee-documents/{employeeDocument}/reupload', [EmployeeDocumentController::class, 'requestReupload'])
        ->name('employee-documents.reupload');
    Route::post('employee-documents/{employeeDocument}/remind-reupload', [EmployeeDocumentController::class, 'remindReupload'])
        ->name('employee-documents.remind-reupload');
    Route::post('employee-documents/{employeeDocument}/remind-expiry', [EmployeeDocumentController::class, 'remindExpiry'])
        ->name('employee-documents.remind-expiry');
    Route::resource('documents', DocumentController::class)->except(['create', 'edit']);
    Route::post('documents/categories', [DocumentController::class, 'storeCategory'])->name('documents.categories.store');
    Route::patch('documents/categories/{category}', [DocumentController::class, 'updateCategory'])->name('documents.categories.update');
    Route::delete('documents/categories/{category}', [DocumentController::class, 'destroyCategory'])->name('documents.categories.destroy');
    Route::post('documents/subcategories', [DocumentController::class, 'storeSubcategory'])->name('documents.subcategories.store');
    Route::patch('documents/subcategories/{subcategory}', [DocumentController::class, 'updateSubcategory'])->name('documents.subcategories.update');
    Route::delete('documents/subcategories/{subcategory}', [DocumentController::class, 'destroySubcategory'])->name('documents.subcategories.destroy');
    Route::resource('departments', DepartmentController::class)->except(['create', 'edit']);
    Route::post('department-types', [DepartmentTypeController::class, 'store'])->name('department-types.store');
    Route::patch('department-types/{departmentType}', [DepartmentTypeController::class, 'update'])->name('department-types.update');
    Route::delete('department-types/{departmentType}', [DepartmentTypeController::class, 'destroy'])->name('department-types.destroy');
    Route::post('departments/{department}/logo', [DepartmentController::class, 'updateLogo'])
        ->name('departments.logo.update');
    Route::get('positions/{position}/members', [PositionController::class, 'members'])
        ->name('positions.members');
    Route::resource('positions', PositionController::class)->except(['create', 'edit']);


    Route::get('profile', function () {
        return view('profile.show', ['user' => Auth::user()]);
    })->name('profile.show');

    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('employee-documents/download/{employeeDocument}', [EmployeeDocumentController::class, 'download'])
        ->name('employee-documents.download');

    Route::get('reports', [ReportController::class, 'index'])
        ->name('reports.index');

    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin')
        ->name('audit-logs.index');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('role:admin')
        ->name('audit-logs.export');
    Route::get('audit-logs/print', [AuditLogController::class, 'print'])
        ->middleware('role:admin')
        ->name('audit-logs.print');

    Route::get('departments/{department}/positions', [DepartmentController::class, 'positions'])
        ->middleware('auth')
        ->name('departments.positions');
    Route::get('departments/{department}/positions/{position}/employees', [DepartmentController::class, 'positionEmployees'])
        ->middleware('auth')
        ->name('departments.positions.employees');

    Route::get('leaves', [\App\Http\Controllers\LeaveRequestController::class, 'index'])
        ->name('leaves.index');
    Route::get('leaves/create', function () {
        return redirect()->route('leaves.index');
    })->name('leaves.create');
    Route::post('leaves', [\App\Http\Controllers\LeaveRequestController::class, 'store'])
        ->name('leaves.store');
    Route::get('leaves/{leave}/edit', function () {
        return redirect()->route('leaves.index');
    })->name('leaves.edit');
    Route::put('leaves/{leave}', [\App\Http\Controllers\LeaveRequestController::class, 'update'])
        ->name('leaves.update');
    Route::post('leaves/{leave}/cancel', [\App\Http\Controllers\LeaveRequestController::class, 'cancel'])
        ->name('leaves.cancel');

    Route::get('leaves/head', function () {
        return redirect()->route('leaves.index');
    })->name('leaves.head');
    Route::get('leaves/approvals', [\App\Http\Controllers\LeaveRequestController::class, 'approvalsIndex'])
        ->name('leaves.approvals');
    Route::post('leaves/{leave}/head-approve', [\App\Http\Controllers\LeaveRequestController::class, 'headApprove'])
        ->name('leaves.head.approve');
    Route::post('leaves/{leave}/head-decline', [\App\Http\Controllers\LeaveRequestController::class, 'headDecline'])
        ->name('leaves.head.decline');

    Route::post('leaves/{leave}/hr-approve', [\App\Http\Controllers\LeaveRequestController::class, 'hrApprove'])
        ->name('leaves.hr.approve');
    Route::post('leaves/{leave}/hr-decline', [\App\Http\Controllers\LeaveRequestController::class, 'hrDecline'])
        ->name('leaves.hr.decline');
    Route::post('leaves/{leave}/president-approve', [\App\Http\Controllers\LeaveRequestController::class, 'presidentApprove'])
        ->name('leaves.president.approve');
    Route::post('leaves/{leave}/president-decline', [\App\Http\Controllers\LeaveRequestController::class, 'presidentDecline'])
        ->name('leaves.president.decline');

    Route::get('leaves/president', function () {
        return redirect()->route('leaves.index');
    })->name('leaves.president');

    Route::resource('leave-types', \App\Http\Controllers\LeaveTypeController::class)
        ->middleware('role:admin')
        ->except(['show', 'create', 'edit']);
    Route::resource('leave-balances', \App\Http\Controllers\LeaveBalanceController::class)
        ->only(['index']);
    Route::post('leave-balances/settings', [\App\Http\Controllers\LeaveBalanceController::class, 'storeYearSetting'])
        ->name('leave-balances.settings.store');

    Route::post('/users/{user}/activate', [UserController::class, 'activate'])
        ->name('users.activate');

    Route::get('job-postings/departments/{department}/positions', [JobPostingController::class, 'positions'])
        ->name('job-postings.positions');
    Route::get('job-postings/{jobPosting}/edit-data', [JobPostingController::class, 'editData'])
        ->name('job-postings.edit-data');
    Route::put('job-postings', [JobPostingController::class, 'updateFallback'])
        ->name('job-postings.update-fallback');
    Route::get('job-postings/applicants', [JobPostingController::class, 'applicants'])
        ->name('job-postings.applicants');
    Route::post('job-postings/approvals/{approval}/approve', [RecruitmentApprovalController::class, 'approve'])
        ->name('job-postings.approvals.approve');
    Route::post('job-postings/approvals/{approval}/reject', [RecruitmentApprovalController::class, 'reject'])
        ->name('job-postings.approvals.reject');
    Route::post('job-postings/applicants/{applicant}/complete', [JobPostingController::class, 'completeApplicant'])
        ->name('job-postings.applicants.complete');
    Route::post('job-postings/applicants/{applicant}/activate', [JobPostingController::class, 'activateApplicant'])
        ->name('job-postings.applicants.activate');
    Route::post('job-postings/applicants/{applicant}/archive', [JobPostingController::class, 'archiveApplicant'])
        ->name('job-postings.applicants.archive');

    Route::resource('job-postings', JobPostingController::class)->except(['show', 'create', 'edit']);

    Route::get('pds', [PdsController::class, 'index'])->name('pds.index');
    Route::get('pds/{employee}', [PdsController::class, 'show'])->whereNumber('employee')->name('pds.show');
    Route::put('pds/{employee}/sections/{section}', [PdsController::class, 'saveSection'])->whereNumber('employee')->name('pds.sections.save');
    Route::post('pds/{employee}/submit', [PdsController::class, 'submit'])->whereNumber('employee')->name('pds.submit');
    Route::post('pds/{employee}/verify', [PdsController::class, 'verify'])->whereNumber('employee')->name('pds.verify');
    Route::post('pds/{employee}/request-correction', [PdsController::class, 'requestCorrection'])->whereNumber('employee')->name('pds.request-correction');
    Route::get('pds/{employee}/print', [PdsController::class, 'print'])->whereNumber('employee')->name('pds.print');

    Route::get('/ai-test', [AIController::class, 'test'])->name('ai.test');
    Route::post('/ai/chat', [AIController::class, 'chat'])->name('ai.chat');
});

Route::post('attendance/self', [AttendanceController::class, 'store'])
    ->name('attendance.self')
    ->middleware(['auth','role:employee']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

require __DIR__.'/auth.php';


