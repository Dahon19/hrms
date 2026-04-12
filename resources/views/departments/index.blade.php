@extends ('layouts.admin')
@section ('content')
    @php use App\Support\FormValidation; @endphp
    <div
        class="container-fluid"
        id="departmentsIndexPage"
        data-create-error="{{ $errors->any() && old('form_context') === 'department_create' ? '1' : '0' }}"
        data-edit-error="{{ $errors->any() && old('form_context') === 'department_edit' ? '1' : '0' }}"
        data-logo-error="{{ $errors->any() && old('form_context') === 'department_logo' ? '1' : '0' }}"
        data-type-create-error="{{ $errors->any() && old('form_context') === 'department_type_create' ? '1' : '0' }}"
        data-type-edit-error="{{ $errors->any() && old('form_context') === 'department_type_edit' ? '1' : '0' }}"
    >
        @php $logoUrl = function (?string $path) { if (!$path) { return null; } $parts = explode('/', $path); if (count($parts) < 3) { return null; } return route('storage.file', [ 'folder' => $parts[0], 'subfolder' => $parts[1], 'filename' => $parts[2], ]); }; $currentUser = auth()->user(); $currentDeptId = $currentUser?->employee?->department_id; $canUpdateLogo = in_array($currentUser?->positionName() ?? 'employee', ['head', 'secretary'], true); @endphp
        <x-page-header
            eyebrow="Workforce"
            title="Departments"
            subtitle="Manage department profiles, organization charts, and member visibility."
        >
            <x-slot:actions>
                <div class="dept-header-actions">
                    <div class="dept-header-left">
                        @if (!empty($isSpecialHead))
                            <div
                                class="dept-nav-tabs"
                                role="tablist"
                                aria-label="Department views"
                            >
                                <a
                                    class="dept-nav-tab {{ empty($showOrgChart) ? 'is-active' : '' }}"
                                    href="{{ route('departments.index') }}"
                                >
                                    <i class="cil-building"></i>
                                    <span>Department List</span>
                                </a>
                                <a
                                    class="dept-nav-tab {{ !empty($showOrgChart) ? 'is-active' : '' }}"
                                    href="{{ route('departments.index', ['view' => 'org']) }}"
                                >
                                    <i class="cil-sitemap"></i>
                                    <span>Organization Chart</span>
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="dept-header-right">
                        @if (Auth::user()->role == 'admin')
                            <x-ui.button
                                variant="outline-primary"
                                size="sm"
                                data-toggle="modal"
                                data-target="#departmentTypeManageModal"
                                icon="cil-tags"
                            >
                                Department Types
                            </x-ui.button>
                            <x-ui.button
                                variant="outline-primary"
                                size="sm"
                                data-toggle="modal"
                                data-target="#departmentCreateModal"
                                icon="cil-plus"
                            >
                                Add Department
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </x-slot:actions>
        </x-page-header>
        @if (!empty($isHead) && $isHead && empty($isSpecialHead))
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body">
                    @if (empty($orgChartDepartment))
                        <x-ui.empty-state
                            icon="cil-people"
                            title="No positions found"
                            message="No positions found for this department."
                        />
                    @else
                        <div class="org-chart-header mb-4">
                            @php $orgChartLogoUrl = $logoUrl($orgChartDepartment->logo_path); $orgChartMembers = $orgChart->flatMap(fn ($group) => $group->members)->unique('id')->values(); $orgChartHeadGroup = $orgChart->first(function ($group) { return in_array(strtolower((string) $group->position), ['head', 'dean'], true); }); $orgChartHeadMember = $orgChartHeadGroup?->members->first(); $orgChartHeadName = $orgChartHeadMember ? trim(($orgChartHeadMember->first_name ?? '') . ' ' . ($orgChartHeadMember->last_name ?? '')) : null; @endphp
                            @if ($canUpdateLogo && $orgChartDepartment)
                                <button
                                    type="button"
                                    class="department-logo-btn"
                                    data-toggle="modal"
                                    data-target="#departmentLogoModal"
                                    data-update-url="{{ route('departments.logo.update', $orgChartDepartment) }}"
                                    data-current-logo="{{ $orgChartLogoUrl }}"
                                    aria-label="{{ $orgChartLogoUrl ? 'Edit department logo' : 'Upload department logo' }}"
                                >
                                    @if ($orgChartLogoUrl)
                                        <img
                                            src="{{ $orgChartLogoUrl }}"
                                            alt="{{ $orgChartDepartment->department }} logo"
                                            class="department-logo-lg"
                                        />
                                    @else
                                        <span
                                            class="department-logo-lg d-inline-flex align-items-center justify-content-center text-primary"
                                        >
                                            <i class="cil-image"></i>
                                        </span>
                                    @endif
                                    <span class="logo-edit-badge"
                                        ><i class="cil-pencil"></i
                                    ></span>
                                </button>
                            @elseif ($orgChartLogoUrl)
                                <img
                                    src="{{ $orgChartLogoUrl }}"
                                    alt="{{ $orgChartDepartment->department }} logo"
                                    class="department-logo-lg"
                                />
                            @endif
                            <div class="org-chart-header-copy">
                                <div class="org-dept-title">
                                    {{ $orgChartDepartment->department }}
                                </div>
                                <div class="org-chart-meta-line">
                                    {{ $orgChartHeadGroup?->position ?? 'Head' }}:
                                    <span
                                        >{{ $orgChartHeadName ?: 'No department head assigned' }}</span
                                    >
                                </div>
                                <div class="org-chart-meta-line">
                                    Employees:
                                    <span>{{ $orgChartMembers->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            @forelse ($orgChart as $group)
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="org-card h-100">
                                        <div
                                            class="org-card-header d-flex align-items-center justify-content-between"
                                        >
                                            <span>{{ $group->position }}</span>
                                            <span
                                                class="badge badge-light"
                                                >{{ $group->members->count() }}</span
                                            >
                                        </div>
                                        <div class="org-card-body">
                                            @forelse ($group->members as $member)
                                                <div class="org-member">
                                                    <div
                                                        class="org-avatar d-inline-flex align-items-center justify-content-center text-primary font-weight-bold"
                                                    >
                                                        {{ strtoupper(substr($member->first_name ?? 'U', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div
                                                            class="member-name"
                                                        >
                                                            {{ trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: 'Employee' }}
                                                        </div>
                                                        <div
                                                            class="member-id text-muted"
                                                        >
                                                            #{{ $member->employee_id ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-muted small">
                                                    No assigned employees.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <x-ui.empty-state
                                        icon="cil-people"
                                        title="No positions found"
                                        message="No positions found for this department."
                                    />
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @if (!empty($isSpecialHead) && !empty($showOrgChart))
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body">
                    @if (empty($orgChartDepartment))
                        <x-ui.empty-state
                            icon="cil-people"
                            title="No positions found"
                            message="No positions found for this department."
                        />
                    @else
                        <div class="org-chart-header mb-4">
                            @php $orgChartLogoUrl = $logoUrl($orgChartDepartment->logo_path); $orgChartMembers = $orgChart->flatMap(fn ($group) => $group->members)->unique('id')->values(); $orgChartHeadGroup = $orgChart->first(function ($group) { return in_array(strtolower((string) $group->position), ['head', 'dean'], true); }); $orgChartHeadMember = $orgChartHeadGroup?->members->first(); $orgChartHeadName = $orgChartHeadMember ? trim(($orgChartHeadMember->first_name ?? '') . ' ' . ($orgChartHeadMember->last_name ?? '')) : null; @endphp
                            @if ($canUpdateLogo && $orgChartDepartment)
                                <button
                                    type="button"
                                    class="department-logo-btn"
                                    data-toggle="modal"
                                    data-target="#departmentLogoModal"
                                    data-update-url="{{ route('departments.logo.update', $orgChartDepartment) }}"
                                    data-current-logo="{{ $orgChartLogoUrl }}"
                                    aria-label="{{ $orgChartLogoUrl ? 'Edit department logo' : 'Upload department logo' }}"
                                >
                                    @if ($orgChartLogoUrl)
                                        <img
                                            src="{{ $orgChartLogoUrl }}"
                                            alt="{{ $orgChartDepartment->department }} logo"
                                            class="department-logo-lg"
                                        />
                                    @else
                                        <span
                                            class="department-logo-lg d-inline-flex align-items-center justify-content-center text-primary"
                                        >
                                            <i class="cil-image"></i>
                                        </span>
                                    @endif
                                    <span class="logo-edit-badge"
                                        ><i class="cil-pencil"></i
                                    ></span>
                                </button>
                            @elseif ($orgChartLogoUrl)
                                <img
                                    src="{{ $orgChartLogoUrl }}"
                                    alt="{{ $orgChartDepartment->department }} logo"
                                    class="department-logo-lg"
                                />
                            @endif
                            <div class="org-chart-header-copy">
                                <div class="org-dept-title">
                                    {{ $orgChartDepartment->department }}
                                </div>
                                <div class="org-chart-meta-line">
                                    {{ $orgChartHeadGroup?->position ?? 'Head' }}:
                                    <span
                                        >{{ $orgChartHeadName ?: 'No department head assigned' }}</span
                                    >
                                </div>
                                <div class="org-chart-meta-line">
                                    Employees:
                                    <span>{{ $orgChartMembers->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            @forelse ($orgChart as $group)
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="org-card h-100">
                                        <div
                                            class="org-card-header d-flex align-items-center justify-content-between"
                                        >
                                            <span>{{ $group->position }}</span>
                                            <span
                                                class="badge badge-light"
                                                >{{ $group->members->count() }}</span
                                            >
                                        </div>
                                        <div class="org-card-body">
                                            @forelse ($group->members as $member)
                                                <div class="org-member">
                                                    <div
                                                        class="org-avatar d-inline-flex align-items-center justify-content-center text-primary font-weight-bold"
                                                    >
                                                        {{ strtoupper(substr($member->first_name ?? 'U', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div
                                                            class="member-name"
                                                        >
                                                            {{ trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: 'Employee' }}
                                                        </div>
                                                        <div
                                                            class="member-id text-muted"
                                                        >
                                                            #{{ $member->employee_id ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-muted small">
                                                    No assigned employees.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <x-ui.empty-state
                                        icon="cil-people"
                                        title="No positions found"
                                        message="No positions found for this department."
                                    />
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @if (empty($isHead) || !$isHead || (!empty($isSpecialHead) && empty($showOrgChart)))
            <x-ui.table-card
                title="Department Directory"
                class="dept-card"
            >
                <x-slot:controls>
                    <x-ui.table-toolbar
                        method="GET"
                        :action="route('departments.index')"
                        class="dept-index-toolbar mb-0"
                    >
                        <div class="dept-index-toolbar-grid">
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                            >
                                <label
                                    class="form-label"
                                    for="departmentTableSearch"
                                    >Search</label
                                >
                                <input
                                    id="departmentTableSearch"
                                    type="search"
                                    name="search"
                                    value="{{ $search ?? '' }}"
                                    class="form-control form-control-sm dept-toolbar-search"
                                    placeholder="Search department name"
                                />
                            </div>
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                            >
                                <label
                                    class="form-label"
                                    for="departmentTypeFilter"
                                    >Department Type</label
                                >
                                <select
                                    id="departmentTypeFilter"
                                    name="type"
                                    class="form-control form-control-sm dept-toolbar-filter"
                                >
                                    <option value="">All types</option>
                                    @foreach ($departmentTypes as $type)
                                        <option
                                            value="{{ $type->name }}"
                                            @selected (($typeFilter ?? '') === $type->name)
                                            >{{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div
                                class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                            >
                                <x-ui.button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    id="departmentToolbarApply"
                                >
                                    Apply
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.table-toolbar>
                </x-slot:controls>
                <table
                    class="table table-hover align-middle mb-0 hrms-table"
                    id="departmentsTable"
                >
                    <thead class="bg-light">
                        <tr class="text-uppercase text-muted small">
                            <th class="ps-4 py-3">Department Name</th>
                            <th class="py-3">Type</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $dept)
                            @php $departmentEditPayload = ['update_url' => route('departments.update', $dept), 'name' => $dept->department, 'type' => $dept->department_type]; @endphp
                            <tr
                                class="department-row"
                                data-dept-id="{{ $dept->id }}"
                                data-dept-name="{{ $dept->department }}"
                                title="Click to view positions"
                            >
                                <td class="align-middle ps-4">
                                    <div class="d-flex align-items-center">
                                        <div
                                            class="icon-shape icon-sm bg-light rounded me-3 text-primary border"
                                        >
                                            @if ($dept->logo_path)
                                                <img
                                                    src="{{ $logoUrl($dept->logo_path) }}"
                                                    alt="{{ $dept->department }} logo"
                                                    class="department-logo-sm"
                                                />
                                            @else
                                                <i class="cil-sitemap"></i>
                                            @endif
                                        </div>
                                        <span
                                            class="font-weight-bold text-dark"
                                            >{{ $dept->department }}</span
                                        >
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <span
                                        class="badge badge-secondary-soft px-2 py-1 border text-uppercase small"
                                    >
                                        {{ $dept->department_type ?? 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="align-middle text-center">
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        <x-ui.button
                                            type="view"
                                            size="sm"
                                            class="view-dept"
                                            data-toggle="modal"
                                            data-target="#departmentPositionsModal"
                                            data-dept-id="{{ $dept->id }}"
                                            data-dept-name="{{ $dept->department }}"
                                            aria-label="View Department Positions"
                                            title="View Department Positions"
                                        />
                                        @can ('manage-departments')
                                            <x-ui.button
                                                type="edit"
                                                size="sm"
                                                data-toggle="modal"
                                                data-target="#departmentEditModal"
                                                data-edit='@json($departmentEditPayload)'
                                                data-update-url="{{ route('departments.update', $dept) }}"
                                                data-name="{{ $dept->department }}"
                                                data-type="{{ $dept->department_type }}"
                                                aria-label="Edit Department"
                                                title="Edit Department"
                                            />
                                        @endcan
                                        @if (Auth::user()->isAdmin())
                                            <form
                                                action="{{ route('departments.destroy', $dept) }}"
                                                method="POST"
                                                class="d-inline"
                                                data-confirm-message="Delete {{ $dept->department }}?"
                                                data-confirm-title="Delete Department"
                                                data-confirm-label="Delete"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf
                                                @method ('DELETE')
                                                <x-ui.button
                                                    type="submit"
                                                    variant="delete"
                                                    size="sm"
                                                    aria-label="Delete Department"
                                                    title="Delete Department"
                                                />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-0">
                                    <x-ui.empty-state
                                        icon="cil-folder-open"
                                        title="No departments found"
                                        message="No departments found matching current filters."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <x-slot:footer>
                    {{ $departments->links() }}
                </x-slot:footer>
            </x-ui.table-card>
            {{-- ===================== POSITION MODAL ===================== --}}
            <x-ui.modal
                id="departmentPositionsModal"
                size="lg"
                data-base-url="{{ url('departments') }}"
            >
                        <x-ui.modal-header
                            title="Department Positions"
                            title-id="departmentPositionsTitle"
                        />
                        <div class="modal-body p-0">
                            <div class="bg-light p-3 border-bottom">
                                <small
                                    id="dept-availability"
                                    class="text-primary font-weight-bold text-uppercase"
                                ></small>
                            </div>
                        <div class="table-responsive hrms-table">
                            <table
                                class="table hrms-table"
                                id="departmentPositionsTable"
                                >
                                    <thead class="bg-white">
                                        <tr
                                            class="small text-muted text-uppercase"
                                        >
                                            <th class="ps-4">Position Title</th>
                                            <th>Allocation</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dept-positions-body">
                                        <tr>
                                            <td
                                                colspan="3"
                                                class="text-center py-4"
                                            >
                                                <div
                                                    class="dept-positions-loader justify-content-center"
                                                >
                                                    <x-ui.spinner color="success" size="sm" label="Loading positions..." />
                                                    Loading positions...
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                data-coreui-dismiss="modal"
                            >
                                Close
                            </button>
                        </x-ui.modal-footer>
            </x-ui.modal>
        @endif
    </div>
    @if (Auth::user()->role == 'admin')
        <x-ui.modal
            id="departmentTypeManageModal"
            size="lg"
        >
                    <x-ui.modal-header
                        title="Manage Department Types"
                    />
                    <div class="modal-body">
                        <form
                            action="{{ route('department-types.store') }}"
                            method="POST"
                            class="mb-4"
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="form_context"
                                value="department_type_create"
                            />
                            <div class="input-group">
                                <input
                                    type="text"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'department_type_create') }}"
                                    placeholder="Add department type"
                                    value="{{ old('form_context') === 'department_type_create' ? old('name') : '' }}"
                                    required
                                />
                                <div class="input-group-append">
                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary"
                                    >
                                        <i class="cil-plus me-1"></i> Add Type
                                    </button>
                                </div>
                            </div>
                            <x-ui.form-error field="name" context="department_type_create" class="invalid-feedback d-block font-weight-bold" />
                        </form>
                        <div class="table-responsive">
                            <table class="table hrms-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Department Type</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($departmentTypes as $type)
                                        <tr>
                                            <td class="align-middle">
                                                {{ $type->name }}
                                            </td>
                                            <td class="align-middle text-center">
                                                @if ($type->id)
                                                    <div class="crud-actions justify-content-center">
                                                        <x-ui.button
                                                            type="edit"
                                                            size="sm"
                                                            data-toggle="modal"
                                                            data-target="#departmentTypeEditModal"
                                                            data-edit='@json(['update_url' => route('department-types.update', $type), 'name' => $type->name])'
                                                            data-update-url="{{ route('department-types.update', $type) }}"
                                                            data-name="{{ $type->name }}"
                                                            aria-label="Edit Department Type"
                                                            title="Edit Department Type"
                                                        />
                                                        <form
                                                            action="{{ route('department-types.destroy', $type) }}"
                                                            method="POST"
                                                            class="d-inline"
                                                            data-confirm-message="Delete {{ $type->name }}?"
                                                            data-confirm-title="Delete Department Type"
                                                            data-confirm-label="Delete"
                                                            data-confirm-variant="danger"
                                                        >
                                                            @csrf
                                                            @method ('DELETE')
                                                            <x-ui.button
                                                                type="submit"
                                                                variant="delete"
                                                                size="sm"
                                                                aria-label="Delete Department Type"
                                                                title="Delete Department Type"
                                                            />
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">Unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td
                                                colspan="2"
                                                class="text-center text-muted py-4"
                                            >
                                                No department types found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <button
                            type="button"
                            class="btn btn-light btn-sm"
                            data-coreui-dismiss="modal"
                        >
                            Close
                        </button>
                    </x-ui.modal-footer>
        </x-ui.modal>
        <x-ui.modal
            id="departmentTypeEditModal"
        >
                    <x-ui.modal-header
                        title="Edit Department Type"
                        icon="cil-pencil"
                    />
                    <form id="departmentTypeEditForm" action="#" method="POST">
                        @csrf
                        @method ('PATCH')
                        <input
                            type="hidden"
                            name="form_context"
                            value="department_type_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="department_type_update_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group mb-0">
                                <label for="department_type_edit_name"
                                    >Department Type</label
                                >
                                <input
                                    type="text"
                                    id="department_type_edit_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'department_type_edit') }}"
                                    value="{{ old('form_context') === 'department_type_edit' ? old('name') : '' }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="department_type_edit" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
        <x-ui.modal
            id="departmentCreateModal"
            size="lg"
        >
                    <x-ui.modal-header
                        title="Create New Department"
                        icon="cil-building"
                    />
                    <form
                        action="{{ route('departments.store') }}"
                        method="POST"
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="department_create"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="department_create_name"
                                    >Department Name
                                    <span class="text-danger">*</span></label
                                >
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"
                                            ><i class="cil-sitemap"></i
                                        ></span>
                                    </div>
                                    <input
                                        type="text"
                                        class="form-control {{ FormValidation::invalidClass('department', 'department_create') }}"
                                        id="department_create_name"
                                        name="department"
                                        placeholder="e.g., Human Resources, IT, Finance"
                                        value="{{ old('department') }}"
                                        required
                                    />
                                </div>
                                <x-ui.form-error field="department" context="department_create" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                            <div class="form-group">
                                <label for="department_create_type"
                                    >Department Type
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('department_type', 'department_create') }}"
                                    id="department_create_type"
                                    name="department_type"
                                    required
                                >
                                    <option
                                        value=""
                                        disabled
                                        {{ old('department_type') ? '' : 'selected' }}
                                        >Select type
                                    </option>
                                    @foreach ($departmentTypes as $type)
                                        <option
                                            value="{{ $type->name }}"
                                            {{ old('department_type') === $type->name ? 'selected' : '' }}
                                        >
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-ui.form-error field="department_type" context="department_create" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
        <x-ui.modal
            id="departmentEditModal"
            size="lg"
        >
                    <x-ui.modal-header
                        title="Edit Department"
                        icon="cil-pencil"
                    />
                    <form id="departmentEditForm" action="#" method="POST">
                        @csrf
                        @method ('PUT')
                        <input
                            type="hidden"
                            name="form_context"
                            value="department_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="department_edit_update_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="department_edit_name"
                                    >Department Name
                                    <span class="text-danger">*</span></label
                                >
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"
                                            ><i
                                                class="cil-sitemap text-warning"
                                            ></i
                                        ></span>
                                    </div>
                                    <input
                                        type="text"
                                        class="form-control {{ FormValidation::invalidClass('department', 'department_edit') }}"
                                        id="department_edit_name"
                                        name="department"
                                        placeholder="Enter department name"
                                        value="{{ old('department') }}"
                                        required
                                    />
                                </div>
                                <x-ui.form-error field="department" context="department_edit" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                            <div class="form-group">
                                <label for="department_edit_type"
                                    >Department Type
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('department_type', 'department_edit') }}"
                                    id="department_edit_type"
                                    name="department_type"
                                    required
                                >
                                    @foreach ($departmentTypes as $type)
                                        <option
                                            value="{{ $type->name }}"
                                            {{ old('department_type') === $type->name ? 'selected' : '' }}
                                        >
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-ui.form-error field="department_type" context="department_edit" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                            <div class="alert alert-info mt-3 p-2">
                                <small
                                    ><i class="cil-info mr-1"></i> Changing the
                                    department name will update the record for
                                    all linked employees.</small
                                >
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
    @endif
    @if ($canUpdateLogo)
        <x-ui.modal
            id="departmentLogoModal"
            dialog-class="department-logo-modal-dialog"
        >
                    <x-ui.modal-header
                        title="Update Department Logo"
                        icon="cil-image"
                    />
                    <form
                        id="departmentLogoForm"
                        action="#"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="department_logo"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="department_logo_update_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body department-logo-modal-body">
                            <div
                                class="department-logo-modal-preview text-center"
                            >
                                <div class="department-logo-preview-wrap">
                                    <img
                                        id="department_logo_preview"
                                        src=""
                                        alt="Department logo preview"
                                        class="department-logo-preview d-none"
                                    />
                                    <div
                                        id="department_logo_empty"
                                        class="department-logo-empty-state"
                                    >
                                        <span class="department-logo-empty-icon"
                                            ><i class="cil-image"></i
                                        ></span>
                                        <span class="department-logo-empty-text"
                                            >No logo uploaded</span
                                        >
                                    </div>
                                </div>
                                <p class="department-logo-helper mb-0">PNG, JPG, or WEBP up to 10MB.</p>
                            </div>
                            <div class="form-group mb-0">
                                <input
                                    type="file"
                                    name="logo"
                                    id="department_logo_file"
                                    class="department-logo-input {{ FormValidation::invalidClass('logo', 'department_logo') }}"
                                    accept="image/png,image/jpeg,image/webp"
                                    required
                                />
                                <label
                                    for="department_logo_file"
                                    class="logo-upload-dropzone"
                                    id="departmentLogoDropzone"
                                >
                                    <span class="logo-upload-icon"
                                        ><i class="cil-cloud-upload"></i
                                    ></span>
                                    <span class="logo-upload-title"
                                        >Choose Logo</span
                                    >
                                    <span class="logo-upload-hint"
                                        >Drag and drop a file here or click to
                                        browse.</span
                                    >
                                </label>
                                <x-ui.form-error field="logo" context="department_logo" class="invalid-feedback d-block font-weight-bold" />
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
    @endif
@endsection
