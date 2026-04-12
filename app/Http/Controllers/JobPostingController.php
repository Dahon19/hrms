<?php

namespace App\Http\Controllers;

use App\Events\JobApplicationSubmitted;
use App\Models\JobPosting;
use App\Models\Department;
use App\Models\Position;
use App\Models\EmployeePosition;
use App\Models\Employee;
use App\Models\User;
use App\Models\Applicant;
use App\Models\Document;
use App\Models\EmployeeDocument;
use App\Models\RecruitmentApproval;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Services\AccessControl;
use App\Services\RecruitmentActionService;
use App\Services\RecruitmentApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Services\HrmsNotificationService;

class JobPostingController extends Controller
{
    private const APPLICANT_NAME_REGEX = "/^(?=.{1,255}$)[A-Za-z]+(?:[ .'-][A-Za-z]+)*$/";
    private const APPLICANT_ADDRESS_REGEX = "/^[A-Za-z0-9][A-Za-z0-9\\s.,#\\/'()-]*$/";
    private const APPLICANT_HISTORY_STATUSES = ['completed', 'hired', 'archived'];

    public function __construct(
        private readonly HrmsNotificationService $notificationService,
        private readonly RecruitmentActionService $recruitmentActionService,
        private readonly RecruitmentApprovalService $recruitmentApprovalService,
    ) {
    }

    private function normalizeWhitespace(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function hasPositionDepartmentColumn(): bool
    {
        return Schema::hasColumn('positions', 'department_id');
    }

    private function departmentScopedPositionsQuery(Department $department)
    {
        $query = Position::query()
            ->whereRaw('LOWER(position) != ?', ['admin'])
            ->orderBy('position');

        if ($this->hasPositionDepartmentColumn()) {
            return $query->where('department_id', $department->id);
        }

        $positionIds = EmployeePosition::query()
            ->whereHas('employee', function ($employeeQuery) use ($department) {
                $employeeQuery->where('department_id', $department->id);
            })
            ->pluck('position_id');

        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'department_id')) {
            $postingPositionIds = JobPosting::query()
                ->where('department_id', $department->id)
                ->pluck('position_id');

            $positionIds = $positionIds->merge($postingPositionIds);
        }

        $positionIds = $positionIds
            ->filter()
            ->map(fn ($positionId) => (int) $positionId)
            ->unique()
            ->values();

