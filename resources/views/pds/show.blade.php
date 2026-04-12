@extends ('layouts.admin')

@section ('content')
    @php
    $status = $profile->status;
    $completionMap = $sectionCompletion ?? ($profile->section_completion ?? []);
    $completed = collect($completionMap)->filter()->count();
    $activeFormSection = old('form_section');
    $info = $profile->personalInfo;
    $family = $profile->familyBackground;
    $prefill = $personalInfoDefaults ?? [];
    $editableSections = $editableSections ?? [];
    $canEditSection = fn (string $section) => in_array($section, $editableSections, true);
    $canEditPersonalInformation = $canEditPersonalInformation ?? false;
    $employeeCanEditPersonalInformationSubset = $employeeCanEditPersonalInformationSubset ?? false;
    $employeeEditablePersonalInfoKeys = $employeeEditablePersonalInfoKeys ?? [];
    $personalFieldDisabled = function (string $field) use ($canEditPersonalInformation, $employeeCanEditPersonalInformationSubset, $employeeEditablePersonalInfoKeys) {
        if (!$canEditPersonalInformation) {
            return true;
        }

        if (!$employeeCanEditPersonalInformationSubset) {
            return false;
        }

        return !in_array($field, $employeeEditablePersonalInfoKeys, true);
    };
    $activeTab = match ($activeFormSection) {
        'family-background', 'other-information' => 'background',
        'education-background', 'civil-service-eligibility' => 'qualifications',
        'work-experience', 'voluntary-work', 'learning-development' => 'experience',
        default => 'personal',
    };
