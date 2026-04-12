@extends ('layouts.admin')
@section ('content')
    <div
        id="jobPostingsIndexPage"
        data-page="job-postings.index"
        data-create-error="{{ $errors->any() && old('form_context') === 'job_create' ? '1' : '0' }}"
        data-edit-error="{{ $errors->any() && old('form_context') === 'job_edit' ? '1' : '0' }}"
        data-old-create-position-id="{{ old('form_context') === 'job_create' ? old('position_id') : '' }}"
        data-old-edit-position-id="{{ old('form_context') === 'job_edit' ? old('position_id') : '' }}"
        data-positions-endpoint-template="{{ route('job-postings.positions', ['department' => '__DEPT__']) }}"
        data-edit-endpoint-template="{{ route('job-postings.edit-data', ['jobPosting' => '__ID__']) }}"
    >
        @php
            $canReviewApprovals = $canReviewApprovals ?? false;
            $pendingPostingApprovals = $pendingPostingApprovals ?? collect();
            $isCreateFormContext = $errors->any() && old('form_context') === 'job_create';
            $isEditFormContext = $errors->any() && old('form_context') === 'job_edit';
        @endphp
        <x-ui.hero
            title="Job Postings"
            subtitle="Manage vacancies, publishing status, and applicant-facing listings."
        >
            <x-slot:actions>
                @if ($canReviewApprovals && $pendingPostingApprovals->isNotEmpty())
                    <span class="badge badge-soft-warning px-3 py-2">
                        {{ $pendingPostingApprovals->count() }} pending request{{ $pendingPostingApprovals->count() === 1 ? '' : 's' }}
                    </span>
                @endif
                <x-ui.button
                    type="create"
                    size="sm"
                    icon="cil-plus"
                    data-toggle="modal"
                    data-target="#jobPostingCreateModal"
                >
                    Create Job Posting
                </x-ui.button>
            </x-slot:actions>
        </x-ui.hero>
        <x-ui.table-card
                title="Vacancy Directory"
                subtitle="Staff-submitted posting requests appear first so HR can approve or decline them immediately."
                class="hrms-list-card"
            >
                <x-slot:controls>
                    @php $showJobPostingAdvancedFilters = filled($staffing); @endphp
                    <div
                        class="job-postings-index-toolbar"
                        id="jobPostingsToolbarForm"
                    >
                        <div class="job-postings-filter-shell">
                            <div class="job-postings-filter-primary">
                                <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search job-postings-filter-search">
                                    <label class="ui-toolbar__label" for="toolbar-search-posting_search">Search</label>
                                    <input
                                        id="toolbar-search-posting_search"
                                        type="search"
                                        class="form-control form-control-sm"
                                        placeholder="Search vacancy"
                                    />
                                </div>
                                <div class="job-postings-filter-toggle-wrap">
                                    <label class="ui-toolbar__label job-postings-filter-toggle-label" for="jobPostingsFiltersToggle">Filters</label>
                                    <x-ui.button
                                        type="button"
                                        :variant="$showJobPostingAdvancedFilters ? 'primary' : 'outline-secondary'"
                                        size="sm"
                                        icon="cil-filter"
                                        id="jobPostingsFiltersToggle"
                                        class="job-postings-filter-toggle"
                                        data-coreui-toggle="collapse"
                                        data-coreui-target="#jobPostingsFiltersCollapse"
                                        aria-expanded="{{ $showJobPostingAdvancedFilters ? 'true' : 'false' }}"
                                        aria-controls="jobPostingsFiltersCollapse"
                                    >
                                        Filters
                                    </x-ui.button>
                                </div>
                            </div>

                            <div id="jobPostingsFiltersCollapse" class="job-postings-filter-panel collapse {{ $showJobPostingAdvancedFilters ? 'show' : '' }}">
                                <div class="offcanvas-body job-postings-filter-offcanvas-body">
                                    <div class="job-postings-filter-grid">
                                        <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                            <label class="ui-toolbar__label" for="toolbar-filter-staffing">Staffing</label>
                                            <select
                                                id="toolbar-filter-staffing"
                                                class="form-control form-control-sm select2bs4"
                                                data-toolbar-select2="1"
                                                data-placeholder="All Slots"
                                                data-allow-clear="1"
                                            >
                                                <option value=""></option>
                                                <option value="fully_staffed" @selected($staffing === 'fully_staffed')>Fully Staffed</option>
                                                <option value="partially_filled" @selected($staffing === 'partially_filled')>Partially Filled</option>
                                                <option value="unfilled" @selected($staffing === 'unfilled')>Unfilled</option>
                                            </select>
                                        </div>
                                        <div class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action job-postings-filter-apply">
                                            <x-ui.button
                                                type="button"
                                                variant="primary"
                                                size="sm"
                                                class="ui-toolbar__submit"
                                            >
                                                Apply
                                            </x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:controls>
                <div class="table-responsive">
                <table
                    class="table table-hover align-middle mb-0 datatable hrms-list-table hrms-table"
                    id="jobPostingsTable"
                >
                    <thead class="bg-light text-uppercase small font-weight-bold">
                        <tr>
                            <th class="pl-4 py-3">Vacancy</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Type</th>
                            <th class="py-3 text-center">Slots</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingPostingApprovals as $approval)
                            @php
                                $payload = (array) ($approval->payload ?? []);
                                $jobPayload = (array) ($payload['job_posting'] ?? []);
                                $requesterName = trim(($approval->requester?->employee?->first_name ?? '') . ' ' . ($approval->requester?->employee?->last_name ?? ''));
                                $requesterName = $requesterName !== '' ? $requesterName : ($approval->requester?->name ?? 'Unknown user');
                                $title = ucfirst((string) ($payload['job_title'] ?? $jobPayload['title'] ?? 'Job Posting'));
                                $departmentName = $payload['department_name'] ?? ($approval->requester?->employee?->department?->department ?? 'Department');
                                $employmentType = $jobPayload['employment_type'] ?? 'N/A';
                                $requestedHeadcount = max((int) ($jobPayload['required_headcount'] ?? 1), 1);
                                $requestedStatus = ucfirst((string) ($jobPayload['status'] ?? 'pending'));
                                $closingDate = !empty($jobPayload['closing_date'] ?? null)
                                    ? \Illuminate\Support\Carbon::parse($jobPayload['closing_date'])->format('M d, Y')
                                    : null;
                            @endphp
                            <tr class="table-warning" data-search="{{ strtolower(trim($title . ' ' . $departmentName . ' ' . $requesterName)) }}">
                                <td class="align-middle pl-4">
                                    <div class="job-row-card">
                                        <span class="job-row-icon bg-warning-soft text-warning">
                                            <i class="cil-clock"></i>
                                        </span>
                                        <div class="job-row-meta">
                                            <div class="job-cell-title text-dark font-weight-bold">
                                                {{ $title }}
                                            </div>
                                            <div class="job-cell-subtitle text-muted small">
                                                {{ $approval->actionLabel() }} by {{ $requesterName }}
                                            </div>
                                            <div class="job-cell-subtitle small text-warning">
                                                Awaiting HR review
                                            </div>
                                            @if ($closingDate)
                                                <div class="job-cell-subtitle text-muted small">
                                                    Requested closing date: {{ $closingDate }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    {{ $departmentName }}
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-light border px-2 py-1">{{ $employmentType }}</span>
                                </td>
                                <td class="align-middle text-center">
                                    <div class="font-weight-bold">
                                        0 / {{ $requestedHeadcount }}
                                    </div>
                                    <span class="badge badge-warning px-2 py-1">Pending Request</span>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="badge badge-warning px-3 py-2">Pending Approval</span>
                                    <div class="text-muted small mt-2">{{ $requestedStatus }}</div>
                                </td>
                                <td class="align-middle text-center">
                                    <div class="crud-actions justify-content-center flex-wrap">
                                        <form
                                            method="POST"
                                            action="{{ route('job-postings.approvals.approve', $approval) }}"
                                            class="d-inline"
                                        >
                                            @csrf
                                            <x-ui.button
                                                type="submit"
                                                variant="success"
                                                size="sm"
                                                aria-label="Approve Job Posting Request"
                                                title="Approve Job Posting Request"
                                            >
                                                Approve
                                            </x-ui.button>
                                        </form>
                                        <form
                                            method="POST"
                                            action="{{ route('job-postings.approvals.reject', $approval) }}"
                                            class="d-inline"
                                        >
                                            @csrf
                                            <x-ui.button
                                                type="submit"
                                                variant="danger"
                                                size="sm"
                                                aria-label="Decline Job Posting Request"
                                                title="Decline Job Posting Request"
                                            >
                                                Decline
                                            </x-ui.button>
                                        </form>
                                        <x-ui.button
                                            type="view"
                                            size="sm"
                                            data-toggle="modal"
                                            data-target="#recruitmentApprovalDetailsModal{{ $approval->id }}"
                                            aria-label="View Job Request Details"
                                            title="View Job Request Details"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @forelse ($postings as $job)
                            @php $requiredHeadcount = max((int) ($job->required_headcount ?? 1), 1); $fulfilledCount = (int) ($job->hired_count ?? 0); $remainingSlots = max($requiredHeadcount - $fulfilledCount, 0); $slotsBadgeClass = $remainingSlots === 0 ? 'badge-success' : ($fulfilledCount > 0 ? 'badge-warning' : 'badge-secondary'); $slotsBadgeLabel = $remainingSlots === 0 ? 'Fully Staffed' : ($fulfilledCount > 0 ? 'Hiring in Progress' : 'Open'); $remainingClass = $remainingSlots === 0 ? 'text-success' : ($remainingSlots === 1 ? 'text-warning' : 'text-muted'); $jobEditPayload = ['id' => $job->id, 'update_url' => route('job-postings.update', $job), 'department_id' => $job->department_id, 'position_id' => $job->position_id, 'title' => ucfirst($job->position?->position ?? $job->title), 'description' => $job->description, 'requirements' => $job->requirements, 'employment_type' => $job->employment_type, 'status' => $job->status, 'closing_date' => optional($job->closing_date)->format('Y-m-d'), 'required_headcount' => $requiredHeadcount, 'fulfilled_count' => $fulfilledCount, 'remaining_slots' => $remainingSlots]; @endphp
                            <tr data-search="{{ ucfirst($job->position?->position ?? $job->title) }}">
                                <td class="align-middle pl-4">
                                    <div class="job-row-card">
                                        <span class="job-row-icon">
                                            <i class="cil-briefcase"></i>
                                        </span>
                                        <div class="job-row-meta">
                                            <div
                                                class="job-cell-title text-dark font-weight-bold"
                                            >
                                                {{ ucfirst($job->position?->position ?? $job->title) }}
                                            </div>
                                            @if ($job->closing_date)
                                                <div
                                                    class="job-cell-subtitle text-muted small"
                                                >
                                                    Closes {{ $job->closing_date->format('M d, Y') }}
                                                </div>
                                            @else
                                                <div
                                                    class="job-cell-subtitle text-muted small"
                                                >
                                                    No closing date
                                                </div>
                                            @endif
                                            <div
                                                class="job-cell-subtitle small {{ $remainingClass }}"
                                            >
                                                Remaining slots: {{ $remainingSlots }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    {{ $job->department?->department ?? 'N/A' }}
                                </td>
                                <td class="align-middle">
                                    <span
                                        class="badge badge-light border px-2 py-1"
                                        >{{ $job->employment_type }}</span
                                    >
                                </td>
                                <td class="align-middle text-center">
                                    <div class="font-weight-bold">
                                        {{ $fulfilledCount }} / {{ $requiredHeadcount }}
                                    </div>
                                    <span
                                        class="badge {{ $slotsBadgeClass }} px-2 py-1"
                                        >{{ $slotsBadgeLabel }}</span
                                    >
                                </td>
                                <td class="align-middle text-center">
                                    @if ($job->status === 'open')
                                        <span class="badge badge-success px-3 py-2"
                                            >Open</span
                                        >
                                    @elseif ($job->status === 'draft')
                                        <span class="badge badge-warning px-3 py-2"
                                            >Draft</span
                                        >
                                    @else
                                        <span
                                            class="badge badge-secondary px-3 py-2"
                                            >Closed</span
                                        >
                                    @endif
                                </td>
                                <td class="align-middle text-center">
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        <x-ui.button
                                            type="edit"
                                            size="sm"
                                            class="edit-posting"
                                            data-toggle="modal"
                                            data-target="#jobPostingEditModal"
                                            data-id="{{ $job->id }}"
                                            data-update-url="{{ route('job-postings.update', $job) }}"
                                            data-edit='@json($jobEditPayload)'
                                            aria-label="Edit Job Posting"
                                            title="Edit Job Posting"
                                        />
                                        @if (Auth::user()->isAdmin() || \App\Services\AccessControl::isHrStaff(Auth::user()))
                                            <form
                                                action="{{ route('job-postings.destroy', $job) }}"
                                                method="POST"
                                                class="d-inline"
                                                data-confirm-message="Delete {{ $job->title }}?"
                                                data-confirm-title="Delete Job Posting"
                                                data-confirm-label="Delete"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf
                                                @method ('DELETE')
                                                <x-ui.button
                                                    type="submit"
                                                    variant="delete"
                                                    size="sm"
                                                    aria-label="Delete Job Posting"
                                                    title="Delete Job Posting"
                                                />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            @if ($pendingPostingApprovals->isEmpty())
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="opacity-50">
                                            <i
                                                class="cil-bullhorn fs-1 mb-3 text-muted"
                                            ></i>
                                            <h5 class="text-muted">
                                                No postings found
                                            </h5>
                                            <p class="text-muted small">Create the first vacancy to start receiving applications.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
                </div>
                <x-slot:footer>
                    {{ $postings->links() }}
                </x-slot:footer>
            </x-ui.table-card>
    </div>
    @foreach ($pendingPostingApprovals as $approval)
        @php
            $payload = (array) ($approval->payload ?? []);
            $jobPayload = (array) ($payload['job_posting'] ?? []);
            $requesterName = trim(($approval->requester?->employee?->first_name ?? '') . ' ' . ($approval->requester?->employee?->last_name ?? ''));
            $requesterName = $requesterName !== '' ? $requesterName : ($approval->requester?->name ?? 'Unknown user');
        @endphp
        <x-modal
            id="recruitmentApprovalDetailsModal{{ $approval->id }}"
            title="Job Request Details"
            subtitle="Review the staff-submitted posting request before approval."
            size="lg"
        >
            <x-slot:body>
                <div class="container-fluid px-0">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small text-uppercase">Request Type</div>
                            <div class="font-weight-bold">{{ $approval->actionLabel() }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small text-uppercase">Requested By</div>
                            <div>{{ $requesterName }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small text-uppercase">Vacancy</div>
                            <div class="font-weight-bold">{{ $payload['job_title'] ?? ($jobPayload['title'] ?? 'N/A') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small text-uppercase">Department</div>
                            <div>{{ $payload['department_name'] ?? 'N/A' }}</div>
                        </div>
                        @if (!empty($jobPayload['employment_type'] ?? null))
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Employment Type</div>
                                <div>{{ $jobPayload['employment_type'] }}</div>
                            </div>
                        @endif
                        @if (!empty($jobPayload['required_headcount'] ?? null))
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Required Headcount</div>
                                <div>{{ $jobPayload['required_headcount'] }}</div>
                            </div>
                        @endif
                        @if (!empty($jobPayload['status'] ?? null))
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Requested Status</div>
                                <div>{{ ucfirst((string) $jobPayload['status']) }}</div>
                            </div>
                        @endif
                        @if (!empty($jobPayload['closing_date'] ?? null))
                            <div class="col-md-6 mb-3">
                                <div class="text-muted small text-uppercase">Closing Date</div>
                                <div>{{ \Illuminate\Support\Carbon::parse($jobPayload['closing_date'])->format('M d, Y') }}</div>
                            </div>
                        @endif
                        @if (!empty($jobPayload['description'] ?? null))
                            <div class="col-12 mb-3">
                                <div class="text-muted small text-uppercase">Description</div>
                                <div class="border rounded px-3 py-2 bg-light">{{ $jobPayload['description'] }}</div>
                            </div>
                        @endif
                        @if (!empty($jobPayload['requirements'] ?? null))
                            <div class="col-12">
                                <div class="text-muted small text-uppercase">Requirements</div>
                                <div class="border rounded px-3 py-2 bg-light">{{ $jobPayload['requirements'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </x-slot:body>
        </x-modal>
    @endforeach
    <x-ui.modal
        id="jobPostingCreateModal"
        size="xl"
    >
                <x-ui.modal-header
                    title="Create Job Posting"
                    subtitle="Set a position as vacant and publish it to the job portal."
                />
                <form
                    action="{{ route('job-postings.store') }}"
                    method="POST"
                    id="jobPostingCreateForm"
                    class="job-posting-form"
                >
                    @csrf
                    <input
                        type="hidden"
                        name="form_context"
                        value="job_create"
                    />
                    <div class="modal-body">
                        @if ($isCreateFormContext)
                            @php
                                $createSummaryErrors = collect($errors->messages())
                                    ->except('closing_date')
                                    ->flatten()
                                    ->values();
                            @endphp
                            @if ($createSummaryErrors->isNotEmpty())
                                <div class="alert alert-danger" role="alert">
                                    <div class="font-weight-bold mb-1">Please correct the following before saving.</div>
                                    <ul class="mb-0 pl-3">
                                        @foreach ($createSummaryErrors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                        <div class="job-modal-layout row">
                            <div class="col-lg-8 pr-lg-4">
                                <x-ui.form-section
                                    title="Vacancy Information"
                                    class="job-form-section"
                                >
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="job_create_dept_id"
                                                >Department
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <select
                                                name="department_id"
                                                id="job_create_dept_id"
                                                class="form-control select2bs4"
                                                data-placeholder="Select department"
                                                required
                                            >
                                                <option value="">
                                                    -- Select Department --
                                                </option>
                                                @foreach ($departments as $dept)
                                                    <option
                                                        value="{{ $dept->id }}"
                                                        {{ $isCreateFormContext && (string) old('department_id') === (string) $dept->id ? 'selected' : '' }}
                                                        >{{ $dept->department }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-0">
                                            <label for="job_create_position_id"
                                                >Vacant Position
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <select
                                                name="position_id"
                                                id="job_create_position_id"
                                                class="form-control select2bs4"
                                                data-placeholder="Select position"
                                                required
                                                disabled
                                            >
                                                <option value="">
                                                    -- Select Department First
                                                    --
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </x-ui.form-section>
                                <x-ui.form-section
                                    title="Job Details"
                                    class="job-form-section"
                                >
                                    <div class="form-group">
                                        <label for="job_create_description"
                                            >Job Description
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <textarea
                                            name="description"
                                            id="job_create_description"
                                            class="form-control"
                                            rows="2"
                                            required
                                            >{{ $isCreateFormContext ? old('description') : '' }}</textarea
                                        >
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="job_create_requirements"
                                            >Requirements (Optional)</label
                                        >
                                        <textarea
                                            name="requirements"
                                            id="job_create_requirements"
                                            class="form-control"
                                            >{{ $isCreateFormContext ? old('requirements') : '' }}</textarea
                                        >
                                    </div>
                                </x-ui.form-section>
                            </div>
                            <div class="col-lg-4">
                                <aside class="job-publishing-panel">
                                    <h6 class="job-form-section-title">
                                        Publishing Settings
                                    </h6>
                                    <div class="form-group">
                                        <label for="job_create_employment_type"
                                            >Employment Type
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            name="employment_type"
                                            id="job_create_employment_type"
                                            class="form-control select2bs4"
                                            data-placeholder="Select employment type"
                                            required
                                        >
                                            <option
                                                value="Full-time"
                                                {{ ($isCreateFormContext ? old('employment_type') : 'Full-time') === 'Full-time' ? 'selected' : '' }}
                                                >Full-time
                                            </option>
                                            <option
                                                value="Part-time"
                                                {{ ($isCreateFormContext ? old('employment_type') : 'Full-time') === 'Part-time' ? 'selected' : '' }}
                                                >Part-time
                                            </option>
                                            <option
                                                value="Contract"
                                                {{ ($isCreateFormContext ? old('employment_type') : 'Full-time') === 'Contract' ? 'selected' : '' }}
                                                >Contract
                                            </option>
                                            <option
                                                value="Freelance"
                                                {{ ($isCreateFormContext ? old('employment_type') : 'Full-time') === 'Freelance' ? 'selected' : '' }}
                                                >Freelance
                                            </option>
                                        </select>
                                        <small class="form-text text-muted"
                                            >Defines listing label and contract
                                            classification.</small
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="job_create_status"
                                            >Status
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            name="status"
                                            id="job_create_status"
                                            class="form-control select2bs4"
                                            data-placeholder="Select status"
                                            required
                                        >
                                            <option
                                                value="draft"
                                                {{ ($isCreateFormContext ? old('status', 'draft') : 'draft') === 'draft' ? 'selected' : '' }}
                                                >Draft
                                            </option>
                                            <option
                                                value="open"
                                                {{ ($isCreateFormContext ? old('status') : 'draft') === 'open' ? 'selected' : '' }}
                                                >Open
                                            </option>
                                            <option
                                                value="closed"
                                                {{ ($isCreateFormContext ? old('status') : 'draft') === 'closed' ? 'selected' : '' }}
                                                >Closed
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label
                                            for="job_create_required_headcount"
                                            >Number of Required Hires
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="number"
                                            id="job_create_required_headcount"
                                            name="required_headcount"
                                            class="form-control"
                                            min="1"
                                            step="1"
                                            value="{{ $isCreateFormContext ? old('required_headcount', 1) : 1 }}"
                                            required
                                        />
                                        <small class="form-text text-muted"
                                            >Specify how many individuals are
                                            needed for this vacancy.</small
                                        >
                                    </div>
                                    <div class="form-group mb-0 mt-3">
                                        <label for="job_create_closing_date"
                                            >Closing Date</label
                                        >
                                        <input
                                            type="date"
                                            id="job_create_closing_date"
                                            name="closing_date"
                                            class="form-control {{ $errors->has('closing_date') && $isCreateFormContext ? 'is-invalid' : '' }}"
                                            value="{{ $isCreateFormContext ? old('closing_date') : '' }}"
                                        />
                                        <small class="form-text text-muted"
                                            >Optional. Leave blank for
                                            open-ended recruitment.</small
                                        >
                                        @if ($errors->has('closing_date') && $isCreateFormContext)
                                            <div class="invalid-feedback d-block">{{ $errors->first('closing_date') }}</div>
                                        @endif
                                    </div>
                                </aside>
                            </div>
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-save"
                        >
                            Save Posting
                        </x-ui.button>
                    </x-ui.modal-footer>
                </form>
    </x-ui.modal>
    <x-ui.modal
        id="jobPostingEditModal"
        size="xl"
    >
                <x-ui.modal-header
                    title="Edit Job Posting"
                    subtitle="Update vacancy details and publishing status."
                >
                    <p class="hrms-modal-head__subtitle mb-0">Remaining slots: <span class="font-weight-bold" id="job_edit_remaining_slots_badge">-</span></p>
                </x-ui.modal-header>
                <form
                    action="{{ route('job-postings.update-fallback') }}"
                    method="POST"
                    id="jobPostingEditForm"
                    class="job-posting-form"
                >
                    @csrf
                    @method ('PUT')
                    <input type="hidden" name="form_context" value="job_edit" />
                    <input
                        type="hidden"
                        name="posting_id"
                        id="job_edit_posting_id"
                        value="{{ old('posting_id') }}"
                    />
                    <input
                        type="hidden"
                        name="update_url"
                        id="job_edit_update_url"
                        value="{{ old('update_url') }}"
                    />
                    <div class="modal-body">
                        @if ($isEditFormContext)
                            @php
                                $editSummaryErrors = collect($errors->messages())
                                    ->except('closing_date')
                                    ->flatten()
                                    ->values();
                            @endphp
                            @if ($editSummaryErrors->isNotEmpty())
                                <div class="alert alert-danger" role="alert">
                                    <div class="font-weight-bold mb-1">Fix the fields below, then update.</div>
                                    <ul class="mb-0 pl-3">
                                        @foreach ($editSummaryErrors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                        <div class="job-modal-layout row">
                            <div class="col-lg-8 pr-lg-4">
                                <x-ui.form-section
                                    title="Vacancy Information"
                                    class="job-form-section"
                                >
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label for="job_edit_dept_id"
                                                >Department
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <select
                                                name="department_id"
                                                id="job_edit_dept_id"
                                                class="form-control select2bs4"
                                                data-placeholder="Select department"
                                                required
                                            >
                                                <option value="">
                                                    -- Select Department --
                                                </option>
                                                @foreach ($departments as $dept)
                                                    <option
                                                        value="{{ $dept->id }}"
                                                        {{ $isEditFormContext && (string) old('department_id') === (string) $dept->id ? 'selected' : '' }}
                                                        >{{ $dept->department }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-0">
                                            <label for="job_edit_position_id"
                                                >Vacant Position
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <select
                                                name="position_id"
                                                id="job_edit_position_id"
                                                class="form-control select2bs4"
                                                data-placeholder="Select position"
                                                required
                                                disabled
                                            >
                                                <option value="">
                                                    -- Select Department First
                                                    --
                                                </option>
                                            </select>
                                            <small class="form-text text-muted"
                                                >Positions load based on
                                                selected department.</small
                                            >
                                        </div>
                                    </div>
                                </x-ui.form-section>
                                <x-ui.form-section
                                    title="Job Details"
                                    class="job-form-section"
                                >
                                    <div class="form-group">
                                        <label for="job_edit_description"
                                            >Job Description
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <textarea
                                            name="description"
                                            id="job_edit_description"
                                            class="form-control"
                                            rows="2"
                                            required
                                            >{{ $isEditFormContext ? old('description') : '' }}</textarea
                                        >
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="job_edit_requirements"
                                            >Requirements (Optional)</label
                                        >
                                        <textarea
                                            name="requirements"
                                            id="job_edit_requirements"
                                            class="form-control"
                                            rows="2"
                                            >{{ $isEditFormContext ? old('requirements') : '' }}</textarea
                                        >
                                    </div>
                                </x-ui.form-section>
                            </div>
                            <div class="col-lg-4">
                                <aside class="job-publishing-panel">
                                    <h6 class="job-form-section-title">
                                        Publishing Settings
                                    </h6>
                                    <div class="form-group">
                                        <label for="job_edit_employment_type"
                                            >Employment Type
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            name="employment_type"
                                            id="job_edit_employment_type"
                                            class="form-control select2bs4"
                                            data-placeholder="Select employment type"
                                            required
                                        >
                                            <option
                                                value="Full-time"
                                                {{ ($isEditFormContext ? old('employment_type') : 'Full-time') === 'Full-time' ? 'selected' : '' }}
                                                >Full-time
                                            </option>
                                            <option
                                                value="Part-time"
                                                {{ ($isEditFormContext ? old('employment_type') : 'Full-time') === 'Part-time' ? 'selected' : '' }}
                                                >Part-time
                                            </option>
                                            <option
                                                value="Contract"
                                                {{ ($isEditFormContext ? old('employment_type') : 'Full-time') === 'Contract' ? 'selected' : '' }}
                                                >Contract
                                            </option>
                                            <option
                                                value="Freelance"
                                                {{ ($isEditFormContext ? old('employment_type') : 'Full-time') === 'Freelance' ? 'selected' : '' }}
                                                >Freelance
                                            </option>
                                        </select>
                                        <small class="form-text text-muted"
                                            >Defines listing label and contract
                                            classification.</small
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="job_edit_status"
                                            >Status
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            name="status"
                                            id="job_edit_status"
                                            class="form-control select2bs4"
                                            data-placeholder="Select status"
                                            required
                                        >
                                            <option
                                                value="draft"
                                                {{ ($isEditFormContext ? old('status', 'draft') : 'draft') === 'draft' ? 'selected' : '' }}
                                                >Draft
                                            </option>
                                            <option
                                                value="open"
                                                {{ ($isEditFormContext ? old('status') : 'draft') === 'open' ? 'selected' : '' }}
                                                >Open
                                            </option>
                                            <option
                                                value="closed"
                                                {{ ($isEditFormContext ? old('status') : 'draft') === 'closed' ? 'selected' : '' }}
                                                >Closed
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="job_edit_required_headcount"
                                            >Number of Required Hires
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="number"
                                            name="required_headcount"
                                            id="job_edit_required_headcount"
                                            class="form-control"
                                            min="1"
                                            step="1"
                                            value="{{ $isEditFormContext ? old('required_headcount', 1) : 1 }}"
                                            required
                                        />
                                        <small class="form-text text-muted"
                                            >Specify how many individuals are
                                            needed for this vacancy.</small
                                        >
                                    </div>
                                    <div class="form-group mb-0 mt-3">
                                        <label for="job_edit_closing_date"
                                            >Closing Date</label
                                        >
                                        <input
                                            type="date"
                                            name="closing_date"
                                            id="job_edit_closing_date"
                                            class="form-control {{ $errors->has('closing_date') && $isEditFormContext ? 'is-invalid' : '' }}"
                                            value="{{ $isEditFormContext ? old('closing_date') : '' }}"
                                        />
                                        <small class="form-text text-muted"
                                            >Optional. Leave blank for
                                            open-ended recruitment.</small
                                        >
                                        @if ($errors->has('closing_date') && $isEditFormContext)
                                            <div class="invalid-feedback d-block">{{ $errors->first('closing_date') }}</div>
                                        @endif
                                    </div>
                                </aside>
                            </div>
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-save"
                        >
                            Update Posting
                        </x-ui.button>
                    </x-ui.modal-footer>
                </form>
    </x-ui.modal>
@endsection