        if ($positionIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $positionIds->all());
    }

    private function ensurePositionBelongsToDepartment(int $departmentId, int $positionId): Position
    {
        $department = Department::findOrFail($departmentId);
        $position = $this->departmentScopedPositionsQuery($department)
            ->where('id', $positionId)
            ->first();

        if (!$position) {
            throw ValidationException::withMessages([
                'position_id' => $this->hasPositionDepartmentColumn()
                    ? 'Selected position does not belong to the selected department.'
                    : 'Selected position is no longer available.',
            ]);
        }

        return $position;
    }

    private function hasRequiredHeadcountColumn(): bool
    {
        static $hasColumn;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('job_postings', 'required_headcount');
        }

        return $hasColumn;
    }

    private function normalizeJobPostingPayload(array $validated): array
    {
        if (!$this->hasRequiredHeadcountColumn()) {
            unset($validated['required_headcount']);
            return $validated;
        }

        if ((int) ($validated['required_headcount'] ?? 1) <= 0) {
            $validated['required_headcount'] = 1;
        }

        return $validated;
    }

    private function requiredHeadcountFor(JobPosting $posting): int
    {
        return max((int) ($posting->required_headcount ?? 1), 1);
    }

    private function validateClosingDateNotPast(?string $closingDate): void
    {
        if (!$closingDate) {
            return;
        }

        if (Carbon::parse($closingDate)->startOfDay()->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'closing_date' => 'Closing date must be today or later.',
            ]);
        }
    }

    private function withHiredCount($query)
    {
        return $query->withCount([
            'hiredApplicants as hired_count',
        ]);
    }

    private function applyStaffingFilter($query, string $staffing, bool $hasRequiredHeadcount)
    {
        return $query
            ->when($staffing === 'fully_staffed' && $hasRequiredHeadcount, function ($builder) {
                $builder->havingRaw('hired_count >= required_headcount');
            })
            ->when($staffing === 'partially_filled' && $hasRequiredHeadcount, function ($builder) {
                $builder->havingRaw('hired_count > 0')
                    ->havingRaw('hired_count < required_headcount');
            })
            ->when($staffing === 'unfilled', function ($builder) {
                $builder->having('hired_count', '=', 0);
            });
    }

    /**
     * Resolve an employee record from applicant email.
     */
    private function resolveEmployeeForApplicant(Applicant $applicant): ?Employee
    {
        $normalizedEmail = Str::lower(trim((string) $applicant->email));
        if ($normalizedEmail === '') {
            return null;
        }

        $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first();
    }

    /**
     * Backfill 201 file uploads for already-hired applicants.
     */
    private function syncMissingDocumentsForHiredApplicants(): void
    {
        $docNames = ['Application Letter', 'Resume', 'Transcript of Records'];
        $docIdsByName = Document::query()
            ->whereIn('document', $docNames)
            ->pluck('id', 'document');

        $hiredApplicants = Applicant::query()
            ->where('status', 'hired')
            ->whereNotNull('email')
            ->get();

        foreach ($hiredApplicants as $applicant) {
            $employee = $this->resolveEmployeeForApplicant($applicant);
            if (!$employee) {
                continue;
            }

            $sources = [
                'Application Letter' => (string) ($applicant->application_letter_path ?? ''),
                'Resume' => (string) ($applicant->resume_path ?? ''),
                'Transcript of Records' => (string) ($applicant->transcript_path ?? ''),
            ];

            $needsSync = false;
            foreach ($sources as $docName => $sourcePath) {
                if ($sourcePath === '' || !Storage::disk('local')->exists($sourcePath)) {
                    continue;
                }

                $documentId = $docIdsByName[$docName] ?? null;
                if (!$documentId) {
                    $needsSync = true;
                    break;
                }

                $existingUpload = EmployeeDocument::query()
                    ->where('employee_id', $employee->id)
                    ->where('document_id', $documentId)
                    ->first();

                if ($existingUpload && $existingUpload->file_path && Storage::disk('local')->exists($existingUpload->file_path)) {
                    if ($existingUpload->status !== 'verified' || !empty($existingUpload->review_notes)) {
                        $existingUpload->update([
                            'status' => 'verified',
                            'review_notes' => null,
                        ]);
                    }
                    continue;
                }

                if (!$existingUpload || !$existingUpload->file_path || !Storage::disk('local')->exists($existingUpload->file_path)) {
                    $needsSync = true;
                    break;
                }
            }

            if ($needsSync) {
                $this->syncApplicantDocumentsTo201($applicant, $employee);
            }
        }
    }

    /**
     * Move applicant-submitted files into employee 201 records.
     */
    private function syncApplicantDocumentsTo201(Applicant $applicant, Employee $employee): void
    {
        $documentMappings = [
            'application_letter_path' => ['name' => 'Application Letter', 'type' => 'Permanent'],
            'resume_path' => ['name' => 'Resume', 'type' => 'Permanent'],
            'transcript_path' => ['name' => 'Transcript of Records', 'type' => 'Permanent'],
        ];

        foreach ($documentMappings as $pathField => $meta) {
            $sourcePath = (string) ($applicant->{$pathField} ?? '');
            if ($sourcePath === '' || !Storage::disk('local')->exists($sourcePath)) {
                continue;
            }

            $document = Document::query()->firstOrCreate(
                ['document' => $meta['name']],
                [
                    // Category/Subcategory aligned with Personnel Data and Pre-Employment.
                    'document_category_id' => 2,
                    'document_subcategory_id' => 13,
                    'gender' => null,
                ]
            );

            $originalExt = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'pdf';
            $targetFileName = strtolower(str_replace(' ', '_', $meta['name']))
                . '_applicant_' . $applicant->id
                . '_' . now()->format('YmdHis')
                . '.' . $originalExt;
            $targetPath = 'employee_documents/' . $employee->id . '/' . $targetFileName;

            Storage::disk('local')->copy($sourcePath, $targetPath);

            $employeeDocument = EmployeeDocument::query()
                ->where('employee_id', $employee->id)
                ->where('document_id', $document->id)
                ->first();

            if ($employeeDocument) {
                $oldPath = $employeeDocument->file_path;
                $employeeDocument->update([
                    'file_path' => $targetPath,
                    'document_name' => $document->document,
                    // Applicant already passed screening before activation.
                    'status' => 'verified',
                    'review_notes' => null,
                    'issued_at' => null,
                    'expires_at' => null,
                    'expiry_notified_at' => null,
                ]);

                if ($oldPath && $oldPath !== $targetPath && Storage::disk('local')->exists($oldPath)) {
                    Storage::disk('local')->delete($oldPath);
                }
            } else {
                EmployeeDocument::create(array_merge([
                    'employee_id' => $employee->id,
                    'document_id' => $document->id,
                    'document_name' => $document->document,
                    'file_path' => $targetPath,
                    // Applicant already passed screening before activation.
                    'status' => 'verified',
                    'issued_at' => null,
                    'expires_at' => null,
                    'expiry_notified_at' => null,
                ], Schema::hasColumn('employee_documents', 'document_type') ? ['document_type' => 'Permanent'] : []));
            }
        }
    }

    /**
     * Helper to authorize HR/Admin access.
     */
    private function authorizeHR()
    {
        $user = Auth::user();
        if (!$user || !($user->isAdmin() || AccessControl::isHrStaff($user))) {
            abort(403, 'Only HR or Admins can manage job vacancies.');
        }
    }

    private function requiresRecruitmentApproval(?User $user): bool
    {
        return $user instanceof User && $this->recruitmentApprovalService->requiresApproval($user);
    }

    private function queueRecruitmentApproval(
        User $user,
        string $actionType,
        string $summary,
        array $payload = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        $this->recruitmentApprovalService->createRequest(
            $user,
            $actionType,
            $summary,
            $payload,
            $subjectType,
            $subjectId,
        );
    }

    private function publicPortalRedirectTarget(Request $request): string
    {
        $previous = trim((string) url()->previous());
        if ($previous !== '' && !str_contains($previous, '/jobs/') && !str_contains($previous, '/apply')) {
            return $previous;
        }

        return route('landing');
    }

    private function applyApplicantDirectoryViewFilter($query, string $view)
    {
        if ($view === 'history') {
            return $query->whereIn('status', self::APPLICANT_HISTORY_STATUSES);
        }

        return $query->where(function ($builder) {
            $builder->whereNull('status')
                ->orWhere('status', '')
                ->orWhereNotIn('status', self::APPLICANT_HISTORY_STATUSES);
        });
    }

    /**
     * Display a listing of job postings for HR/Admin.
     */
    public function index(Request $request)
    {
        $this->authorizeHR();
        $this->recruitmentActionService->closeExpiredOpenPostings();

        $user = Auth::user();
        $canReviewApprovals = $user && $this->recruitmentApprovalService->canReview($user);

        $staffing = strtolower((string) $request->query('staffing', ''));
        if (!in_array($staffing, ['fully_staffed', 'partially_filled', 'unfilled'], true)) {
            $staffing = '';
        }

        $hasRequiredHeadcount = $this->hasRequiredHeadcountColumn();

        $postings = $this->applyStaffingFilter(
            $this->withHiredCount(
                JobPosting::with(['department', 'position'])
            ),
            $staffing,
            $hasRequiredHeadcount
        )
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $departments = Department::orderBy('department')->get();
        $pendingPostingApprovals = collect();

        if (Schema::hasTable('recruitment_approvals') && $canReviewApprovals) {
            $pendingPostingApprovals = RecruitmentApproval::query()
                ->with(['requester.employee.department'])
                ->where('status', RecruitmentApproval::STATUS_PENDING)
                ->whereIn('action_type', [
                    RecruitmentApproval::ACTION_JOB_POSTING_CREATE,
                    RecruitmentApproval::ACTION_JOB_POSTING_UPDATE,
                    RecruitmentApproval::ACTION_JOB_POSTING_DELETE,
                ])
                ->latest()
                ->get();
        }

        return view('job-postings.index', compact(
            'postings',
            'departments',
            'staffing',
            'pendingPostingApprovals',
            'canReviewApprovals',
        ));
    }

    /**
     * Load selectable positions for a department when creating/editing postings.
     */
    public function positions(Department $department): JsonResponse
    {
        $this->authorizeHR();
        $includePositionId = (int) request()->query('include_position_id');

        $positions = $this->departmentScopedPositionsQuery($department)
            ->get()
            ->map(function (Position $position) use ($department, $includePositionId) {
                $normalizedName = strtolower(trim($position->position));
                $name = ucfirst($position->position);
                if ($department->department_type === 'Academic' && in_array($normalizedName, ['head', 'dean'], true)) {
                    $name = 'Dean';
                } elseif ($normalizedName === 'head') {
                    $name = 'Head';
                }

                $limit = $position->capacityLimit();
                $count = EmployeePosition::where('position_id', $position->id)
                    ->whereHas('employee', function ($query) use ($department) {
                        $query->where('department_id', $department->id)
                            ->whereHas('user', function ($userQuery) {
                                $userQuery->whereNull('archived_at');
                            });
                    })
                    ->distinct('employee_id')
                    ->count('employee_id');

                $isOccupied = $limit !== null && $count >= $limit;

                return [
                    'id' => (int) $position->id,
                    'name' => $name,
                    'is_occupied' => $isOccupied,
                    'is_current' => $includePositionId > 0 && (int) $position->id === $includePositionId,
                ];
            })
            ->filter(function ($item) use ($includePositionId) {
                if (!$item['is_occupied']) {
                    return true;
                }

                return $includePositionId > 0 && !empty($item['is_current']);
            })
            ->map(function ($item) {
                unset($item['is_occupied']);
                unset($item['is_current']);
                return $item;
            })
            ->sortBy(fn ($item) => strtolower(trim($item['name'] ?? '')))
            ->values();

        return response()->json([
            'positions' => $positions,
        ]);
    }

    /**
     * Store a newly created job posting in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeHR();
        $user = Auth::user();
        $hasRequiredHeadcount = $this->hasRequiredHeadcountColumn();
        $validated = $request->validate([
            'position_id' => 'required|exists:positions,id',
            'department_id' => 'required|exists:departments,id',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'employment_type' => 'required|string',
            'status' => 'required|in:open,closed,draft',
            'required_headcount' => $hasRequiredHeadcount ? 'required|integer|min:1|max:100' : 'nullable',
            'closing_date' => 'nullable|date',
        ]);

        $validated = $this->recruitmentActionService->prepareJobPostingPayload($validated);

        if ($this->requiresRecruitmentApproval($user)) {
            $departmentName = Department::find((int) $validated['department_id'])?->department ?? 'Department';
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_JOB_POSTING_CREATE,
                'Create job posting request: ' . $validated['title'] . ' for ' . $departmentName,
                [
                    'job_posting' => $validated,
                    'job_title' => $validated['title'],
                    'department_name' => $departmentName,
                ],
                JobPosting::class
            );

            return redirect()->route('job-postings.index')->with('success', 'Job posting request submitted for HR Head approval.');
        }

        $this->recruitmentActionService->createJobPosting($validated);

        return redirect()->route('job-postings.index')->with('success', 'Job posting created successfully.');
    }

    /**
     * Return edit payload for a posting modal.
     */
    public function editData(JobPosting $jobPosting): JsonResponse
    {
        $this->authorizeHR();

        return response()->json([
            'id' => $jobPosting->id,
            'department_id' => $jobPosting->department_id,
            'position_id' => $jobPosting->position_id,
            'position_label' => $jobPosting->position?->position ?? $jobPosting->title,
            'description' => (string) $jobPosting->description,
            'requirements' => (string) ($jobPosting->requirements ?? ''),
            'employment_type' => (string) $jobPosting->employment_type,
            'status' => (string) $jobPosting->status,
            'closing_date' => optional($jobPosting->closing_date)->format('Y-m-d'),
            'required_headcount' => (int) ($jobPosting->required_headcount ?? 1),
            'fulfilled_count' => (int) $jobPosting->applicants()->where('status', 'hired')->count(),
            'remaining_slots' => max(((int) ($jobPosting->required_headcount ?? 1)) - (int) $jobPosting->applicants()->where('status', 'hired')->count(), 0),
            'update_url' => route('job-postings.update', $jobPosting),
        ]);
    }

    /**
     * Update the specified job posting in storage.
     */
    public function update(Request $request, JobPosting $jobPosting)
    {
        $this->authorizeHR();
        $user = Auth::user();
        $hasRequiredHeadcount = $this->hasRequiredHeadcountColumn();
        $validated = $request->validate([
            'position_id' => 'required|exists:positions,id',
            'department_id' => 'required|exists:departments,id',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'employment_type' => 'required|string',
            'status' => 'required|in:open,closed,draft',
            'required_headcount' => $hasRequiredHeadcount ? 'required|integer|min:1|max:100' : 'nullable',
            'closing_date' => 'nullable|date',
        ]);

        $validated = $this->recruitmentActionService->prepareJobPostingPayload($validated);

        if ($this->requiresRecruitmentApproval($user)) {
            $departmentName = Department::find((int) $validated['department_id'])?->department ?? 'Department';
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_JOB_POSTING_UPDATE,
                'Update job posting request: ' . ($jobPosting->title ?: $validated['title']),
                [
                    'job_posting' => $validated,
                    'job_title' => $validated['title'],
                    'department_name' => $departmentName,
                ],
                JobPosting::class,
                (int) $jobPosting->id,
            );

            return redirect()->route('job-postings.index')->with('success', 'Job posting update submitted for HR Head approval.');
        }

        $this->recruitmentActionService->updateJobPosting($jobPosting, $validated);

        return redirect()->route('job-postings.index')->with('success', 'Job posting updated successfully.');
    }

    /**
     * Fallback update endpoint for modal submissions that have not yet resolved the resource action URL.
     */
    public function updateFallback(Request $request)
    {
        $postingId = (int) $request->input('posting_id');
        if ($postingId <= 0) {
            $updateUrl = trim((string) $request->input('update_url'));
            if ($updateUrl !== '') {
                $path = parse_url($updateUrl, PHP_URL_PATH) ?: '';
                if ($path !== '') {
                    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
                    $lastSegment = end($segments);
                    if ($lastSegment !== false && ctype_digit((string) $lastSegment)) {
                        $postingId = (int) $lastSegment;
                    }
                }
            }
        }

        abort_if($postingId <= 0, 404);

        $jobPosting = JobPosting::findOrFail($postingId);

        return $this->update($request, $jobPosting);
    }

    /**
     * Remove the specified job posting from storage.
     */
    public function destroy(JobPosting $jobPosting)
    {
        $this->authorizeHR();
        $user = Auth::user();

        if ($this->requiresRecruitmentApproval($user)) {
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_JOB_POSTING_DELETE,
                'Delete job posting request: ' . $jobPosting->title,
                [
                    'job_title' => $jobPosting->title,
                    'department_name' => $jobPosting->department?->department,
                ],
                JobPosting::class,
                (int) $jobPosting->id,
            );

            return redirect()->route('job-postings.index')->with('success', 'Job posting deletion submitted for HR Head approval.');
        }

        $this->recruitmentActionService->deleteJobPosting($jobPosting);

        return redirect()->route('job-postings.index')->with('success', 'Job posting deleted successfully.');
    }

    /**
     * Public Job Portal view.
     */
    public function portal()
    {
        $this->recruitmentActionService->closeExpiredOpenPostings();

        $query = $this->withHiredCount(
            JobPosting::with(['department', 'position'])
        )
            ->where('status', 'open')
            ->where(function ($builder) {
                $builder->whereNull('closing_date')
                    ->orWhereDate('closing_date', '>=', today());
            })
            ->orderByDesc('created_at');

        if ($this->hasRequiredHeadcountColumn()) {
            $query->whereRaw(
                '(select count(*) from applicants where job_postings.id = applicants.job_posting_id and status = ?) < required_headcount',
                ['hired']
            );
        } else {
            $query->whereRaw(
                '(select count(*) from applicants where job_postings.id = applicants.job_posting_id and status = ?) < ?',
                ['hired', 1]
            );
        }

        $postings = $query->get();

        return view('landing', compact('postings'));
    }

    /**
     * Display submitted applicants for HR/Admin.
     */
    public function applicants()
    {
        $this->authorizeHR();
        $user = Auth::user();
        $canReviewApprovals = $user && $this->recruitmentApprovalService->canReview($user);

        $view = request('view', 'active');

        $applicants = $this->applyApplicantDirectoryViewFilter(Applicant::with([
                'jobPosting' => function ($query) {
                    $this->withHiredCount($query->with(['department', 'position']));
                },
            ]), $view)
            ->latest()
            ->paginate(10)
            ->appends(['view' => $view]);

        $pendingApplicantApprovals = collect();
        if (Schema::hasTable('recruitment_approvals') && $canReviewApprovals) {
            $pendingApplicantApprovals = RecruitmentApproval::query()
                ->with(['requester.employee.department', 'subject.jobPosting.department', 'subject.jobPosting.position'])
                ->where('status', RecruitmentApproval::STATUS_PENDING)
                ->whereIn('action_type', [
                    RecruitmentApproval::ACTION_APPLICANT_COMPLETE,
                    RecruitmentApproval::ACTION_APPLICANT_ACTIVATE,
                    RecruitmentApproval::ACTION_APPLICANT_ARCHIVE,
                ])
                ->latest()
                ->get();
        }

        return view('job-postings.applicants', compact('applicants', 'view', 'pendingApplicantApprovals', 'canReviewApprovals'));
    }

    /**
     * Hire applicant and create or reactivate the linked account/employee profile.
     */
    private function hireApplicantRecord(
        Applicant $applicant,
        string $successMessage,
        string $auditEvent,
        array $redirectParams = [],
        ?string $filledSuccessMessage = null
    ) {
        $posting = $applicant->jobPosting;
        if (!$posting) {
            return redirect()->route('job-postings.applicants')
                ->with('error', 'Applicant has no linked vacancy.');
        }

        if ($applicant->status === 'hired' && ($applicant->account_status ?? 'active') === 'active') {
            return redirect()->route('job-postings.applicants')
                ->with('warning', 'Applicant is already hired for this vacancy.');
        }

        $normalizedEmail = Str::lower(trim((string) $applicant->email));
        $fullName = trim((string) $applicant->full_name);
        $nameParts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = $nameParts[0] ?? 'Applicant';
        $lastName = count($nameParts) > 1 ? trim(implode(' ', array_slice($nameParts, 1))) : 'Applicant';
        $employeesHasAddressColumn = Schema::hasColumn('employees', 'address');
        $autoArchivedCount = 0;
        $vacancyFilled = false;

        DB::transaction(function () use ($applicant, $posting, $normalizedEmail, $fullName, $firstName, $lastName, $employeesHasAddressColumn, &$autoArchivedCount, &$vacancyFilled) {
            $posting = JobPosting::whereKey($posting->id)->lockForUpdate()->firstOrFail();
            $requiredHeadcount = $this->requiredHeadcountFor($posting);
            $fulfilledCount = (int) Applicant::where('job_posting_id', $posting->id)
                ->where('status', 'hired')
                ->count();

            if ($fulfilledCount >= $requiredHeadcount) {
                throw ValidationException::withMessages([
                    'job_posting_id' => 'This vacancy has already reached its required headcount.',
                ]);
            }

            $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $fullName ?: ($firstName . ' ' . $lastName),
                    'email' => $normalizedEmail,
                    'gender' => $applicant->gender ?: null,
                    'role' => 'employee',
                    'password' => Hash::make('password'),
                ]);
            } else {
                if ($user->archived_at) {
                    $user->archived_at = null;
                }
                if (empty($user->name)) {
                    $user->name = $fullName ?: ($firstName . ' ' . $lastName);
                }
                if (empty($user->gender) && !empty($applicant->gender)) {
                    $user->gender = $applicant->gender;
                }
                $user->save();
            }

            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                $employeePayload = [
                    'user_id' => $user->id,
                    'employee_id' => Employee::nextEmployeeId(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'department_id' => $posting->department_id,
                    'hire_date' => now()->toDateString(),
                    'status' => 'active',
                ];

                if ($employeesHasAddressColumn) {
                    $employeePayload['address'] = $applicant->address;
                }

                $employee = Employee::create($employeePayload);
            } else {
                $employeeUpdatePayload = [
                    'status' => 'active',
                    'department_id' => $employee->department_id ?: $posting->department_id,
                ];

                if ($employeesHasAddressColumn) {
                    $employeeUpdatePayload['address'] = $employee->address ?: $applicant->address;
                }

                $employee->update($employeeUpdatePayload);
            }

            if ($posting->position_id) {
                $hasPosition = EmployeePosition::where('employee_id', $employee->id)
                    ->where('position_id', $posting->position_id)
                    ->exists();

                if (!$hasPosition) {
                    EmployeePosition::create([
                        'employee_id' => $employee->id,
                        'position_id' => $posting->position_id,
                    ]);
                }
            }

            $this->syncApplicantDocumentsTo201($applicant, $employee);

            $applicant->update([
                'status' => 'hired',
                'account_status' => 'active',
            ]);

            $fulfilledAfter = $fulfilledCount + 1;
            if ($fulfilledAfter >= $requiredHeadcount) {
                $vacancyFilled = true;
                $autoArchivedCount = Applicant::where('job_posting_id', $posting->id)
                    ->whereKeyNot($applicant->id)
                    ->whereNotIn('status', ['hired', 'archived'])
                    ->update([
                        'status' => 'archived',
                        'account_status' => 'inactive',
                    ]);

                $posting->update(['status' => 'closed']);
            }
        });

        AuditLogger::logSystem($auditEvent, [
            'applicant_name' => $applicant->full_name,
            'applicant_email' => $applicant->email,
            'job_title' => $posting->title,
            'auto_archived_applicants' => $autoArchivedCount,
            'vacancy_filled' => $vacancyFilled,
        ], null, 'Applicant', $applicant->id);

        return redirect()->route('job-postings.applicants', $redirectParams)
            ->with('success', $vacancyFilled && $filledSuccessMessage ? $filledSuccessMessage : $successMessage);
    }

    /**
     * Complete the applicant process by activating the account and employee profile.
     */
    public function completeApplicant(Applicant $applicant)
    {
        $this->authorizeHR();

        $user = Auth::user();
        if ($this->requiresRecruitmentApproval($user)) {
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_APPLICANT_COMPLETE,
                'Complete applicant request: ' . $applicant->full_name . ' for ' . ($applicant->jobPosting?->title ?? 'Job Posting'),
                [
                    'applicant_name' => $applicant->full_name,
                    'job_title' => $applicant->jobPosting?->title,
                ],
                Applicant::class,
                (int) $applicant->id,
            );

            return redirect()->route('job-postings.applicants', ['view' => 'history'])
                ->with('success', 'Applicant completion submitted for HR Head approval.');
        }

        $result = $this->recruitmentActionService->completeApplicant($applicant);

        return redirect()->route('job-postings.applicants', ['view' => 'history'])
            ->with('success', !empty($result['vacancy_filled'])
                ? 'Applicant completed. Vacancy filled. Remaining applicants archived.'
                : 'Applicant completed.');
    }

    /**
     * Activate applicant account when applicant approaches HR.
     */
    public function activateApplicant(Applicant $applicant)
    {
        $this->authorizeHR();

        $user = Auth::user();
        if ($this->requiresRecruitmentApproval($user)) {
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_APPLICANT_ACTIVATE,
                'Activate applicant request: ' . $applicant->full_name . ' for ' . ($applicant->jobPosting?->title ?? 'Job Posting'),
                [
                    'applicant_name' => $applicant->full_name,
                    'job_title' => $applicant->jobPosting?->title,
                ],
                Applicant::class,
                (int) $applicant->id,
            );

            return redirect()->route('job-postings.applicants', ['view' => 'history'])
                ->with('success', 'Applicant activation submitted for HR Head approval.');
        }

        $this->recruitmentActionService->activateApplicant($applicant);

        return redirect()->route('job-postings.applicants', ['view' => 'history'])
            ->with('success', 'Applicant activated as hired. Employee account details were created successfully.');
    }

    /**
     * Archive applicant as rejected but keep record in history.
     */
    public function archiveApplicant(Applicant $applicant)
    {
        $this->authorizeHR();
        $user = Auth::user();

        if ($this->requiresRecruitmentApproval($user)) {
            $this->queueRecruitmentApproval(
                $user,
                RecruitmentApproval::ACTION_APPLICANT_ARCHIVE,
                'Archive applicant request: ' . $applicant->full_name . ' for ' . ($applicant->jobPosting?->title ?? 'Job Posting'),
                [
                    'applicant_name' => $applicant->full_name,
                    'job_title' => $applicant->jobPosting?->title,
                ],
                Applicant::class,
                (int) $applicant->id,
            );

            return redirect()->route('job-postings.applicants', ['view' => 'history'])
                ->with('success', 'Applicant archive submitted for HR Head approval.');
        }

        $this->recruitmentActionService->archiveApplicant($applicant);

        return redirect()->route('job-postings.applicants', ['view' => 'history'])
            ->with('success', 'Applicant archived (rejected) and moved to applicant history.');
    }

    /**
     * Store a public application from the careers portal.
     */
    public function apply(Request $request, JobPosting $jobPosting)
    {
        $redirectTarget = $this->publicPortalRedirectTarget($request);
        $normalizedEmail = Str::lower(trim((string) $request->input('email')));
        $normalizedPhone = preg_replace('/[^\d+]/', '', (string) $request->input('phone', ''));
        $normalizedFullName = $this->normalizeWhitespace($request->input('full_name'));
        $normalizedAddress = $this->normalizeWhitespace($request->input('address'));
        if (str_starts_with($normalizedPhone, '639') && !str_starts_with($normalizedPhone, '+639')) {
            $normalizedPhone = '+' . $normalizedPhone;
        }

        $request->merge([
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone !== '' ? $normalizedPhone : null,
            'full_name' => $normalizedFullName !== '' ? $normalizedFullName : null,
            'address' => $normalizedAddress !== '' ? $normalizedAddress : null,
        ]);

        if ($jobPosting->status !== 'open') {
            return redirect()->to($redirectTarget)
                ->with('error', 'This posting is no longer accepting applications.');
        }

        if ($jobPosting->closing_date && $jobPosting->closing_date->startOfDay()->lt(now()->startOfDay())) {
            if ($jobPosting->status !== 'closed') {
                $jobPosting->update(['status' => 'closed']);
            }

            return redirect()->to($redirectTarget)
                ->with('warning', 'This posting is already closed because the application deadline has passed.');
        }

        $fulfilledCount = (int) Applicant::where('job_posting_id', $jobPosting->id)
            ->where('status', 'hired')
            ->count();
        $requiredHeadcount = $this->requiredHeadcountFor($jobPosting);
        if ($fulfilledCount >= $requiredHeadcount) {
            if ($jobPosting->status !== 'closed') {
                $jobPosting->update(['status' => 'closed']);
            }
            return redirect()->to($redirectTarget)
                ->with('warning', 'This posting has already reached the required number of hires.');
        }

        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:255', 'regex:' . self::APPLICANT_NAME_REGEX],
            'email' => ['required', 'email', 'max:255'],
            'gender' => 'required|in:male,female',
            'birthday' => 'required|date|before_or_equal:today',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(?:\\+63|0)9\\d{9}$/'],
            'address' => ['required', 'string', 'max:1000', 'regex:' . self::APPLICANT_ADDRESS_REGEX],
            'message' => 'nullable|string|max:4000',
            'application_letter' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'transcript' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ], [
            'full_name.regex' => 'Full name can contain letters, spaces, apostrophes, periods, and hyphens only.',
            'phone.regex' => 'Use a valid Philippine mobile number such as 09171234567 or +639171234567.',
            'address.regex' => 'Address can contain letters, numbers, spaces, commas, periods, #, slash, apostrophes, parentheses, and hyphens only.',
        ]);

        if ($validator->fails()) {
            return redirect()->to($redirectTarget)
                ->with('error', 'Please correct the highlighted application fields and try again.')
                ->withErrors($validator)
                ->withInput($request->except(['application_letter', 'resume', 'transcript']));
        }

        $validated = $validator->validated();
        $alreadyApplied = Applicant::where('job_posting_id', $jobPosting->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();

        if ($alreadyApplied) {
            return redirect()->to($redirectTarget)
                ->with('error', 'Application already submitted for this vacancy using the same email address.');
        }

        $baseFolder = 'applicants/' . $jobPosting->id;
        $storedPaths = [];
        $applicant = null;

        try {
            $applicationLetterPath = $request->file('application_letter')->store($baseFolder);
            $storedPaths[] = $applicationLetterPath;

            $resumePath = $request->file('resume')->store($baseFolder);
            $storedPaths[] = $resumePath;

            $transcriptPath = $request->hasFile('transcript')
                ? $request->file('transcript')->store($baseFolder)
                : null;

            if ($transcriptPath) {
                $storedPaths[] = $transcriptPath;
            }

            $applicantPayload = [
                'job_posting_id' => $jobPosting->id,
                'full_name' => $validated['full_name'],
                'email' => $normalizedEmail,
                'gender' => $validated['gender'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'] ?? null,
                'message' => $validated['message'] ?? null,
                'application_letter_path' => $applicationLetterPath,
                'resume_path' => $resumePath,
                'transcript_path' => $transcriptPath,
                'status' => 'submitted',
                'account_status' => 'inactive',
            ];

            if (Schema::hasColumn('applicants', 'address')) {
                $applicantPayload['address'] = $validated['address'];
            }

            DB::transaction(function () use ($applicantPayload, &$applicant) {
                $applicant = Applicant::create($applicantPayload);
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                    Storage::disk('local')->delete($storedPath);
                }
            }

            report($exception);

            return redirect()->to($redirectTarget)
                ->withInput($request->except(['application_letter', 'resume', 'transcript']))
                ->with('error', 'Application could not be submitted right now. Please try again.');
        }

        if ($applicant) {
            JobApplicationSubmitted::dispatch($applicant, $jobPosting);
        }

        AuditLogger::logSystem('job_application_submitted', [
            'job_title' => $jobPosting->title,
            'applicant_name' => $validated['full_name'],
            'applicant_email' => $validated['email'],
        ], null, 'JobPosting', $jobPosting->id);

        return redirect()->to($redirectTarget)
            ->with('success', 'Application submitted successfully.');
    }

}
