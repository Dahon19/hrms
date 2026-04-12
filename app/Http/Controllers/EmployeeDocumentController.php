<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentSubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Services\AuditLogger;
use App\Services\HrmsNotificationService;
use App\Services\AccessControl;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly HrmsNotificationService $notificationService
    ) {
    }

    private function ensureDocumentEditingAllowed(?Employee $employee): void
    {
        if ($employee && $employee->hasActiveOffboardingRecord()) {
            abort(423, 'Employee documents are read-only while the employee is in offboarding.');
        }
    }

    private function normalizeDocumentName(?string $name): string
    {
        $normalized = strtolower(trim($name ?? ''));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        return trim(preg_replace('/\s+/', ' ', $normalized));
    }

    private function genderSpecificDocuments(): array
    {
        return [
            'female' => [
                'marriage contract',
                'affidavit of change of namesignature',
                'maternity notification form',
                'maternity leave application',
                'magna carta for women medical certificate',
                'vawc leave certificationprotection order',
                'obgyn clearancemedical certificate',
                'mammogramcervical cancer screening results',
            ],
            'male' => [
                'paternity leave application',
                'proof of childbirthbirth certificate',
                'prostate exampsa results',
            ],
        ];
    }

    private function isDocumentAllowedForGender($document, ?string $gender): bool
    {
        if (!$document) {
            return true;
        }

        if (!$gender) {
            return true;
        }

        // 1. Check Database Restriction (if $document is a Model)
        if ($document instanceof Document && !empty($document->gender)) {
            return strtolower($document->gender) === strtolower($gender);
        }

        // 2. Fallback to Name-Based Restriction
        $documentName = $document instanceof Document ? $document->document : $document;
        $normalizedDoc = $this->normalizeDocumentName($documentName);
        $genderDocs = $this->genderSpecificDocuments();

        if ($gender === 'female') {
            if (in_array($normalizedDoc, $genderDocs['male'], true)) {
                return false;
            }
        }

        if ($gender === 'male') {
            if (in_array($normalizedDoc, $genderDocs['female'], true)) {
                return false;
            }
        }

        return true;
    }

    private function issueExpiryRequiredDocuments(): array
    {
        return [
            'prc license professional regulation commission',
            'prc license',
            'drivers license',
            'nbi clearance',
            'police clearance',
            'barangay clearance',
            'healthmedical certificate',
            'health certificate',
            'medical certificate',
            'working permit aep for foreign employees',
            'working permit aep',
            'working permit',
            'aep',
            'security guard license',
            'first aid cpr certification red cross',
            'first aid cpr certification',
            'first aid certification',
            'cpr certification',
            'tesda national certificate nc',
            'tesda national certificate',
            'employment contract for cosjob order staff',
            'employment contract',
            'notarized affidavits',
            'notarized affidavit',
        ];
    }

    private function requiresIssueAndExpiry(?Document $document): bool
    {
        if (!$document) {
            return false;
        }

        $normalized = $this->normalizeDocumentName($document->document);
        return in_array($normalized, $this->issueExpiryRequiredDocuments(), true);
    }

    private function normalizeDepartmentName(?string $departmentName): string
    {
        $normalized = strtolower(trim($departmentName ?? ''));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        return trim(preg_replace('/\s+/', ' ', $normalized));
    }

    private function isDepartmentLeader($user): bool
    {
        return AccessControl::isHeadOrDean($user) || AccessControl::isDepartmentSupport($user);
    }

    private function isHrHead($user): bool
    {
        return AccessControl::isHrHead($user);
    }

    private function canReviewDocuments($user): bool
    {
        return $user->isAdmin() || $this->isHrHead($user);
    }

    private function shouldAutoVerifyOwnSubmission($user, ?Employee $employee): bool
    {
        return $user && $employee
            && $this->isHrHead($user)
            && (int) optional($user->employee)->id === (int) $employee->id;
    }

    private function isPresidentOfficeHead($user): bool
    {
        if ($user->positionName() !== 'head') {
            return false;
        }

        $normalizedDept = $this->normalizeDepartmentName($user->employee?->department?->department ?? '');
        return $normalizedDept === 'presidents office';
    }

    private function canViewEmployeeDocuments($user, ?Employee $employee): bool
    {
        if (!$employee) {
            return false;
        }

        if ($user->isReadOnlyStaff() && !$user->isAdmin()) {
            if ($employee->user && $employee->user->role === 'admin') {
                return false;
            }
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (AccessControl::isHrStaff($user)) {
            return true;
        }

        if (AccessControl::isDepartmentSupport($user)) {
            $userDeptId = $user->employee?->department_id;
            return $userDeptId && $employee->department_id === $userDeptId;
        }

        return (int) optional($user->employee)->id === (int) $employee->id;
    }

    private function documentIndexRouteParams(Employee|int $employee, ?Request $request = null): array
    {
        $employeeId = $employee instanceof Employee ? $employee->id : (int) $employee;
        $request ??= request();

        $params = [
            'employee_id' => $employeeId,
        ];

        if ($request->boolean('embedded')) {
            $params['embedded'] = 1;
        }

        return $params;
    }

    private function employeeDocumentCompatibilityPayload(?Document $document = null): array
    {
        if (!Schema::hasColumn('employee_documents', 'document_type')) {
            return [];
        }

        return [
            'document_type' => 'Permanent',
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if (
            !$request->boolean('embedded')
            && ($user->isAdmin() || AccessControl::isHrStaff($user))
        ) {
            return redirect()
                ->route('employees.index')
                ->with('info', 'Employee documents are now accessed from the Employees module.');
        }

        $employees = Employee::select(['id', 'employee_id', 'first_name', 'last_name', 'department_id', 'user_id'])
            ->nonAdmin()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $selectedEmployee = null;
        $employeeDocuments = collect();
        $employee = $user->employee;

        // Get the position name safely (defaults to 'employee' if not found)
        $positionName = $user->positionName();
        $canViewAll = $user->isAdmin() || AccessControl::isHrStaff($user);
        $departmentId = $user->employee?->department_id;
        $normalizedDept = $this->normalizeDepartmentName($user->employee?->department?->department ?? '');
        $excludedHeadDept = in_array($normalizedDept, ['hr department', 'presidents office'], true);
        $isDepartmentSupport = AccessControl::isDepartmentSupport($user);

        if ($isDepartmentSupport && $departmentId) {
            $employees = Employee::select(['id', 'employee_id', 'first_name', 'last_name', 'department_id', 'user_id'])
                ->nonAdmin()
                ->where('department_id', $departmentId)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        } elseif ($this->isHrHead($user) || $user->isAdmin()) {
            $employees = Employee::select(['id', 'employee_id', 'first_name', 'last_name', 'department_id', 'user_id'])
                ->nonAdmin()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        if (($user->isReadOnlyStaff() || $isDepartmentSupport) && !$user->isAdmin()) {
            $employees = $employees->filter(function ($employee) {
                return $employee->user?->role !== 'admin';
            })->values();
        }

        // 1. HANDLE DOSSIER LOGIC (Top Section)
        if (!$canViewAll) {
            $selectedEmployee = $user->employee;
        } elseif ($canViewAll) {
            $requestedId = $request->input('employee_id');
            if (is_array($requestedId)) {
                $requestedId = $requestedId[0] ?? null;
            }
            if ($requestedId) {
                $selectedEmployee = Employee::with('user')->find($requestedId);
            } elseif ($user->employee) {
                $selectedEmployee = $user->employee;
            }
        }

        if ($selectedEmployee instanceof \Illuminate\Support\Collection || $selectedEmployee instanceof \Illuminate\Database\Eloquent\Collection) {
            $selectedEmployee = $selectedEmployee->first();
        }

        if ($selectedEmployee && $selectedEmployee->user && $selectedEmployee->user->role === 'admin') {
            $selectedEmployee = null;
        }

        if ($selectedEmployee && !$this->canViewEmployeeDocuments($user, $selectedEmployee)) {
            $selectedEmployee = null;
        }

        $documentGroups = collect();

        if ($selectedEmployee) {
            $selectedEmployee->loadMissing('user');
            $employeeDocuments = EmployeeDocument::where('employee_id', $selectedEmployee->id)
                ->with('documents')
                ->get();

            $uploadsByDocument = $employeeDocuments->keyBy('document_id');

            $gender = $selectedEmployee->user?->gender;
            $documentsForIndex = Document::with(['category', 'subcategory'])
                ->orderByRaw('LOWER(document)')
                ->get()
                ->filter(function ($doc) use ($gender) {
                    return $this->isDocumentAllowedForGender($doc, $gender);
                })
                ->values();

            $today = now()->startOfDay();

            $documentGroups = $documentsForIndex
                ->groupBy(function ($doc) {
                    return $doc->category?->name ?? 'Uncategorized';
                })
                ->sortBy(function ($group, $categoryName) {
                    $isUncategorized = $categoryName === 'Uncategorized';
                    return [
                        $isUncategorized ? 1 : 0,
                        strtolower((string) $categoryName),
                    ];
                })
                ->map(function ($group) use ($uploadsByDocument, $selectedEmployee, $today) {
                    return $group->groupBy(function ($doc) {
                        return $doc->subcategory?->name ?? 'General';
                    })
                    ->sortBy(function ($docs, $subcategoryName) {
                        $isGeneral = $subcategoryName === 'General';
                        return [
                            $isGeneral ? 1 : 0,
                            strtolower((string) $subcategoryName),
                        ];
                    })
                    ->map(function ($docs) use ($uploadsByDocument, $selectedEmployee, $today) {
                        return $docs->map(function ($doc) use ($uploadsByDocument, $selectedEmployee, $today) {
                            $upload = $uploadsByDocument->get($doc->id);
                            $expiresAt = $upload?->expires_at;
                            $expiryState = null;
                            if ($expiresAt) {
                                $daysUntil = $today->diffInDays($expiresAt, false);
                                if ($daysUntil < 0) {
                                    $expiryState = 'expired';
                                } elseif ($daysUntil <= 30) {
                                    $expiryState = 'expiring';
                                }
                            }

                            $statusValue = $upload?->status ?? null;
                            $statusLabel = 'Missing';
                            $badgeClass = 'bg-danger';
                            if ($upload) {
                                $statusLabel = match ($statusValue) {
                                    'verified' => 'Uploaded',
                                    'reupload' => 'Reupload',
                                    default => 'Submitted',
                                };
                                $badgeClass = match ($statusValue) {
                                    'verified' => 'bg-success',
                                    'reupload' => 'bg-warning text-dark',
                                    default => 'bg-info',
                                };
                            }

                            return (object) [
                                'document_id' => $doc->id,
                                'document_name' => $doc->document,
                                'status' => $statusLabel,
                                'badge_class' => $badgeClass,
                                'uploaded_at' => $upload ? $upload->created_at->format('M d, Y') : null,
                                'issued_at' => $upload?->issued_at?->toDateString(),
                                'expires_at' => $expiresAt ? $expiresAt->toDateString() : null,
                                'expires_display' => $expiresAt ? $expiresAt->format('M d, Y') : null,
                                'expiry_state' => $expiryState,
                                'file_path' => $upload ? $upload->file_path : null,
                                'upload_id' => $upload ? $upload->id : null,
                                'review_notes' => $upload?->review_notes,
                                'status_raw' => $statusValue,
                                'employee_id' => $selectedEmployee ? $selectedEmployee->id : null,
                            ];
                        });
                    });
                });
        }

        // 2. HANDLE CATALOG LOGIC (Bottom Section - The missing piece)
        $search = $request->get('search');
        $documents = Document::query()
            ->when($search, function($query) use ($search) {
                return $query->where('document', 'like', "%{$search}%");
            })
            ->with(['category', 'subcategory'])
            ->orderBy('document', 'asc')
            ->paginate(10); 

        $categories = DocumentCategory::withCount('subcategories')
            ->orderByRaw('LOWER(name)')
            ->get();
        $subcategories = DocumentSubcategory::query()
            ->with('category')
            ->leftJoin('document_categories as categories', 'categories.id', '=', 'document_subcategories.document_category_id')
            ->select('document_subcategories.*')
            ->orderByRaw('LOWER(categories.name)')
            ->orderByRaw('LOWER(document_subcategories.name)')
            ->get();
            
        $canReviewDocuments = $this->canReviewDocuments($user);

        return view('documents.index', compact('employees', 'selectedEmployee', 'documentGroups', 'documents', 'positionName', 'categories', 'subcategories', 'canReviewDocuments'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'employee'])) {
            abort(403);
        }

        $document = Document::find($request->input('document_id'));
        $requiresDates = $this->requiresIssueAndExpiry($document);

        $rules = [
            'document_id' => 'required|exists:documents,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'issued_at' => $requiresDates ? 'required|date' : 'nullable|date',
            'expires_at' => $requiresDates ? 'required|date|after_or_equal:issued_at' : 'nullable|date',
        ];

        if ($user->isAdmin()) {
            $rules['employee_id'] = 'required|exists:employees,id';
        }

        $validated = $request->validate($rules);

        if ($user->isAdmin()) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            $employee = $user->employee;
            if (!$employee) {
                abort(403);
            }
        }

        $this->ensureDocumentEditingAllowed($employee);

        if (!$user->isAdmin() && (int) $request->input('employee_id', $employee->id) !== (int) $employee->id) {
            abort(403);
        }

        if (!$this->isDocumentAllowedForGender($document ?? $request->input('document_name'), $employee->user?->gender)) {
            return back()->withErrors([
                'document_id' => 'Selected document is not applicable to the employee gender.',
            ])->withInput();
        }

        $folder = 'employee_documents/' . $employee->id;
        $filePath = $request->file('file')->store($folder, 'local');

        EmployeeDocument::create(array_merge([
            'employee_id' => $employee->id,
            'document_id' => $request->document_id,
            'document_name' => $document?->document ?? $request->input('document_name', 'Document'),
            'file_path' => $filePath,
            'status' => $this->shouldAutoVerifyOwnSubmission($user, $employee) ? 'verified' : 'submitted',
            'issued_at' => $request->input('issued_at'),
            'expires_at' => $request->input('expires_at'),
            'expiry_notified_at' => null,
        ], $this->employeeDocumentCompatibilityPayload($document)));

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employee, $request))
            ->with('success', $this->shouldAutoVerifyOwnSubmission($user, $employee) ? 'Document uploaded and verified successfully.' : 'Document uploaded successfully.');
    }

    public function update(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && (int) $employeeDocument->employee_id !== (int) optional($user->employee)->id) {
            abort(403);
        }

        $this->ensureDocumentEditingAllowed($employeeDocument->employee);

        $document = $employeeDocument->documents ?? Document::find($employeeDocument->document_id);
        if (!$this->isDocumentAllowedForGender($document, $employeeDocument->employee->user?->gender)) {
            return back()->withErrors([
                'document_id' => 'Selected document is not applicable to the employee gender.',
            ])->withInput();
        }
        $requiresDates = $this->requiresIssueAndExpiry($document);

        $request->validate([
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'issued_at' => $requiresDates ? 'required|date' : 'nullable|date',
            'expires_at' => $requiresDates ? 'required|date|after_or_equal:issued_at' : 'nullable|date',
        ]);

        if ($request->hasFile('file')) {
            $oldPath = $employeeDocument->file_path;
            $folder = 'employee_documents/' . $employeeDocument->employee_id;
            $newPath = $request->file('file')->store($folder, 'local');

            $employeeDocument->file_path = $newPath;
            $employeeDocument->status = $this->shouldAutoVerifyOwnSubmission($user, $employeeDocument->employee) ? 'verified' : 'submitted';
            $employeeDocument->review_notes = null;
            
            if ($employeeDocument->save()) {
                if ($oldPath && Storage::disk('local')->exists($oldPath)) {
                    Storage::disk('local')->delete($oldPath);
                }
            }
        }

        if ($request->has('issued_at')) {
            $employeeDocument->issued_at = $request->input('issued_at') ?: null;
        }

        if ($request->has('expires_at')) {
            $employeeDocument->expires_at = $request->input('expires_at') ?: null;
            $employeeDocument->expiry_notified_at = null;
        }

        if ($request->has('issued_at') || $request->has('expires_at')) {
            $employeeDocument->save();
        }

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employeeDocument->employee_id, $request))
            ->with('success', $this->shouldAutoVerifyOwnSubmission($user, $employeeDocument->employee) ? 'Document updated and verified successfully.' : 'Document updated successfully.');
    }

    public function verify(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$this->canReviewDocuments($user)) {
            abort(403);
        }

        $employeeDocument->update([
            'status' => 'verified',
            'review_notes' => null,
        ]);

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employeeDocument->employee_id, $request))
            ->with('success', 'Document verified.');
    }

    public function requestReupload(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$this->canReviewDocuments($user)) {
            abort(403);
        }

        $data = $request->validate([
            'review_notes' => 'required|string|max:2000',
        ]);

        $employeeDocument->update([
            'status' => 'reupload',
            'review_notes' => $data['review_notes'],
        ]);

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employeeDocument->employee_id, $request))
            ->with('success', 'Reupload requested.');
    }

    public function remindReupload(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$this->canReviewDocuments($user)) {
            abort(403);
        }

        $employeeDocument->loadMissing(['employee.user', 'documents']);
        if ($employeeDocument->status !== 'reupload') {
            return back()->with('error', 'Only documents pending reupload can be reminded.');
        }

        $recipient = $employeeDocument->employee?->user;
        if (!$recipient) {
            return back()->with('error', 'Employee user account is unavailable for reminders.');
        }

        $documentName = $employeeDocument->documents?->document ?: $employeeDocument->document_name ?: 'Document';
        $employeeName = trim(($employeeDocument->employee?->first_name ?? '') . ' ' . ($employeeDocument->employee?->last_name ?? ''));
        $message = $employeeDocument->review_notes
            ? 'Please reupload your ' . $documentName . '. Review note: ' . $employeeDocument->review_notes
            : 'Please reupload your ' . $documentName . ' as requested by HR.';

        $this->notificationService->notifyUsers([$recipient], [
            'title' => 'Document Reupload Reminder',
            'message' => $message,
            'type' => 'warning',
            'module' => 'documents',
            'record_id' => $employeeDocument->id,
            'route_name' => 'employee-documents.index',
            'route_params' => [
                'employee_id' => $employeeDocument->employee_id,
            ],
            'event_key' => 'employee_document.reupload_reminder',
            ...$this->notificationService->formatSender($user),
        ]);

        AuditLogger::log('document_reupload_reminder_sent', $employeeDocument, [
            'employee_id' => $employeeDocument->employee_id,
            'employee_name' => $employeeName,
            'document_name' => $documentName,
        ]);

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employeeDocument->employee_id, $request))
            ->with('success', 'Reupload reminder sent.');
    }

    public function remindExpiry(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$this->canReviewDocuments($user)) {
            abort(403);
        }

        $employeeDocument->loadMissing(['employee.user', 'documents']);
        $expiresAt = $employeeDocument->expires_at?->copy()?->startOfDay();
        if (!$expiresAt) {
            return back()->with('error', 'This document has no expiry date to remind.');
        }

        $today = now()->startOfDay();
        $daysUntil = $today->diffInDays($expiresAt, false);
        if ($daysUntil > 30) {
            return back()->with('error', 'Only expiring or expired documents can be reminded.');
        }

        $recipient = $employeeDocument->employee?->user;
        if (!$recipient) {
            return back()->with('error', 'Employee user account is unavailable for reminders.');
        }

        $documentName = $employeeDocument->documents?->document ?: $employeeDocument->document_name ?: 'Document';
        $message = $daysUntil < 0
            ? $documentName . ' expired on ' . $expiresAt->format('M d, Y') . '. Please upload an updated copy.'
            : $documentName . ' will expire on ' . $expiresAt->format('M d, Y') . '. Please upload an updated copy before it expires.';

        $this->notificationService->notifyUsers([$recipient], [
            'title' => $daysUntil < 0 ? 'Expired Document Reminder' : 'Document Expiry Reminder',
            'message' => $message,
            'type' => $daysUntil < 0 ? 'warning' : 'info',
            'module' => 'documents',
            'record_id' => $employeeDocument->id,
            'route_name' => 'employee-documents.index',
            'route_params' => [
                'employee_id' => $employeeDocument->employee_id,
            ],
            'event_key' => 'employee_document.expiry_reminder',
            ...$this->notificationService->formatSender($user),
        ]);

        $employeeDocument->forceFill([
            'expiry_notified_at' => now(),
        ])->save();

        AuditLogger::log('document_expiry_reminder_sent', $employeeDocument, [
            'employee_id' => $employeeDocument->employee_id,
            'document_name' => $documentName,
            'expires_at' => $expiresAt->toDateString(),
        ]);

        return redirect()
            ->route('employee-documents.index', $this->documentIndexRouteParams($employeeDocument->employee_id, $request))
            ->with('success', 'Expiry reminder sent.');
    }

    public function destroy(Request $request, EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && (int) $employeeDocument->employee_id !== (int) optional($user->employee)->id) {
            abort(403);
        }

        $this->ensureDocumentEditingAllowed($employeeDocument->employee);

        $empId = $employeeDocument->employee_id;
        Storage::disk('local')->delete($employeeDocument->file_path);
        $employeeDocument->delete();
        
        return redirect()->route('employee-documents.index', $this->documentIndexRouteParams($empId, $request))
                         ->with('success', 'Document deleted.');
    }

    public function download(EmployeeDocument $employeeDocument)
    {
        $user = Auth::user();
        $employeeDocument->loadMissing(['employee']);
        if (!$this->canViewEmployeeDocuments($user, $employeeDocument->employee)) {
            abort(403);
        }

        if (!Storage::disk('local')->exists($employeeDocument->file_path)) {
            abort(404, 'File not found');
        }

        $extension = pathinfo($employeeDocument->file_path, PATHINFO_EXTENSION);
        $downloadName = $employeeDocument->documents->document . '.' . $extension;

        AuditLogger::log('download', $employeeDocument, [
            'path' => $employeeDocument->file_path,
            'filename' => $downloadName,
        ]);

        return Storage::disk('local')->download(
            $employeeDocument->file_path,
            $downloadName,
        );
    }
}