@endphp
    <div
        class="container-fluid pt-4"
        id="pdsShowPage"
        data-pds-auto-submit="{{ ($canSubmit ?? false) ? '1' : '0' }}"
        data-pds-submit-url="{{ ($canSubmit ?? false) ? route('pds.submit', $employee) : '' }}"
        data-pds-status="{{ $status }}"
    >
        <x-page-header
            eyebrow="Records Management"
            title="PDS Record"
            subtitle="{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }} #{{ $employee->employee_id }}"
        >
            <x-slot:actions>
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    :href="route('pds.index')"
                    icon="cil-arrow-left"
                >
                    Back
                </x-ui.button>
            </x-slot:actions>
        </x-page-header>

        @if ($isReadOnlyByOffboarding ?? false)
            <div class="alert alert-warning">
                PDS is read-only while the employee is in offboarding.
            </div>
        @endif

        @if (($profile->status ?? '') === 'needs_correction' && !empty($profile->hr_remarks))
            <div class="alert alert-warning pds-remarks-alert">
                <strong>Correction requested by HR.</strong>
                @if ($profile->correctionRequester || $profile->correction_requested_at)
                    <div class="small mt-1 mb-1">
                        {{ $profile->correctionRequester?->name ?? 'HR' }}
                        @if ($profile->correction_requested_at)
                            on {{ $profile->correction_requested_at->format('M d, Y h:i A') }}
                        @endif
                    </div>
                @endif
                {{ $profile->hr_remarks }}
            </div>
        @endif

        <div class="pds-overview-bar">
            <div class="pds-overview-bar__item">
                <span class="pds-overview-label">Completion</span>
                <strong class="pds-overview-bar__value">{{ $completed }}/8</strong>
            </div>
            <div class="pds-overview-bar__item">
                <span class="pds-overview-label">Progress</span>
                <strong class="pds-overview-bar__value">{{ round(($completed / 8) * 100) }}%</strong>
            </div>
            <div class="pds-overview-bar__item pds-overview-bar__item--status">
                <span class="pds-overview-label">Status</span>
                <x-ui.status-badge
                    class="pds-overview-status text-uppercase"
                    :status="$status"
                    :text="str_replace('_', ' ', $status)"
                    :variant="$status === 'verified' ? 'success' : 'warning'"
                />
            </div>
            <div class="pds-overview-bar__progress">
                <div
                    class="d-flex justify-content-between align-items-center mb-1"
                >
                    <small class="pds-overview-meta mb-0">Sections saved in this record</small>
                    <small class="pds-overview-percent mb-0">{{ $completed }} completed</small>
                </div>
                <div class="progress pds-progress">
                    <div
                        class="progress-bar"
                        role="progressbar"
                        style="width: {{ ($completed / 8) * 100 }}%"
                    ></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm pds-record-shell">
            <div class="card-header p-0">
                <ul class="nav nav-tabs pds-tabs" id="pdsTabs" role="tablist">
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $activeTab === 'personal' ? 'active' : '' }}"
                            data-toggle="tab"
                            href="#sec-personal"
                            >A. Personal</a
                        >
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'background' ? 'active' : '' }}" data-toggle="tab" href="#sec-background"
                            >B. Family & Background</a
                        >
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $activeTab === 'qualifications' ? 'active' : '' }}"
                            data-toggle="tab"
                            href="#sec-qualifications"
                            >C. Qualifications</a
                        >
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'experience' ? 'active' : '' }}" data-toggle="tab" href="#sec-experience"
                            >D. Experience & Development</a
                        >
                    </li>
                </ul>
            </div>

            <div class="card-body pds-record-body">
                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeTab === 'personal' ? 'show active' : '' }}" id="sec-personal">
                        <form
                            method="POST"
                            action="{{ route('pds.sections.save', [$employee, 'personal-information']) }}"
                            class="pds-section-form"
                            data-pds-autosave="{{ $canEditPersonalInformation ? '1' : '0' }}"
                            data-pds-section="personal-information"
                        >
                            @csrf
                            @method ('PUT')
                            <input
                                type="hidden"
                                name="form_section"
                                value="personal-information"
                            />

                            @if (!$canEditPersonalInformation && ($canSelfManage ?? false))
                                <div class="alert alert-info mb-3">
                                    Personal Information updates must be requested through HR Head.
                                </div>
                            @endif

                            <fieldset>
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Last Name</label
                                        ><input
                                            name="last_name"
                                            value="{{ old('last_name', $prefill['last_name'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('last_name'))
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>First Name</label
                                        ><input
                                            name="first_name"
                                            value="{{ old('first_name', $prefill['first_name'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('first_name'))
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Middle Name</label
                                        ><input
                                            name="middle_name"
                                            value="{{ old('middle_name', $prefill['middle_name'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('middle_name'))
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Extension</label
                                        ><input
                                            name="name_extension"
                                            value="{{ old('name_extension', $prefill['name_extension'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('name_extension'))
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Birth Date</label
                                        ><input
                                            type="date"
                                            name="birth_date"
                                            value="{{ old('birth_date', $prefill['birth_date'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('birth_date'))
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Birth Place</label
                                        ><input
                                            name="birth_place"
                                            value="{{ old('birth_place', $prefill['birth_place'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('birth_place'))
                                        />
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label>Sex</label
                                        ><select
                                            name="sex"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('sex'))
                                        >
                                            <option value="">-</option>
                                            <option
                                                value="male"
                                                @selected (old('sex', $prefill['sex'] ?? '') === 'male')
                                                >Male
                                            </option>
                                            <option
                                                value="female"
                                                @selected (old('sex', $prefill['sex'] ?? '') === 'female')
                                                >Female
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label>Civil Status</label
                                        ><input
                                            name="civil_status"
                                            value="{{ old('civil_status', $prefill['civil_status'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('civil_status'))
                                        />
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label>Citizenship</label
                                        ><input
                                            name="citizenship"
                                            value="{{ old('citizenship', $prefill['citizenship'] ?? '') }}"
                                            class="form-control"
                                            @disabled($personalFieldDisabled('citizenship'))
                                        />
                                    </div>
                                </div>

                                <details class="pds-details mb-2">
                                    <summary class="font-weight-bold">
                                        Optional identifiers and contact fields
                                    </summary>
                                    <div class="row mt-2">
                                        <div class="col-md-2 form-group">
                                            <label>Height (m)</label
                                            ><input
                                                name="height_m"
                                                value="{{ old('height_m', $prefill['height_m'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('height_m'))
                                            />
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Weight (kg)</label
                                            ><input
                                                name="weight_kg"
                                                value="{{ old('weight_kg', $prefill['weight_kg'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('weight_kg'))
                                            />
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <label>Blood Type</label
                                            ><input
                                                name="blood_type"
                                                value="{{ old('blood_type', $prefill['blood_type'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('blood_type'))
                                            />
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label>GSIS</label
                                            ><input
                                                name="gsis_no"
                                                value="{{ old('gsis_no', $prefill['gsis_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('gsis_no'))
                                            />
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label>SSS</label
                                            ><input
                                                name="sss_no"
                                                value="{{ old('sss_no', $prefill['sss_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('sss_no'))
                                            />
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label>TIN</label
                                            ><input
                                                name="tin_no"
                                                value="{{ old('tin_no', $prefill['tin_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('tin_no'))
                                            />
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label>PhilHealth</label
                                            ><input
                                                name="philhealth_no"
                                                value="{{ old('philhealth_no', $prefill['philhealth_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('philhealth_no'))
                                            />
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>Residential Address</label
                                            ><textarea
                                                name="residential_address"
                                                class="form-control"
                                                rows="2"
                                                @disabled($personalFieldDisabled('residential_address'))
                                                >{{ old('residential_address', $prefill['residential_address'] ?? '') }}</textarea
                                            >
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>Permanent Address</label
                                            ><textarea
                                                name="permanent_address"
                                                class="form-control"
                                                rows="2"
                                                @disabled($personalFieldDisabled('permanent_address'))
                                                >{{ old('permanent_address', $prefill['permanent_address'] ?? '') }}</textarea
                                            >
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Telephone</label
                                            ><input
                                                name="telephone_no"
                                                value="{{ old('telephone_no', $prefill['telephone_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('telephone_no'))
                                            />
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Mobile</label
                                            ><input
                                                name="mobile_no"
                                                value="{{ old('mobile_no', $prefill['mobile_no'] ?? '') }}"
                                                class="form-control"
                                                @disabled($personalFieldDisabled('mobile_no'))
                                            />
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Email</label
                                            ><input
                                                name="email_address"
                                                value="{{ old('email_address', $prefill['email_address'] ?? '') }}"
                                                class="form-control"
                                                @disabled(true)
                                            />
                                        </div>
                                    </div>
                                </details>
                            </fieldset>

                            @if ($canEditPersonalInformation)
                                <div class="pds-section-actions">
                                    <small
                                        class="text-muted mr-3 pds-autosave-status"
                                        data-pds-autosave-status="1"
                                    >
                                        Autosave ready.
                                    </small>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'background' ? 'show active' : '' }}" id="sec-background">
                        <div class="pds-merged-stack">
                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Family Background</h6>
                                    <p class="mb-0">Spouse, parents, and children information.</p>
                                </div>
                        <form
                            method="POST"
                            action="{{ route('pds.sections.save', [$employee, 'family-background']) }}"
                            class="pds-section-form"
                            data-pds-autosave="{{ $canEditSection('family-background') ? '1' : '0' }}"
                            data-pds-section="family-background"
                        >
                            @csrf
                            @method ('PUT')
                            <input
                                type="hidden"
                                name="form_section"
                                value="family-background"
                            />
                            <fieldset
                                @disabled (!$canEditSection('family-background'))
                            >
                                <h6 class="text-uppercase">Spouse</h6>
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Last Name</label
                                        ><input
                                            name="spouse_last_name"
                                            value="{{ old('spouse_last_name', $family?->spouse_last_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>First Name</label
                                        ><input
                                            name="spouse_first_name"
                                            value="{{ old('spouse_first_name', $family?->spouse_first_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Middle Name</label
                                        ><input
                                            name="spouse_middle_name"
                                            value="{{ old('spouse_middle_name', $family?->spouse_middle_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Occupation</label
                                        ><input
                                            name="spouse_occupation"
                                            value="{{ old('spouse_occupation', $family?->spouse_occupation ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Employer</label
                                        ><input
                                            name="spouse_employer"
                                            value="{{ old('spouse_employer', $family?->spouse_employer ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Business Address</label
                                        ><input
                                            name="spouse_business_address"
                                            value="{{ old('spouse_business_address', $family?->spouse_business_address ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Telephone</label
                                        ><input
                                            name="spouse_telephone"
                                            value="{{ old('spouse_telephone', $family?->spouse_telephone ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                </div>

                                <h6 class="text-uppercase">Parents</h6>
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Father Last</label
                                        ><input
                                            name="father_last_name"
                                            value="{{ old('father_last_name', $family?->father_last_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Father First</label
                                        ><input
                                            name="father_first_name"
                                            value="{{ old('father_first_name', $family?->father_first_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Father Middle</label
                                        ><input
                                            name="father_middle_name"
                                            value="{{ old('father_middle_name', $family?->father_middle_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Father Ext</label
                                        ><input
                                            name="father_name_extension"
                                            value="{{ old('father_name_extension', $family?->father_name_extension ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Mother Last</label
                                        ><input
                                            name="mother_last_name"
                                            value="{{ old('mother_last_name', $family?->mother_last_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Mother First</label
                                        ><input
                                            name="mother_first_name"
                                            value="{{ old('mother_first_name', $family?->mother_first_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Mother Middle</label
                                        ><input
                                            name="mother_middle_name"
                                            value="{{ old('mother_middle_name', $family?->mother_middle_name ?? '') }}"
                                            class="form-control"
                                        />
                                    </div>
                                </div>

                                <details class="pds-details pds-details--children mb-2" open>
                                    <summary class="font-weight-bold">
                                        Children
                                    </summary>
                                @php
                                $childrenRows = $activeFormSection === 'family-background'
                                    ? old('children', [])
                                    : $profile->children->map(fn($c) => [
                                        'id' => $c->id,
                                        'full_name' => $c->full_name,
                                        'birth_date' => optional($c->birth_date)->toDateString(),
                                    ])->all();
                                $childrenDisplayCount = max(1, count($childrenRows));
                            @endphp
                                <div
                                    class="pds-children-list"
                                    data-pds-children-list="1"
                                    data-next-index="{{ $childrenDisplayCount }}"
                                    data-can-edit="{{ $canEditSection('family-background') ? '1' : '0' }}"
                                >
                                @for ($i = 0; $i < $childrenDisplayCount; $i++)
                                    <div class="pds-child-row" data-pds-child-row="1" @if ($i === 0) data-pds-locked-row="1" @endif>
                                        <input
                                            type="hidden"
                                            name="children[{{ $i }}][id]"
                                            value="{{ $childrenRows[$i]['id'] ?? '' }}"
                                            data-pds-child-id="1"
                                        />
                                        <div class="form-group pds-child-row__field">
                                            <input
                                                name="children[{{ $i }}][full_name]"
                                                value="{{ $childrenRows[$i]['full_name'] ?? '' }}"
                                                class="form-control"
                                                placeholder="Child Full Name"
                                            />
                                        </div>
                                        <div class="form-group pds-child-row__date">
                                            <input
                                                type="date"
                                                name="children[{{ $i }}][birth_date]"
                                                value="{{ $childrenRows[$i]['birth_date'] ?? '' }}"
                                                class="form-control"
                                            />
                                        </div>
                                        @if ($canEditSection('family-background'))
                                            <div class="form-group pds-child-row__action">
                                                @if ($i === 0)
                                                    <span class="pds-row-action-placeholder" aria-hidden="true"></span>
                                                @else
                                                    <x-ui.button
                                                        type="delete"
                                                        size="sm"
                                                        class="pds-child-remove"
                                                        data-pds-child-remove="1"
                                                        aria-label="Remove child row"
                                                        title="Remove child row"
                                                    />
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endfor
                                </div>
                                @if ($canEditSection('family-background'))
                                    <div class="mt-2 pds-repeatable-actions">
                                        <x-ui.button
                                            type="button"
                                            variant="outline-primary"
                                            size="sm"
                                            icon="cil-plus"
                                            class="pds-repeatable-add"
                                            data-pds-child-add="1"
                                            aria-label="Add child"
                                            title="Add child"
                                        >
                                            Add Child
                                        </x-ui.button>
                                    </div>
                                @endif
                                </details>
                            </fieldset>

                            @if ($canEditSection('family-background'))
                                <div class="pds-section-actions">
                                    <small
                                        class="text-muted mr-3 pds-autosave-status"
                                        data-pds-autosave-status="1"
                                    >
                                        Autosave ready.
                                    </small>
                                </div>
                            @endif
                        </form>
                            </section>

                            @php
                            $specialSkills = $activeFormSection === 'other-information'
                                ? old('special_skills', [])
                                : $profile->otherInfos->where('info_type', 'special_skill')->pluck('description')->values()->all();
                            $recognitions = $activeFormSection === 'other-information'
                                ? old('recognitions', [])
                                : $profile->otherInfos->where('info_type', 'recognition')->pluck('description')->values()->all();
                            $memberships = $activeFormSection === 'other-information'
                                ? old('memberships', [])
                                : $profile->otherInfos->where('info_type', 'membership')->pluck('description')->values()->all();
                        @endphp
                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Other Information</h6>
                                    <p class="mb-0">Skills, recognitions, and membership details.</p>
                                </div>
                        <form
                            method="POST"
                            action="{{ route('pds.sections.save', [$employee, 'other-information']) }}"
                            class="pds-section-form"
                            data-pds-autosave="{{ $canEditSection('other-information') ? '1' : '0' }}"
                            data-pds-section="other-information"
                        >
                            @csrf
                            @method ('PUT')
                            <input
                                type="hidden"
                                name="form_section"
                                value="other-information"
                            />
                            <fieldset
                                @disabled (!$canEditSection('other-information'))
                            >
                                <div class="row">
                                    <div class="col-md-4">
                                        <details class="pds-details pds-details--dense" open>
                                            <summary class="font-weight-bold">
                                                Special Skills
                                            </summary>
                                            @php $specialSkillCount = max(1, count($specialSkills)); @endphp
                                            <div
                                                class="pds-simple-repeatable"
                                                data-pds-simple-list="1"
                                                data-next-index="{{ $specialSkillCount }}"
                                                data-input-name="special_skills"
                                                data-placeholder="Enter special skill"
                                            >
                                                @for ($i = 0; $i < $specialSkillCount; $i++)
                                                    <div class="pds-simple-repeatable__row" data-pds-simple-row="1" @if ($i === 0) data-pds-locked-row="1" @endif>
                                                        <input
                                                            class="form-control form-control-sm"
                                                            name="special_skills[]"
                                                            value="{{ $specialSkills[$i] ?? '' }}"
                                                        />
                                                        @if ($canEditSection('other-information'))
                                                            @if ($i === 0)
                                                                <span class="pds-row-action-placeholder" aria-hidden="true"></span>
                                                            @else
                                                                <x-ui.button
                                                                    type="delete"
                                                                    size="sm"
                                                                    class="pds-simple-repeatable__remove"
                                                                    data-pds-simple-remove="1"
                                                                    aria-label="Remove skill"
                                                                    title="Remove skill"
                                                                />
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endfor
                                            </div>
                                            @if ($canEditSection('other-information'))
                                                <div class="mt-2 pds-repeatable-actions">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="outline-primary"
                                                        size="sm"
                                                        icon="cil-plus"
                                                        class="pds-repeatable-add"
                                                        data-pds-simple-add="1"
                                                        aria-label="Add skill"
                                                        title="Add skill"
                                                    >
                                                        Add Field
                                                    </x-ui.button>
                                                </div>
                                            @endif
                                        </details>
                                    </div>
                                    <div class="col-md-4">
                                        <details class="pds-details pds-details--dense" open>
                                            <summary class="font-weight-bold">
                                                Recognitions
                                            </summary>
                                            @php $recognitionCount = max(1, count($recognitions)); @endphp
                                            <div
                                                class="pds-simple-repeatable"
                                                data-pds-simple-list="1"
                                                data-next-index="{{ $recognitionCount }}"
                                                data-input-name="recognitions"
                                                data-placeholder="Enter recognition"
                                            >
                                                @for ($i = 0; $i < $recognitionCount; $i++)
                                                    <div class="pds-simple-repeatable__row" data-pds-simple-row="1" @if ($i === 0) data-pds-locked-row="1" @endif>
                                                        <input
                                                            class="form-control form-control-sm"
                                                            name="recognitions[]"
                                                            value="{{ $recognitions[$i] ?? '' }}"
                                                        />
                                                        @if ($canEditSection('other-information'))
                                                            @if ($i === 0)
                                                                <span class="pds-row-action-placeholder" aria-hidden="true"></span>
                                                            @else
                                                                <x-ui.button
                                                                    type="delete"
                                                                    size="sm"
                                                                    class="pds-simple-repeatable__remove"
                                                                    data-pds-simple-remove="1"
                                                                    aria-label="Remove recognition"
                                                                    title="Remove recognition"
                                                                />
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endfor
                                            </div>
                                            @if ($canEditSection('other-information'))
                                                <div class="mt-2 pds-repeatable-actions">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="outline-primary"
                                                        size="sm"
                                                        icon="cil-plus"
                                                        class="pds-repeatable-add"
                                                        data-pds-simple-add="1"
                                                        aria-label="Add recognition"
                                                        title="Add recognition"
                                                    >
                                                        Add Field
                                                    </x-ui.button>
                                                </div>
                                            @endif
                                        </details>
                                    </div>
                                    <div class="col-md-4">
                                        <details class="pds-details pds-details--dense" open>
                                            <summary class="font-weight-bold">
                                                Memberships
                                            </summary>
                                            @php $membershipCount = max(1, count($memberships)); @endphp
                                            <div
                                                class="pds-simple-repeatable"
                                                data-pds-simple-list="1"
                                                data-next-index="{{ $membershipCount }}"
                                                data-input-name="memberships"
                                                data-placeholder="Enter membership"
                                            >
                                                @for ($i = 0; $i < $membershipCount; $i++)
                                                    <div class="pds-simple-repeatable__row" data-pds-simple-row="1" @if ($i === 0) data-pds-locked-row="1" @endif>
                                                        <input
                                                            class="form-control form-control-sm"
                                                            name="memberships[]"
                                                            value="{{ $memberships[$i] ?? '' }}"
                                                        />
                                                        @if ($canEditSection('other-information'))
                                                            @if ($i === 0)
                                                                <span class="pds-row-action-placeholder" aria-hidden="true"></span>
                                                            @else
                                                                <x-ui.button
                                                                    type="delete"
                                                                    size="sm"
                                                                    class="pds-simple-repeatable__remove"
                                                                    data-pds-simple-remove="1"
                                                                    aria-label="Remove membership"
                                                                    title="Remove membership"
                                                                />
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endfor
                                            </div>
                                            @if ($canEditSection('other-information'))
                                                <div class="mt-2 pds-repeatable-actions">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="outline-primary"
                                                        size="sm"
                                                        icon="cil-plus"
                                                        class="pds-repeatable-add"
                                                        data-pds-simple-add="1"
                                                        aria-label="Add membership"
                                                        title="Add membership"
                                                    >
                                                        Add Field
                                                    </x-ui.button>
                                                </div>
                                            @endif
                                        </details>
                                    </div>
                                </div>
                            </fieldset>

                            @if ($canEditSection('other-information'))
                                <div class="pds-section-actions">
                                    <small
                                        class="text-muted mr-3 pds-autosave-status"
                                        data-pds-autosave-status="1"
                                    >
                                        Autosave ready.
                                    </small>
                                </div>
                            @endif
                        </form>
                            </section>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'qualifications' ? 'show active' : '' }}" id="sec-qualifications">
                        <div class="pds-merged-stack">
                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Education Background</h6>
                                    <p class="mb-0">Academic history and graduation details.</p>
                                </div>
                        @include ('partials.repeatable-table', [
                        'action' => route('pds.sections.save', [$employee, 'education-background']),
                        'sectionKey' => 'education-background',
                        'headers' => ['Level','School','Degree/Course','From','To','Units','Year','Honors'],
                        'rows' => $activeFormSection === 'education-background'
                            ? old('entries', [])
                            : $profile->educations->map(fn($r) => [
                                'id' => $r->id,
                                'education_level' => $r->education_level,
                                'school_name' => $r->school_name,
                                'degree_course' => $r->degree_course,
                                'date_from' => optional($r->date_from)->toDateString(),
                                'date_to' => optional($r->date_to)->toDateString(),
                                'highest_level_units' => $r->highest_level_units,
                                'year_graduated' => $r->year_graduated,
                                'honors_received' => $r->honors_received,
                            ])->all(),
                        'fields' => ['education_level','school_name','degree_course','date_from','date_to','highest_level_units','year_graduated','honors_received'],
                        'selects' => ['education_level' => ['elementary' => 'Elementary','secondary' => 'Secondary','vocational' => 'Vocational','college' => 'College','graduate' => 'Graduate']],
                        'readOnly' => !$canEditSection('education-background'),
                        'canSave' => $canEditSection('education-background'),
                        'canManage' => $canManage,
                    ])
                            </section>

                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Civil Service Eligibility</h6>
                                    <p class="mb-0">Eligibility, license, and rating records.</p>
                                </div>
                        @include ('partials.repeatable-table', [
                        'action' => route('pds.sections.save', [$employee, 'civil-service-eligibility']),
                        'sectionKey' => 'civil-service-eligibility',
                        'headers' => ['Eligibility Type','Rating','Exam Date','Place','License','Validity'],
                        'rows' => $activeFormSection === 'civil-service-eligibility'
                            ? old('entries', [])
                            : $profile->civilServiceEligibilities->map(fn($r) => [
                                'id' => $r->id,
                                'eligibility_type' => $r->eligibility_type,
                                'rating' => $r->rating,
                                'exam_date' => optional($r->exam_date)->toDateString(),
                                'exam_place' => $r->exam_place,
                                'license_number' => $r->license_number,
                                'validity_date' => optional($r->validity_date)->toDateString(),
                            ])->all(),
                        'fields' => ['eligibility_type','rating','exam_date','exam_place','license_number','validity_date'],
                        'dateFields' => ['exam_date','validity_date'],
                        'readOnly' => !$canEditSection('civil-service-eligibility'),
                        'canSave' => $canEditSection('civil-service-eligibility'),
                        'canManage' => $canManage,
                    ])
                            </section>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'experience' ? 'show active' : '' }}" id="sec-experience">
                        <div class="pds-merged-stack">
                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Work Experience</h6>
                                    <p class="mb-0">Employment history and appointment details.</p>
                                </div>
                        @include ('partials.repeatable-table', [
                        'action' => route('pds.sections.save', [$employee, 'work-experience']),
                        'sectionKey' => 'work-experience',
                        'headers' => ['From','To','Position Title','Department/Office','Salary Grade','Appointment Status','Sector'],
                        'rows' => $activeFormSection === 'work-experience'
                            ? old('entries', [])
                            : $profile->workExperiences->map(fn($r) => [
                                'id' => $r->id,
                                'date_from' => optional($r->date_from)->toDateString(),
                                'date_to' => optional($r->date_to)->toDateString(),
                                'position_title' => $r->position_title,
                                'department_office' => $r->department_office,
                                'salary_grade' => $r->salary_grade,
                                'appointment_status' => $r->appointment_status,
                                'sector' => $r->sector,
                            ])->all(),
                        'fields' => ['date_from','date_to','position_title','department_office','salary_grade','appointment_status','sector'],
                        'dateFields' => ['date_from','date_to'],
                        'selects' => ['sector' => ['government' => 'Government', 'private' => 'Private']],
                        'readOnly' => !$canEditSection('work-experience'),
                        'canSave' => $canEditSection('work-experience'),
                        'canManage' => $canManage,
                    ])
                            </section>

                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Voluntary Work</h6>
                                    <p class="mb-0">Volunteer service and community participation.</p>
                                </div>
                        @include ('partials.repeatable-table', [
                        'action' => route('pds.sections.save', [$employee, 'voluntary-work']),
                        'sectionKey' => 'voluntary-work',
                        'headers' => ['Organization','From','To','Hours','Position/Nature'],
                        'rows' => $activeFormSection === 'voluntary-work'
                            ? old('entries', [])
                            : $profile->voluntaryWorks->map(fn($r) => [
                                'id' => $r->id,
                                'organization_name' => $r->organization_name,
                                'date_from' => optional($r->date_from)->toDateString(),
                                'date_to' => optional($r->date_to)->toDateString(),
                                'hours' => $r->hours,
                                'position_nature' => $r->position_nature,
                            ])->all(),
                        'fields' => ['organization_name','date_from','date_to','hours','position_nature'],
                        'dateFields' => ['date_from','date_to'],
                        'readOnly' => !$canEditSection('voluntary-work'),
                        'canSave' => $canEditSection('voluntary-work'),
                        'canManage' => $canManage,
                    ])
                            </section>

                            <section class="pds-subsection">
                                <div class="pds-subsection__header">
                                    <h6>Learning & Development</h6>
                                    <p class="mb-0">Training, seminars, and professional development records.</p>
                                </div>
                        @include ('partials.repeatable-table', [
                        'action' => route('pds.sections.save', [$employee, 'learning-development']),
                        'sectionKey' => 'learning-development',
                        'headers' => ['Title','From','To','Hours','Type','Conducted By'],
                        'rows' => $activeFormSection === 'learning-development'
                            ? old('entries', [])
                            : $profile->trainings->map(fn($r) => [
                                'id' => $r->id,
                                'title' => $r->title,
                                'date_from' => optional($r->date_from)->toDateString(),
                                'date_to' => optional($r->date_to)->toDateString(),
                                'hours' => $r->hours,
                                'training_type' => $r->training_type,
                                'conducted_by' => $r->conducted_by,
                            ])->all(),
                        'fields' => ['title','date_from','date_to','hours','training_type','conducted_by'],
                        'dateFields' => ['date_from','date_to'],
                        'readOnly' => !$canEditSection('learning-development'),
                        'canSave' => $canEditSection('learning-development'),
                        'canManage' => $canManage,
                    ])
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer pds-record-footer d-flex flex-wrap gap-2">
                @if (($canSubmit ?? false))
                    <small class="text-muted mr-2">
                        PDS will be submitted automatically once all required sections are complete.
                    </small>
                @endif
                @if (($canVerify ?? false) && ($profile->status ?? '') === 'submitted')
                    <form
                        method="POST"
                        action="{{ route('pds.verify', $employee) }}"
                        class="mr-2"
                    >
                        @csrf
                        <button class="btn btn-success btn-sm">Verify PDS</button>
                    </form>
                    <form
                        method="POST"
                        action="{{ route('pds.request-correction', $employee) }}"
                        class="d-flex flex-wrap gap-2 pds-reject-form"
                    >
                        @csrf
                        <input
                            type="text"
                            name="hr_remarks"
                            class="form-control form-control-sm"
                            placeholder="Correction remarks"
                            required
                        />
                        <button class="btn btn-warning btn-sm">Request Correction</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <x-ui.modal
        id="documentPreviewModal"
        size="xl"
        aria-labelledby="documentPreviewModalLabel"
    >
                <x-ui.modal-header
                    title="Document Preview"
                    title-id="documentPreviewModalLabel"
                    class="bg-light"
                />
                <div class="modal-body p-0">
                    <iframe
                        title="Document Preview"
                        src="about:blank"
                        loading="lazy"
                        style="width: 100%; height: 80vh; border: 0"
                    ></iframe>
                </div>
    </x-ui.modal>
