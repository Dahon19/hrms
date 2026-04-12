<?php

namespace App\Http\Controllers;

use App\Http\Requests\PdsSectionRequest;
use App\Models\Employee;
use App\Models\PdsProfile;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PdsController extends Controller
{
    private const COMPLETION_REQUIRED_PERSONAL_FIELDS = [
        'last_name' => 'Last Name',
        'first_name' => 'First Name',
        'birth_date' => 'Birth Date',
        'sex' => 'Sex',
        'civil_status' => 'Civil Status',
        'citizenship' => 'Citizenship',
    ];

    private const SECTION_LABELS = [
        'personal-information' => 'Personal Information',
        'family-background' => 'Family Background',
        'education-background' => 'Education Background',
        'civil-service-eligibility' => 'Civil Service Eligibility',
        'work-experience' => 'Work Experience',
        'voluntary-work' => 'Voluntary Work',
        'learning-development' => 'Learning & Development',
        'other-information' => 'Other Information',
    ];

    private const SECTIONS = [
        'personal-information',
        'family-background',
        'education-background',
        'civil-service-eligibility',
        'work-experience',
        'voluntary-work',
        'learning-development',
        'other-information',
    ];

    private const EMPLOYEE_EDITABLE_SECTIONS = [
        'family-background',
        'education-background',
        'civil-service-eligibility',
        'work-experience',
        'voluntary-work',
        'learning-development',
        'other-information',
    ];

    private const EMPLOYEE_EDITABLE_PERSONAL_INFO_KEYS = [
        'birth_date',
        'birth_place',
        'civil_status',
        'citizenship',
        'height_m',
        'weight_kg',
        'blood_type',
        'gsis_no',
        'sss_no',
        'tin_no',
        'philhealth_no',
        'residential_address',
        'permanent_address',
        'telephone_no',
        'mobile_no',
    ];

    /**
     * Employee master -> official CS Form No. 212 (Personal Information) field map.
     * Only fields that belong to the official form are included.
     */
    private const CS212_EMPLOYEE_TO_PERSONAL_MAP = [
        'last_name' => 'last_name',
        'first_name' => 'first_name',
        'middle_name' => 'middle_name',
        'name_extension' => 'suffix',
        'birth_date' => 'birth_date',
        'birth_place' => 'birth_place',
        'sex' => 'sex',
        'civil_status' => 'civil_status',
        'citizenship' => 'citizenship',
        'height_m' => 'height_m',
        'weight_kg' => 'weight_kg',
        'blood_type' => 'blood_type',
        'gsis_no' => 'gsis_no',
        'sss_no' => 'sss_no',
        'tin_no' => 'tin_no',
        'philhealth_no' => 'philhealth_no',
        'residential_address' => 'residential_address',
        'permanent_address' => 'permanent_address',
        'telephone_no' => 'telephone_no',
        'mobile_no' => 'mobile_no',
        'email_address' => 'email',
    ];

    private const PERSONAL_INFO_ALLOWED_KEYS = [
        'last_name',
        'first_name',
        'middle_name',
        'name_extension',
        'birth_date',
        'birth_place',
        'sex',
        'civil_status',
        'citizenship',
        'height_m',
        'weight_kg',
        'blood_type',
        'gsis_no',
        'sss_no',
        'tin_no',
        'philhealth_no',
        'residential_address',
        'permanent_address',
        'telephone_no',
        'mobile_no',
        'email_address',
    ];

    private function pdsEditingBlockedMessage(): string
    {
        return 'PDS is read-only while the employee is in offboarding.';
    }

    private function isPdsEditingBlocked(Employee $employee): bool
    {
        return $employee->hasActiveOffboardingRecord();
    }

    public function index(Request $request)
    {
        Gate::authorize('view-pds');

        $user = $request->user();
        if (!$this->canManageDirectory($user) && $user?->employee) {
            return redirect()->route('pds.show', $user->employee);
        }

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $query = Employee::query()
            ->with(['user', 'department', 'pdsProfile'])
            ->whereHas('user', function ($q) {
                $q->whereNull('archived_at');
            });

        if (!$this->canManageDirectory($user)) {
            $query->where('id', $user?->employee?->id ?? 0);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%');
            });
        }

        if ($this->canManageDirectory($user) && in_array($status, ['draft', 'submitted', 'needs_correction', 'verified'], true)) {
            if ($status === 'draft') {
                $query->where(function ($q) {
                    $q->doesntHave('pdsProfile')
                        ->orWhereHas('pdsProfile', function ($profileQuery) {
                            $profileQuery->where('status', 'draft');
                        });
                });
            } else {
                $query->whereHas('pdsProfile', function ($q) use ($status) {
                    $q->where('status', $status);
                });
            }
        }

        $employees = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('pds.index', compact('employees', 'search', 'status'));
    }

    public function show(Request $request, Employee $employee)
    {
        Gate::authorize('view-pds', $employee);

        $employee->loadMissing(['user']);
        $profile = $this->loadProfile($employee, $this->canManageDirectory($request->user()));
        $sectionCompletion = $this->syncSectionCompletion($employee, $profile);
        $isVerified = $profile->status === 'verified';
        $canManage = $this->canManageDirectory($request->user());
        $canVerify = $this->canVerifyAction($request->user(), $profile);
        $canSelfManage = $this->canEmployeeSelfManagePds($request->user(), $employee);
        $isReadOnlyByOffboarding = $this->isPdsEditingBlocked($employee);
        $canEdit = $this->canEditRecord($request->user(), $employee, $profile);
        $canEditPersonalInformation = $this->canEditPersonalInformation($request->user(), $employee, $profile);
        $employeeCanEditPersonalInformationSubset = $this->canEmployeeEditPersonalInformationSubset($request->user(), $employee, $profile);
        $editableSections = !$canEdit ? [] : self::EMPLOYEE_EDITABLE_SECTIONS;
        $freezeMasterFallback = $isVerified;
        $personalInfoDefaults = $this->buildPersonalInfoDefaults($employee, $profile, $freezeMasterFallback);

        return view('pds.show', [
            'employee' => $employee,
            'profile' => $profile,
            'sections' => self::SECTIONS,
            'canManage' => $canManage,
            'canVerify' => $canVerify,
            'canSelfManage' => $canSelfManage,
            'canEdit' => $canEdit,
            'canEditPersonalInformation' => $canEditPersonalInformation,
            'employeeCanEditPersonalInformationSubset' => $employeeCanEditPersonalInformationSubset,
            'employeeEditablePersonalInfoKeys' => self::EMPLOYEE_EDITABLE_PERSONAL_INFO_KEYS,
            'canSubmit' => $this->canSubmitRecord($request->user(), $employee, $profile),
            'editableSections' => $editableSections,
            'sectionCompletion' => $sectionCompletion,
            'personalInfoDefaults' => $personalInfoDefaults,
            'isReadOnlyByOffboarding' => $isReadOnlyByOffboarding,
        ]);
    }

    public function saveSection(PdsSectionRequest $request, Employee $employee, string $section)
    {
        if (!in_array($section, self::SECTIONS, true)) {
            abort(404);
        }

        [$profile, $completion] = DB::transaction(function () use ($request, $employee, $section) {
            $profile = PdsProfile::query()->where('employee_id', $employee->id)->lockForUpdate()->first();
            if (!$profile) {
                $profile = PdsProfile::create([
                    'employee_id' => $employee->id,
                    'status' => 'draft',
                    'last_encoded_by' => $request->user()->id,
                    'section_completion' => [],
                ]);
            }

            if ($section === 'personal-information') {
                if (!$this->canEditPersonalInformation($request->user(), $employee, $profile)) {
                    throw new AuthorizationException('Personal Information updates must be requested through HR Head.');
                }
            } elseif (!$this->canEditRecord($request->user(), $employee, $profile)) {
                throw new AuthorizationException('PDS cannot be edited from the current state.');
            }
            $payload = $this->sanitizeSectionPayload($section, $request->validated(), $request->user(), $employee);
            match ($section) {
                'personal-information' => $this->savePersonalInformation($profile, $payload),
                'family-background' => $this->saveFamilyBackground($profile, $payload),
                'education-background' => $this->saveEducationBackground($profile, $payload),
                'civil-service-eligibility' => $this->saveCivilServiceEligibility($profile, $payload),
                'work-experience' => $this->saveWorkExperience($profile, $payload),
                'voluntary-work' => $this->saveVoluntaryWork($profile, $payload),
                'learning-development' => $this->saveLearningDevelopment($profile, $payload),
                'other-information' => $this->saveOtherInformation($profile, $payload),
                default => null,
            };

            $profile->status = in_array($profile->status, ['draft', 'needs_correction'], true) ? $profile->status : 'draft';
            $profile->last_encoded_by = $request->user()->id;
            $profile->save();
            $completion = $this->syncSectionCompletion($employee, $profile);

            AuditLogger::logSystem('pds_section_saved', [
                'section' => $section,
                'employee_id' => $employee->id,
                'profile_id' => $profile->id,
            ], $request->user()->id, PdsProfile::class, $profile->id);

            return [$profile, $completion];
        });

        $message = ucfirst(str_replace('-', ' ', $section)) . ' saved as draft.';
        $readyToSubmit = $this->canSubmitRecord($request->user(), $employee, $profile)
            && $this->isReadyForSubmission($employee, $profile, $completion);

        return $request->expectsJson()
            ? response()->json([
                'message' => $message,
                'profile_id' => $profile->id,
                'section_completion' => $completion,
                'status' => $profile->status,
                'ready_to_submit' => $readyToSubmit,
            ])
            : back()->with('success', $message);
    }

    public function submit(Request $request, Employee $employee)
    {
        $profile = $this->loadProfile($employee, true);
        if (!$this->canSubmitRecord($request->user(), $employee, $profile)) {
            $message = 'PDS cannot be submitted from current status.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $missingFields = $this->missingCompletionRequirements($employee, $profile);
        if (!empty($missingFields)) {
            $message = 'Please complete required personal fields first: ' . implode(', ', $missingFields);
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $completion = $this->computeSectionCompletion($employee, $profile);
        $missingSections = collect(self::SECTIONS)->reject(function ($section) use ($completion) {
            return (bool) ($completion[$section] ?? false);
        })->map(fn (string $section) => self::SECTION_LABELS[$section] ?? $section)->values()->all();

        if (!empty($missingSections)) {
            $message = 'Please save all sections before submission: ' . implode(', ', $missingSections);
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $profile->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $request->user()->id,
            'verified_at' => null,
            'verified_by' => null,
            'hr_remarks' => null,
            'correction_requested_at' => null,
            'correction_requested_by' => null,
            'last_encoded_by' => $request->user()->id,
        ]);

        AuditLogger::logSystem('pds_submitted', [
            'employee_id' => $employee->id,
            'profile_id' => $profile->id,
        ], $request->user()->id, PdsProfile::class, $profile->id);

        $message = 'PDS submitted for HR verification.';

        return $request->expectsJson()
            ? response()->json([
                'message' => $message,
                'status' => 'submitted',
            ])
            : back()->with('success', $message);
    }

    public function verify(Request $request, Employee $employee)
    {
        $profile = $this->loadProfile($employee, false);
        if ((int) ($profile->employee_id ?? 0) !== (int) $employee->id) {
            return back()->with('error', 'No PDS record found for this employee.');
        }

        if (!$this->canVerifyAction($request->user(), $profile)) {
            return back()->with('error', 'Only submitted PDS records can be verified.');
        }

        $profile->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
            'hr_remarks' => null,
            'correction_requested_at' => null,
            'correction_requested_by' => null,
        ]);

        AuditLogger::logSystem('pds_verified', [
            'employee_id' => $employee->id,
            'profile_id' => $profile->id,
        ], $request->user()->id, PdsProfile::class, $profile->id);

        return back()->with('success', 'PDS verified.');
    }

    public function requestCorrection(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'hr_remarks' => ['required', 'string', 'max:1000'],
        ]);

        $profile = $this->loadProfile($employee, false);
        if ((int) ($profile->employee_id ?? 0) !== (int) $employee->id) {
            return back()->with('error', 'No PDS record found for this employee.');
        }

        if (!$this->canRequestCorrectionAction($request->user(), $profile)) {
            return back()->with('error', 'PDS can only be returned for correction after employee completion.');
        }

        $profile->update([
            'status' => 'needs_correction',
            'verified_at' => null,
            'verified_by' => null,
            'hr_remarks' => trim((string) $validated['hr_remarks']),
            'correction_requested_at' => now(),
            'correction_requested_by' => $request->user()->id,
        ]);

        AuditLogger::logSystem('pds_correction_requested', [
            'employee_id' => $employee->id,
            'profile_id' => $profile->id,
        ], $request->user()->id, PdsProfile::class, $profile->id);

        return back()->with('success', 'PDS returned for correction.');
    }

    public function print(Request $request, Employee $employee)
    {
        Gate::authorize('view-pds', $employee);

        $employee->loadMissing(['user']);
        $profile = $this->loadProfile($employee, false);
        $isVerified = $profile->status === 'verified';
        $personalInfoDefaults = $this->buildPersonalInfoDefaults($employee, $profile, $isVerified);

        $pdf = Pdf::loadView('pds.print', compact('employee', 'profile', 'personalInfoDefaults'))
            ->setPaper([0, 0, 612, 936], 'portrait');

        $filename = 'pds-' . $employee->employee_id . '.pdf';
        return $pdf->stream($filename, ['Attachment' => false]);
    }

    private function loadProfile(Employee $employee, bool $createIfMissing): PdsProfile
    {
        $query = PdsProfile::with([
            'personalInfo',
            'familyBackground',
            'children',
            'educations',
            'civilServiceEligibilities',
            'workExperiences',
            'voluntaryWorks',
            'trainings',
            'otherInfos',
            'submitter',
            'verifier',
            'correctionRequester',
            'lastEncoder',
        ])->where('employee_id', $employee->id);

        $profile = $query->first();
        if ($profile) {
            return $profile;
        }

        if (!$createIfMissing) {
            return new PdsProfile([
                'employee_id' => $employee->id,
                'status' => 'draft',
                'section_completion' => [],
            ]);
        }

        return PdsProfile::create([
            'employee_id' => $employee->id,
            'status' => 'draft',
            'section_completion' => [],
        ]);
    }

    private function savePersonalInformation(PdsProfile $profile, array $data): void
    {
        $profile->personalInfo()->updateOrCreate([], $data);
    }

    private function saveFamilyBackground(PdsProfile $profile, array $data): void
    {
        $children = collect($data['children'] ?? [])->map(function ($item) {
            return [
                'id' => isset($item['id']) ? (int) $item['id'] : null,
                'full_name' => trim((string) ($item['full_name'] ?? '')),
                'birth_date' => $item['birth_date'] ?? null,
            ];
        })->filter(fn ($item) => $item['full_name'] !== '')->values()->all();

        unset($data['children']);
        $profile->familyBackground()->updateOrCreate([], $data);
        $this->syncRepeatable($profile->children(), $children, ['full_name', 'birth_date']);
    }

    private function saveEducationBackground(PdsProfile $profile, array $data): void
    {
        $entries = $this->normalizeEntries($data['entries'] ?? [], ['education_level', 'school_name', 'degree_course', 'date_from', 'date_to', 'highest_level_units', 'year_graduated', 'honors_received']);
        $this->syncRepeatable($profile->educations(), $entries, ['education_level', 'school_name', 'degree_course', 'date_from', 'date_to', 'highest_level_units', 'year_graduated', 'honors_received']);
    }

    private function saveCivilServiceEligibility(PdsProfile $profile, array $data): void
    {
        $entries = $this->normalizeEntries($data['entries'] ?? [], ['eligibility_type', 'rating', 'exam_date', 'exam_place', 'license_number', 'validity_date']);
        $this->syncRepeatable($profile->civilServiceEligibilities(), $entries, ['eligibility_type', 'rating', 'exam_date', 'exam_place', 'license_number', 'validity_date']);
    }

    private function saveWorkExperience(PdsProfile $profile, array $data): void
    {
        $entries = $this->normalizeEntries($data['entries'] ?? [], ['date_from', 'date_to', 'position_title', 'department_office', 'salary_grade', 'appointment_status', 'sector']);
        $this->syncRepeatable($profile->workExperiences(), $entries, ['date_from', 'date_to', 'position_title', 'department_office', 'salary_grade', 'appointment_status', 'sector']);
    }

    private function saveVoluntaryWork(PdsProfile $profile, array $data): void
    {
        $entries = $this->normalizeEntries($data['entries'] ?? [], ['organization_name', 'date_from', 'date_to', 'hours', 'position_nature']);
        $this->syncRepeatable($profile->voluntaryWorks(), $entries, ['organization_name', 'date_from', 'date_to', 'hours', 'position_nature']);
    }

    private function saveLearningDevelopment(PdsProfile $profile, array $data): void
    {
        $entries = $this->normalizeEntries($data['entries'] ?? [], ['title', 'date_from', 'date_to', 'hours', 'training_type', 'conducted_by']);
        $this->syncRepeatable($profile->trainings(), $entries, ['title', 'date_from', 'date_to', 'hours', 'training_type', 'conducted_by']);
    }

    private function saveOtherInformation(PdsProfile $profile, array $data): void
    {
        $payload = collect([
            'special_skill' => $data['special_skills'] ?? [],
            'recognition' => $data['recognitions'] ?? [],
            'membership' => $data['memberships'] ?? [],
        ])->flatMap(function ($items, $type) {
            return collect($items)->map(function ($description) use ($type) {
                return [
                    'info_type' => $type,
                    'description' => trim((string) $description),
                ];
            })->filter(fn ($row) => $row['description'] !== '');
        })->values();

        $profile->otherInfos()->delete();
        if ($payload->isNotEmpty()) {
            $profile->otherInfos()->createMany($payload->all());
        }
    }

    private function normalizeEntries(array $entries, array $keys): array
    {
        return collect($entries)
            ->map(function ($entry) use ($keys) {
                $row = [
                    'id' => isset($entry['id']) && is_numeric($entry['id']) ? (int) $entry['id'] : null,
                ];
                foreach ($keys as $key) {
                    $value = $entry[$key] ?? null;
                    $row[$key] = is_string($value) ? trim($value) : $value;
                }

                return $row;
            })
            ->filter(function ($row) {
                return collect($row)->contains(function ($value) {
                    return !is_null($value) && $value !== '';
                });
            })
            ->values()
            ->all();
    }

    private function syncRepeatable($relation, array $entries, array $keys): void
    {
        $existing = $relation->withTrashed()->get()->keyBy('id');
        $keptIds = [];

        foreach ($entries as $entry) {
            $id = isset($entry['id']) && is_numeric($entry['id']) ? (int) $entry['id'] : null;
            $payload = [];
            foreach ($keys as $key) {
                $payload[$key] = $entry[$key] ?? null;
            }

            if ($id && $existing->has($id)) {
                $model = $existing->get($id);
                if (method_exists($model, 'trashed') && $model->trashed()) {
                    $model->restore();
                }
                $model->fill($payload);
                $model->save();
                $keptIds[] = $model->id;
                continue;
            }

            $created = $relation->create($payload);
            $keptIds[] = $created->id;
        }

        $toDelete = empty($keptIds)
            ? $relation->get()
            : $relation->whereNotIn('id', $keptIds)->get();

        foreach ($toDelete as $row) {
            $row->delete();
        }
    }

    private function buildPersonalInfoDefaults(Employee $employee, PdsProfile $profile, bool $freezeMasterFallback = false): array
    {
        $info = $profile->personalInfo;
        $employeeAttrs = $employee->getAttributes();
        $user = $employee->user;

        $resolve = static function ($pdsValue, $masterValue) use ($freezeMasterFallback) {
            if (!is_null($pdsValue) && $pdsValue !== '') {
                return $pdsValue;
            }

            if ($freezeMasterFallback) {
                return '';
            }

            return $masterValue ?? '';
        };

        $sexFromMaster = data_get($employeeAttrs, self::CS212_EMPLOYEE_TO_PERSONAL_MAP['sex']) ?? $user?->gender;
        if (is_string($sexFromMaster)) {
            $normalized = strtolower(trim($sexFromMaster));
            if (in_array($normalized, ['m', 'male'], true)) {
                $sexFromMaster = 'male';
            } elseif (in_array($normalized, ['f', 'female'], true)) {
                $sexFromMaster = 'female';
            }
        }

        $fromMaster = static function (string $pdsField) use ($employeeAttrs, $user) {
            $source = self::CS212_EMPLOYEE_TO_PERSONAL_MAP[$pdsField] ?? null;
            if (!$source) {
                return null;
            }

            if (str_starts_with($source, 'user.')) {
                return data_get($user, substr($source, 5));
            }

            if ($source === 'email') {
                return $user?->email;
            }

            return data_get($employeeAttrs, $source);
        };

        return [
            'last_name' => $resolve($info?->last_name, $fromMaster('last_name')),
            'first_name' => $resolve($info?->first_name, $fromMaster('first_name')),
            'middle_name' => $resolve($info?->middle_name, $fromMaster('middle_name')),
            'name_extension' => $resolve($info?->name_extension, $fromMaster('name_extension')),
            'birth_date' => $resolve(optional($info?->birth_date)->toDateString(), $fromMaster('birth_date')),
            'birth_place' => $resolve($info?->birth_place, $fromMaster('birth_place')),
            'sex' => $resolve($info?->sex, $sexFromMaster),
            'civil_status' => $resolve($info?->civil_status, $fromMaster('civil_status')),
            'citizenship' => $resolve($info?->citizenship, $fromMaster('citizenship')),
            'height_m' => $resolve($info?->height_m, $fromMaster('height_m')),
            'weight_kg' => $resolve($info?->weight_kg, $fromMaster('weight_kg')),
            'blood_type' => $resolve($info?->blood_type, $fromMaster('blood_type')),
            'gsis_no' => $resolve($info?->gsis_no, $fromMaster('gsis_no')),
            'sss_no' => $resolve($info?->sss_no, $fromMaster('sss_no')),
            'tin_no' => $resolve($info?->tin_no, $fromMaster('tin_no')),
            'philhealth_no' => $resolve($info?->philhealth_no, $fromMaster('philhealth_no')),
            'residential_address' => $resolve($info?->residential_address, $fromMaster('residential_address')),
            'permanent_address' => $resolve($info?->permanent_address, $fromMaster('permanent_address')),
            'telephone_no' => $resolve($info?->telephone_no, $fromMaster('telephone_no')),
            'mobile_no' => $resolve($info?->mobile_no, $fromMaster('mobile_no')),
            'email_address' => $resolve($info?->email_address, $fromMaster('email_address')),
        ];
    }

    private function sanitizeSectionPayload(string $section, array $payload, User $user, Employee $employee): array
    {
        return match ($section) {
            'personal-information' => Arr::only(
                $payload,
                $this->canEmployeeEditPersonalInformationSubset($user, $employee)
                    ? self::EMPLOYEE_EDITABLE_PERSONAL_INFO_KEYS
                    : self::PERSONAL_INFO_ALLOWED_KEYS
            ),
            'family-background' => Arr::only($payload, [
                'spouse_last_name', 'spouse_first_name', 'spouse_middle_name',
                'spouse_occupation', 'spouse_employer', 'spouse_business_address', 'spouse_telephone',
                'father_last_name', 'father_first_name', 'father_middle_name', 'father_name_extension',
                'mother_last_name', 'mother_first_name', 'mother_middle_name', 'children',
            ]),
            'education-background', 'civil-service-eligibility', 'work-experience', 'voluntary-work', 'learning-development'
                => Arr::only($payload, ['entries']),
            'other-information' => Arr::only($payload, ['special_skills', 'recognitions', 'memberships']),
            default => [],
        };
    }

    private function canEditPds(User $user, Employee $employee): bool
    {
        return $this->canEmployeeSelfManagePds($user, $employee);
    }

    private function canManageDirectory(User $user): bool
    {
        return $user->isAdmin() || AccessControl::isHrHead($user);
    }

    private function canEmployeeSelfManagePds(User $user, Employee $employee): bool
    {
        if (!$this->isOwnEmployee($user, $employee)) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        return (bool) $user->employee;
    }

    private function canEditRecord(User $user, Employee $employee, PdsProfile $profile): bool
    {
        if ($this->isPdsEditingBlocked($employee) || $profile->status === 'verified') {
            return false;
        }

        if (!$this->canEmployeeSelfManagePds($user, $employee)) {
            return false;
        }

        return in_array($profile->status, ['draft', 'needs_correction'], true);
    }

    private function canEditPersonalInformation(User $user, Employee $employee, PdsProfile $profile): bool
    {
        if ($this->isPdsEditingBlocked($employee) || $profile->status === 'verified') {
            return false;
        }

        if (AccessControl::isHrHead($user)) {
            return in_array($profile->status, ['draft', 'needs_correction', 'submitted'], true);
        }

        return $this->canEmployeeEditPersonalInformationSubset($user, $employee, $profile);
    }

    private function canEmployeeEditPersonalInformationSubset(User $user, Employee $employee, ?PdsProfile $profile = null): bool
    {
        if (!$this->canEmployeeSelfManagePds($user, $employee)) {
            return false;
        }

        if ($this->isPdsEditingBlocked($employee)) {
            return false;
        }

        if (!$profile) {
            return true;
        }

        return in_array($profile->status, ['draft', 'needs_correction'], true);
    }

    private function canSubmitRecord(User $user, Employee $employee, PdsProfile $profile): bool
    {
        return $this->canEditRecord($user, $employee, $profile);
    }

    private function canVerifyAction(User $user, PdsProfile $profile): bool
    {
        return $this->canManageDirectory($user)
            && $profile->status === 'submitted'
            && (int) ($user->employee?->id ?? 0) !== (int) $profile->employee_id;
    }

    private function canRequestCorrectionAction(User $user, PdsProfile $profile): bool
    {
        return $this->canManageDirectory($user)
            && $profile->status === 'submitted'
            && (int) ($user->employee?->id ?? 0) !== (int) $profile->employee_id;
    }

    private function isOwnEmployee(?User $user, Employee $employee): bool
    {
        return (int) ($user?->employee?->id ?? 0) === (int) $employee->id;
    }

    private function missingCompletionRequirements(Employee $employee, PdsProfile $profile): array
    {
        $resolved = $this->buildPersonalInfoDefaults($employee, $profile, false);
        $missing = [];

        foreach (self::COMPLETION_REQUIRED_PERSONAL_FIELDS as $field => $label) {
            $value = trim((string) ($resolved[$field] ?? ''));
            if ($value === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private function computeSectionCompletion(Employee $employee, PdsProfile $profile): array
    {
        $family = $profile->familyBackground;
        $familyHasData = false;
        if ($family) {
            $familyHasData = collect([
                $family->spouse_last_name,
                $family->spouse_first_name,
                $family->spouse_middle_name,
                $family->spouse_occupation,
                $family->spouse_employer,
                $family->spouse_business_address,
                $family->spouse_telephone,
                $family->father_last_name,
                $family->father_first_name,
                $family->father_middle_name,
                $family->father_name_extension,
                $family->mother_last_name,
                $family->mother_first_name,
                $family->mother_middle_name,
            ])->contains(fn ($value) => trim((string) $value) !== '');
        }

        $otherInfos = $profile->otherInfos ?? collect();

        return [
            'personal-information' => empty($this->missingCompletionRequirements($employee, $profile)),
            'family-background' => $familyHasData || $profile->children->isNotEmpty(),
            'education-background' => $profile->educations->contains(fn ($row) => $this->rowHasValues($row, ['education_level', 'school_name', 'date_from', 'date_to'])),
            'civil-service-eligibility' => $profile->civilServiceEligibilities->contains(fn ($row) => $this->rowHasValues($row, ['eligibility_type', 'exam_date', 'exam_place'])),
            'work-experience' => $profile->workExperiences->contains(fn ($row) => $this->rowHasValues($row, ['date_from', 'position_title', 'department_office', 'appointment_status'])),
            'voluntary-work' => $profile->voluntaryWorks->contains(fn ($row) => $this->rowHasValues($row, ['organization_name', 'date_from', 'date_to', 'position_nature'])),
            'learning-development' => $profile->trainings->contains(fn ($row) => $this->rowHasValues($row, ['title', 'date_from', 'date_to', 'conducted_by'])),
            'other-information' => $otherInfos->contains(fn ($row) => trim((string) $row->description) !== ''),
        ];
    }

    private function rowHasValues(object $row, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = data_get($row, $field);

            if ($value === null) {
                return false;
            }

            if (is_string($value) && trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function syncSectionCompletion(Employee $employee, PdsProfile $profile): array
    {
        $completion = $this->computeSectionCompletion($employee, $profile);

        if (($profile->section_completion ?? []) !== $completion && $profile->exists) {
            $profile->forceFill(['section_completion' => $completion])->save();
        } else {
            $profile->section_completion = $completion;
        }

        return $completion;
    }

    private function isReadyForSubmission(Employee $employee, PdsProfile $profile, ?array $completion = null): bool
    {
        if (!empty($this->missingCompletionRequirements($employee, $profile))) {
            return false;
        }

        $completion ??= $this->computeSectionCompletion($employee, $profile);

        foreach (self::SECTIONS as $section) {
            if (!($completion[$section] ?? false)) {
                return false;
            }
        }

        return true;
    }

}


