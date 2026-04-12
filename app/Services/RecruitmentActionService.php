<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeePosition;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecruitmentActionService
{
    public function closeExpiredOpenPostings(): int
    {
        return JobPosting::query()
            ->where('status', 'open')
            ->whereNotNull('closing_date')
            ->whereDate('closing_date', '<', today())
            ->update(['status' => 'closed']);
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

    public function ensurePositionBelongsToDepartment(int $departmentId, int $positionId): Position
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

    private function requiredHeadcountFor(JobPosting $posting): int
    {
        return max((int) ($posting->required_headcount ?? 1), 1);
    }

    public function validateClosingDateNotPast(?string $closingDate): void
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

    public function prepareJobPostingPayload(array $validated): array
    {
        $this->validateClosingDateNotPast($validated['closing_date'] ?? null);

        $position = $this->ensurePositionBelongsToDepartment(
            (int) $validated['department_id'],
            (int) $validated['position_id']
        );

        $validated['title'] = $position->position;

        if (!$this->hasRequiredHeadcountColumn()) {
            unset($validated['required_headcount']);
            return $validated;
        }

        if ((int) ($validated['required_headcount'] ?? 1) <= 0) {
            $validated['required_headcount'] = 1;
        }

        return $validated;
    }

    public function createJobPosting(array $payload, array $auditMetadata = []): JobPosting
    {
        $payload = $this->prepareJobPostingPayload($payload);
        $posting = JobPosting::create($payload);

        AuditLogger::log('create', $posting, array_merge([
            'title' => $posting->title,
        ], $auditMetadata));

        return $posting;
    }

    public function updateJobPosting(JobPosting $jobPosting, array $payload, array $auditMetadata = []): JobPosting
    {
        $payload = $this->prepareJobPostingPayload($payload);

        $fulfilledCount = (int) $jobPosting->applicants()
            ->where('status', 'hired')
            ->count();

        $effectiveRequiredHeadcount = $this->hasRequiredHeadcountColumn()
            ? (int) ($payload['required_headcount'] ?? 1)
            : max((int) ($jobPosting->required_headcount ?? 1), 1);

        if ($effectiveRequiredHeadcount < $fulfilledCount) {
            throw ValidationException::withMessages([
                'required_headcount' => 'Required headcount cannot be lower than the fulfilled hires (' . $fulfilledCount . ').',
            ]);
        }

        if (
            ($payload['status'] ?? null) === 'open'
            && $fulfilledCount >= $effectiveRequiredHeadcount
        ) {
            throw ValidationException::withMessages([
                'status' => 'This vacancy cannot be reopened because it is already fully staffed. Increase the required headcount first if you need to reopen it.',
            ]);
        }

        $jobPosting->update($payload);

        AuditLogger::log('update', $jobPosting, array_merge([
            'title' => $jobPosting->title,
        ], $auditMetadata));

        return $jobPosting->refresh();
    }

    public function deleteJobPosting(JobPosting $jobPosting, array $auditMetadata = []): void
    {
        AuditLogger::log('delete', $jobPosting, array_merge([
            'title' => $jobPosting->title,
        ], $auditMetadata));

        $jobPosting->delete();
    }

    public function completeApplicant(Applicant $applicant, array $auditMetadata = []): array
    {
        return $this->hireApplicantRecord($applicant, 'applicant_completed', $auditMetadata);
    }

    public function activateApplicant(Applicant $applicant, array $auditMetadata = []): array
    {
        return $this->hireApplicantRecord($applicant, 'applicant_account_activated', $auditMetadata);
    }

    public function archiveApplicant(Applicant $applicant, array $auditMetadata = []): void
    {
        $applicant->update([
            'status' => 'archived',
            'account_status' => 'inactive',
        ]);

        AuditLogger::logSystem('applicant_archived', array_merge([
            'applicant_name' => $applicant->full_name,
            'applicant_email' => $applicant->email,
        ], $auditMetadata), null, 'Applicant', $applicant->id);
    }

    private function hireApplicantRecord(Applicant $applicant, string $auditEvent, array $auditMetadata = []): array
    {
        $posting = $applicant->jobPosting;
        if (!$posting) {
            throw ValidationException::withMessages([
                'applicant' => 'Applicant has no linked vacancy.',
            ]);
        }

        if ($applicant->status === 'hired' && ($applicant->account_status ?? 'active') === 'active') {
            throw ValidationException::withMessages([
                'applicant' => 'Applicant is already hired for this vacancy.',
            ]);
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

        AuditLogger::logSystem($auditEvent, array_merge([
            'applicant_name' => $applicant->full_name,
            'applicant_email' => $applicant->email,
            'job_title' => $posting->title,
            'auto_archived_applicants' => $autoArchivedCount,
            'vacancy_filled' => $vacancyFilled,
        ], $auditMetadata), null, 'Applicant', $applicant->id);

        return [
            'vacancy_filled' => $vacancyFilled,
            'auto_archived_count' => $autoArchivedCount,
        ];
    }

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

    public function syncMissingDocumentsForHiredApplicants(): void
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
                    'status' => 'verified',
                    'issued_at' => null,
                    'expires_at' => null,
                    'expiry_notified_at' => null,
                ], Schema::hasColumn('employee_documents', 'document_type') ? ['document_type' => 'Permanent'] : []));
            }
        }
    }
}