@endsection

@push ('scripts')
    <script>
        (function () {
            const page = document.getElementById("pdsShowPage");
            if (!page) return;

            const childrenList = page.querySelector("[data-pds-children-list='1']");
            const addButton = page.querySelector("[data-pds-child-add='1']");
            if (!childrenList || !addButton) return;

                const createChildRow = (index) => {
                const row = document.createElement("div");
                row.className = "pds-child-row";
                row.setAttribute("data-pds-child-row", "1");
                row.innerHTML = `
                    <input type="hidden" name="children[${index}][id]" value="" data-pds-child-id="1" />
                    <div class="form-group pds-child-row__field">
                        <input
                            name="children[${index}][full_name]"
                            class="form-control"
                            placeholder="Child Full Name"
                        />
                    </div>
                    <div class="form-group pds-child-row__date">
                        <input
                            type="date"
                            name="children[${index}][birth_date]"
                            class="form-control"
                        />
                    </div>
                    <div class="form-group pds-child-row__action">
                        <button
                            type="button"
                            class="btn hrms-btn btn-sm btn-danger crud-btn-delete action-btn pds-child-remove"
                            data-pds-child-remove="1"
                            aria-label="Remove child row"
                            title="Remove child row"
                        >
                            <i class="cil-trash hrms-btn__icon" aria-hidden="true"></i>
                        </button>
                    </div>
                `;

                return row;
            };

            addButton.addEventListener("click", function () {
                const nextIndex = Number(childrenList.dataset.nextIndex || "0");
                childrenList.appendChild(createChildRow(nextIndex));
                childrenList.dataset.nextIndex = String(nextIndex + 1);
            });

            childrenList.addEventListener("click", function (event) {
                const removeButton = event.target.closest("[data-pds-child-remove='1']");
                if (!removeButton) return;

                const row = removeButton.closest("[data-pds-child-row='1']");
                if (!row || row.dataset.pdsLockedRow === "1") return;
                row?.remove();
            });
        })();

        (function () {
            const page = document.getElementById("pdsShowPage");
            if (!page) return;

            page.querySelectorAll("[data-pds-repeatable-table='1']").forEach((table) => {
                const tbody = table.querySelector("[data-pds-repeatable-body='1']");
                const template = table.parentElement?.querySelector("[data-pds-repeatable-template='1']");
                const addButton = table.parentElement?.querySelector("[data-pds-repeatable-add='1']");
                if (!tbody || !template || !addButton) return;

                addButton.addEventListener("click", function () {
                    const nextIndex = Number(table.dataset.nextIndex || "0");
                    const html = template.innerHTML.replaceAll("__INDEX__", String(nextIndex));
                    tbody.insertAdjacentHTML("beforeend", html);
                    table.dataset.nextIndex = String(nextIndex + 1);
                });

                tbody.addEventListener("click", function (event) {
                    const removeButton = event.target.closest("[data-pds-repeatable-remove='1']");
                    if (!removeButton) return;
                    if (removeButton.closest("[data-pds-locked-row='1']")) return;

                    removeButton.closest("[data-pds-repeatable-row='1']")?.remove();
                });
            });

            page.querySelectorAll("[data-pds-simple-list='1']").forEach((list) => {
                const addButton = list.parentElement?.querySelector("[data-pds-simple-add='1']");
                if (!addButton) return;

                addButton.addEventListener("click", function () {
                    const placeholder = list.dataset.placeholder || "";
                    const row = document.createElement("div");
                    row.className = "pds-simple-repeatable__row";
                    row.setAttribute("data-pds-simple-row", "1");
                    row.innerHTML = `
                        <input class="form-control form-control-sm" name="${list.dataset.inputName}[]" placeholder="${placeholder}" />
                        <button
                            type="button"
                            class="btn hrms-btn btn-sm btn-danger crud-btn-delete action-btn pds-simple-repeatable__remove"
                            data-pds-simple-remove="1"
                            aria-label="Remove row"
                            title="Remove row"
                        >
                            <i class="cil-trash hrms-btn__icon" aria-hidden="true"></i>
                        </button>
                    `;
                    list.appendChild(row);
                });

                list.addEventListener("click", function (event) {
                    const removeButton = event.target.closest("[data-pds-simple-remove='1']");
                    if (!removeButton) return;
                    if (removeButton.closest("[data-pds-locked-row='1']")) return;

                    removeButton.closest("[data-pds-simple-row='1']")?.remove();
                });
            });
        })();
    </script>
@endpush
