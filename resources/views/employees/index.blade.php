@extends ('layouts.admin')

@section ('content')
    @php use App\Support\FormValidation; @endphp
    <div
        id="employeeIndexPage"
        data-create-error="{{ $errors->any() && old('form_context') === 'employee_create' ? '1' : '0' }}"
        data-edit-error="{{ $errors->any() && old('form_context') === 'employee_edit' ? '1' : '0' }}"
        data-has-success="{{ session('success') ? '1' : '0' }}"
    >
        <x-page-header
            eyebrow="Workforce"
            title="Employees"
            subtitle="Manage profiles, positions, and access in one place."
        >
            @if (Auth::user()->role == 'admin')
                <x-slot:actions>
                    <x-ui.button
                        :href="route('employees.index', array_merge(request()->query(), ['account_scope' => (($accountScope ?? 'active') === 'archived' ? 'active' : 'archived')]))"
                        variant="outline-light"
                        size="sm"
                        :icon="(($accountScope ?? 'active') === 'archived') ? 'cil-people' : 'cil-user-x'"
                    >
                        {{ (($accountScope ?? 'active') === 'archived') ? 'Active Accounts' : 'Archived Accounts' }}
                    </x-ui.button>
                    @if (Auth::user()->role == 'admin')
                    <x-ui.button
                        variant="outline-primary"
                        size="sm"
                        icon="cil-plus"
                        data-toggle="modal"
                        data-target="#employeeCreateModal"
                    >
                        Add Employee
                    </x-ui.button>
                    @endif
                </x-slot:actions>
            @endif
        </x-page-header>
        <x-ui.table-card
            title="Employee Directory"
            subtitle="{{ (($accountScope ?? 'active') === 'archived') ? 'Review archived accounts and reactivate when appropriate.' : 'Filter and review employee records.' }}"
            class="border-0 hrms-list-card"
        >
            <x-slot:controls>
                @php
                    $showEmployeeAdvancedFilters = filled($departmentFilter)
                        || filled($positionFilter)
                        || (($sortFilter ?? 'name_asc') !== 'name_asc');
                @endphp
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('employees.index')"
                    class="employees-index-toolbar employee-index-toolbar mb-0"
                >
                    <input type="hidden" name="account_scope" value="{{ $accountScope ?? 'active' }}" />
                    <div class="employees-filter-shell">
                        <div class="employees-filter-primary">
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search employees-filter-search"
                            >
                                <label class="form-label" for="employeesTableSearch"
                                    >Search</label
                                >
                                <input
                                    id="employeesTableSearch"
                                    type="search"
                                    name="search"
                                    value="{{ $search ?? '' }}"
                                    class="form-control form-control-sm employees-toolbar-search"
                                    placeholder="Search"
                                />
                            </div>
                            <div class="employees-filter-toggle-wrap">
                                <label class="form-label employees-filter-toggle-label" for="employeesToolbarFiltersToggle"
                                    >Filters</label
                                >
                                <x-ui.button
                                    type="button"
                                    :variant="$showEmployeeAdvancedFilters ? 'primary' : 'outline-secondary'"
                                    size="sm"
                                    icon="cil-filter"
                                    id="employeesToolbarFiltersToggle"
                                    class="employees-filter-toggle"
                                    data-coreui-toggle="collapse"
                                    data-coreui-target="#employeesFiltersCollapse"
                                    aria-expanded="{{ $showEmployeeAdvancedFilters ? 'true' : 'false' }}"
                                    aria-controls="employeesFiltersCollapse"
                                >
                                    Filters
                                </x-ui.button>
                            </div>
                        </div>

                        <div id="employeesFiltersCollapse" class="employees-filter-panel collapse {{ $showEmployeeAdvancedFilters ? 'show' : '' }}">
                            <div class="offcanvas-body employees-filter-offcanvas-body">
                                <div class="employees-filter-grid">
                                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                        <label
                                            class="form-label"
                                            for="employeesDepartmentFilter"
                                            >Department</label
                                        >
                                        <select
                                            id="employeesDepartmentFilter"
                                            name="department"
                                            class="form-control form-control-sm employees-toolbar-filter select2bs4"
                                            data-toolbar-select2="1"
                                            data-placeholder="All departments"
                                            data-allow-clear="1"
                                        >
                                            <option value=""></option>
                                            @foreach ($toolbarDepartments as $department)
                                                <option
                                                    value="{{ strtolower(trim((string) $department->department)) }}"
                                                    @selected (($departmentFilter ?? '') === strtolower(trim((string) $department->department)))
                                                    >{{ $department->department }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                        <label
                                            class="form-label"
                                            for="employeesPositionFilter"
                                            >Position</label
                                        >
                                        <select
                                            id="employeesPositionFilter"
                                            name="position"
                                            class="form-control form-control-sm employees-toolbar-filter select2bs4"
                                            data-toolbar-select2="1"
                                            data-placeholder="All positions"
                                            data-allow-clear="1"
                                        >
                                            <option value=""></option>
                                            @foreach ($toolbarPositions as $position)
                                                <option
                                                    value="{{ strtolower(trim((string) $position)) }}"
                                                    @selected (($positionFilter ?? '') === strtolower(trim((string) $position)))
                                                    >{{ $position }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                        <label class="form-label" for="employeesSortFilter"
                                            >Sort</label
                                        >
                                        <select
                                            id="employeesSortFilter"
                                            name="sort"
                                            class="form-control form-control-sm employees-toolbar-filter select2bs4"
                                            data-toolbar-select2="1"
                                            data-placeholder="Sort"
                                            data-allow-clear="0"
                                        >
                                            <option
                                                value="name_asc"
                                                @selected (($sortFilter ?? 'name_asc') === 'name_asc')
                                                >Name
                                            </option>
                                            <option
                                                value="name_desc"
                                                @selected (($sortFilter ?? '') === 'name_desc')
                                                >Name Desc
                                            </option>
                                            <option
                                                value="department_asc"
                                                @selected (($sortFilter ?? '') === 'department_asc')
                                                >Department
                                            </option>
                                            <option
                                                value="position_asc"
                                                @selected (($sortFilter ?? '') === 'position_asc')
                                                >Position
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action employees-filter-apply"
                                    >
                                        <x-ui.button
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                            id="employeesToolbarApply"
                                        >
                                            Apply
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <div class="table-responsive hrms-table">
                <table
                    class="table table-hover mb-0 align-middle datatable hrms-list-table hrms-table"
                >
                    <thead
                        class="bg-light text-uppercase small font-weight-bold"
                    >
                        <tr>
                            <th class="pl-4 py-3">Employee</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Position</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (Auth::user()?->employee && Auth::user()->role !== 'admin')
                            @php
                            $currentEmployee = Auth::user()->employee;
                            $currentFullName = trim(($currentEmployee->first_name ?? '') . ' ' . ($currentEmployee->last_name ?? ''));
                            $currentPositionLabel = ucfirst($currentEmployee->positions->first()?->position?->position ?? 'Unassigned');
                            if (strtolower($currentPositionLabel) === 'head' && strtolower($currentEmployee->department?->department_type ?? '') === 'academic') {
                                $currentPositionLabel = 'Dean';
                            }
                            $currentAvatarUrl = null;
                            if (!empty(Auth::user()->avatar)) {
                                $parts = explode('/', Auth::user()->avatar);
                                $folder = $parts[0] ?? null;
                                $subfolder = $parts[1] ?? null;
                                $filename = $parts[2] ?? null;
                                if ($folder && $subfolder && $filename) {
                                    $currentAvatarUrl = route('storage.file', [
                                        'folder' => $folder,
                                        'subfolder' => $subfolder,
                                        'filename' => $filename,
                                    ]);
                                }
                            }
                            $currentAvatarLetter = strtoupper(substr($currentEmployee->first_name ?? Auth::user()->name ?? 'U', 0, 1));
                            $currentAvatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
                                . '<rect width="100%" height="100%" fill="#e9ecef"/>'
                                . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="52" fill="#007bff">'
                                . e($currentAvatarLetter)
                                . '</text></svg>';
                            $currentAvatarFallback = 'data:image/svg+xml;base64,' . base64_encode($currentAvatarSvg);
                        @endphp
                        @endif
                        @forelse ($employees as $emp)
                            @if (Auth::user()?->employee && (int) $emp->id === (int) Auth::user()->employee->id)
                                @continue
                            @endif
                            @php
                            $avatarUrl = null;
                            if (!empty($emp->user?->avatar)) {
                                $parts = explode('/', $emp->user->avatar);
                                $folder = $parts[0] ?? null;
                                $subfolder = $parts[1] ?? null;
                                $filename = $parts[2] ?? null;
                                if ($folder && $subfolder && $filename) {
                                    $avatarUrl = route('storage.file', [
                                        'folder' => $folder,
                                        'subfolder' => $subfolder,
                                        'filename' => $filename,
                                    ]);
                                }
                            }
                            $avatarLetter = strtoupper(substr($emp->first_name ?? $emp->user?->name ?? 'U', 0, 1));
                            $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
                                . '<rect width="100%" height="100%" fill="#e9ecef"/>'
                                . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="52" fill="#007bff">'
                                . e($avatarLetter)
                                . '</text></svg>';
                            $avatarFallback = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg);
                            $fullName = trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''));
                            $positionLabel = ucfirst($emp->positions->first()?->position?->position ?? 'Unassigned');
                            if (strtolower($positionLabel) === 'head' && strtolower($emp->department?->department_type ?? '') === 'academic') {
                                $positionLabel = 'Dean';
                            }
                            $editPositionIds = $emp->positions
                                ->pluck('position_id')
                                ->map(fn ($positionId) => (int) $positionId)
                                ->values()
                                ->all();
                            $editPayload = [
                                'update_url' => route('employees.update', $emp->id),
                                'reset_password_url' => $emp->user_id ? route('employees.reset-password', $emp) : '',
                                'employee_id' => $emp->id,
                                'employee_id_value' => $emp->employee_id,
                                'rfid' => $emp->nfc?->nfc_uid,
                                'status' => $emp->status,
                                'first_name' => $emp->first_name,
                                'last_name' => $emp->last_name,
                                'address' => $emp->address,
                                'department_id' => $emp->department_id,
                                'gender' => $emp->user?->gender ?? '',
                                'position_ids' => $editPositionIds,
                                'hire_date' => $emp->hire_date,
                            ];
                        @endphp
                            <tr data-search="{{ $fullName }}">
                                <td class="align-middle pl-4">
                                    <div class="employee-card">
                                        <img
                                            src="{{ $avatarUrl ?: $avatarFallback }}"
                                            alt="{{ $fullName ?: 'Employee' }}"
                                            class="employee-avatar"
                                        />
                                        <div class="employee-meta">
                                            <div
                                                class="employee-name text-dark font-weight-bold"
                                            >
                                                {{ $fullName ?: 'N/A' }}
                                            </div>
                                            <div class="employee-id text-muted">
                                                #{{ $emp->employee_id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <span
                                        class="text-muted small font-weight-bold"
                                        >{{ $emp->department->department ?? 'N/A' }}</span
                                    >
                                </td>
                                <td class="align-middle">
                                    <span
                                        class="text-dark small"
                                        >{{ $positionLabel }}</span
                                    >
                                </td>
                                <td class="align-middle text-center">
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        @if (($accountScope ?? 'active') === 'archived')
                                            @if ($emp->user_id && (Auth::user()->isAdmin() || \App\Services\AccessControl::isHrHead(Auth::user())))
                                                <form
                                                    action="{{ route('users.activate', $emp->user_id) }}"
                                                    method="POST"
                                                    class="d-inline"
                                                    data-confirm-message="Reactivate {{ $fullName ?: 'this employee' }} account?"
                                                    data-confirm-title="Reactivate Account"
                                                    data-confirm-label="Activate"
                                                    data-confirm-variant="success"
                                                >
                                                    @csrf
                                                    <x-ui.button
                                                        type="submit"
                                                        variant="success"
                                                        size="sm"
                                                        icon="cil-check-circle"
                                                        aria-label="Activate Account"
                                                        title="Activate Account"
                                                    >
                                                        Activate
                                                    </x-ui.button>
                                                </form>
                                            @else
                                                <span class="text-muted small">No actions</span>
                                            @endif
                                        @else
                                            <x-ui.button
                                                type="view"
                                                size="sm"
                                                class="view-employee"
                                                data-toggle="modal"
                                                data-target="#employeeDetailsModal"
                                                data-name="{{ $fullName ?: 'Employee' }}"
                                                data-employee-id="{{ $emp->employee_id }}"
                                                data-first-name="{{ $emp->first_name ?? '' }}"
                                                data-last-name="{{ $emp->last_name ?? '' }}"
                                                data-gender="{{ $emp->user?->gender ?? '-' }}"
                                                data-status="{{ $emp->status ?? '-' }}"
                                                data-department="{{ $emp->department?->department ?? 'N/A' }}"
                                                data-position="{{ $positionLabel }}"
                                                data-address="{{ $emp->address ?? '-' }}"
                                                data-hire-date="{{ $emp->hire_date ? \Illuminate\Support\Carbon::parse($emp->hire_date)->format('M d, Y') : '-' }}"
                                                data-email="{{ $emp->user?->email ?? '-' }}"
                                                data-is-admin="{{ Auth::user()->isAdmin() ? '1' : '0' }}"
                                                data-is-hr-head="{{ \App\Services\AccessControl::isHrHead(Auth::user()) ? '1' : '0' }}"
                                                data-employee-row-id="{{ $emp->id }}"
                                                data-user-id="{{ $emp->user_id }}"
                                                data-reset-password-url="{{ $emp->user_id ? route('employees.reset-password', $emp) : '' }}"
                                                data-reactivate-url="{{ $emp->user_id ? route('users.activate', $emp->user_id) : '' }}"
                                                data-rfid="{{ $emp->nfc?->nfc_uid ?? '' }}"
                                                data-archived="{{ $emp->user?->archived_at ? '1' : '0' }}"
                                                data-avatar="{{ $avatarUrl ?: $avatarFallback }}"
                                                aria-label="View Employee"
                                                title="View Employee"
                                            />
                                            <x-ui.button
                                                type="documents"
                                                size="sm"
                                                class="open-employee-documents-modal"
                                                data-toggle="modal"
                                                data-target="#employeeDocumentsModal"
                                                data-documents-url="{{ route('employee-documents.index', ['employee_id' => $emp->id, 'embedded' => 1]) }}"
                                                data-employee-name="{{ $fullName ?: 'Employee' }}"
                                                data-employee-code="{{ $emp->employee_id }}"
                                                data-avatar="{{ $avatarUrl ?: $avatarFallback }}"
                                                aria-label="View Employee Documents"
                                                title="View Employee Documents"
                                            />
                                            @if (\App\Services\AccessControl::isHrHead(Auth::user()))
                                                <x-ui.button
                                                    type="rfid"
                                                    size="sm"
                                                    class="open-employee-rfid-modal"
                                                    data-toggle="modal"
                                                    data-target="#employeeRfidModal"
                                                    data-name="{{ $fullName ?: 'Employee' }}"
                                                    data-employee-id="{{ $emp->employee_id }}"
                                                    data-employee-row-id="{{ $emp->id }}"
                                                    data-rfid="{{ $emp->nfc?->nfc_uid ?? '' }}"
                                                    aria-label="Register RFID"
                                                    title="Register RFID"
                                                />
                                            @endif
                                            @can ('manage-employees')
                                                <x-ui.button
                                                    type="edit"
                                                    size="sm"
                                                    class="edit-employee-trigger"
                                                    data-toggle="modal"
                                                    data-target="#employeeEditModal"
                                                    data-edit='@json($editPayload)'
                                                    data-update-url="{{ route('employees.update', $emp->id) }}"
                                                    data-employee-row-id="{{ $emp->id }}"
                                                    data-employee-id-value="{{ $emp->employee_id }}"
                                                    data-rfid="{{ $emp->nfc?->nfc_uid ?? '' }}"
                                                    data-status="{{ $emp->status ?? 'active' }}"
                                                    data-first-name="{{ $emp->first_name ?? '' }}"
                                                    data-last-name="{{ $emp->last_name ?? '' }}"
                                                    data-address="{{ $emp->address ?? '' }}"
                                                    data-department-id="{{ $emp->department_id ?? '' }}"
                                                    data-gender="{{ $emp->user?->gender ?? '' }}"
                                                    data-position-ids='@json($editPositionIds)'
                                                    data-reset-password-url="{{ $emp->user_id ? route('employees.reset-password', $emp) : '' }}"
                                                    data-hire-date="{{ $emp->hire_date ?? '' }}"
                                                    aria-label="Edit Employee"
                                                    title="Edit Employee"
                                                />
                                            @endcan
                                            @if (Auth::user()->isAdmin())
                                                <form
                                                    action="{{ route('employees.destroy', $emp) }}"
                                                    method="POST"
                                                    class="d-inline"
                                                    data-confirm-message="Archive {{ $fullName ?: 'this employee' }}?"
                                                    data-confirm-title="Archive Employee"
                                                    data-confirm-label="Archive"
                                                    data-confirm-variant="danger"
                                                >
                                                    @csrf
                                                    @method ('DELETE')
                                                    <x-ui.button
                                                        type="submit"
                                                        variant="delete"
                                                        size="sm"
                                                        aria-label="Archive Employee"
                                                        title="Archive Employee"
                                                    />
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="opacity-50">
                                        <i
                                            class="cil-user-unfollow fs-1 mb-3 text-muted"
                                        ></i>
                                        <h5 class="text-muted">
                                            No records found
                                        </h5>
                                        <p class="text-muted small">Adjust search filters or verify the ID, then try again.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                {{ $employees->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
    <x-modal
        id="employeeCreateModal"
        size="xl"
        title="Register New Employee"
        subtitle="Create a new profile and assign access details."
        content-class="employee-modal employee-modal--form"
    >
        <x-slot:body>
                <form
                    id="employeeCreateForm"
                    action="{{ route('employees.store') }}"
                    method="POST"
                    class="employee-form hrms-form-layout"
                >
                    @csrf
                    <input
                        type="hidden"
                        name="form_context"
                        value="employee_create"
                    />
                    <div>
                        <div class="form-section">
                            <div class="form-section-title">
                                Account Information
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_email"
                                            >Login Email (Account)
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"
                                                    ><i
                                                        class="cil-envelope-open"
                                                    ></i
                                                ></span>
                                            </div>
                                            <input
                                                type="email"
                                                name="email"
                                                id="employee_create_email"
                                                class="form-control {{ FormValidation::invalidClass('email', 'employee_create') }}"
                                                placeholder="employee@company.com"
                                                value="{{ old('email') }}"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_employee_id"
                                            >Employee ID
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"
                                                    ><i class="cil-badge"></i
                                                ></span>
                                            </div>
                                            <input
                                                type="text"
                                                name="employee_id"
                                                id="employee_create_employee_id"
                                                class="form-control {{ FormValidation::invalidClass('employee_id', 'employee_create') }}"
                                                placeholder="e.g., 26-00001"
                                                value="{{ old('employee_id', $nextEmployeeId ?? '') }}"
                                                required
                                                readonly
                                            />
                                        </div>
                                        <x-ui.form-error field="employee_id" context="employee_create" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                Personal Information
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_first_name"
                                            >First Name</label
                                        >
                                        <input
                                            type="text"
                                            name="first_name"
                                            id="employee_create_first_name"
                                            class="form-control {{ FormValidation::invalidClass('first_name', 'employee_create') }}"
                                            value="{{ old('first_name') }}"
                                            pattern="[A-Za-z]+(?:[ .'-][A-Za-z]+)*"
                                            data-validation-message="First name can contain letters, spaces, apostrophes, periods, and hyphens only."
                                            maxlength="255"
                                            autocomplete="given-name"
                                            required
                                        />
                                        <x-ui.form-error field="first_name" context="employee_create" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_last_name"
                                            >Last Name</label
                                        >
                                        <input
                                            type="text"
                                            name="last_name"
                                            id="employee_create_last_name"
                                            class="form-control {{ FormValidation::invalidClass('last_name', 'employee_create') }}"
                                            value="{{ old('last_name') }}"
                                            pattern="[A-Za-z]+(?:[ .'-][A-Za-z]+)*"
                                            data-validation-message="Last name can contain letters, spaces, apostrophes, periods, and hyphens only."
                                            maxlength="255"
                                            autocomplete="family-name"
                                            required
                                        />
                                        <x-ui.form-error field="last_name" context="employee_create" />
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_gender"
                                            >Gender</label
                                        >
                                        <select
                                            name="gender"
                                            id="employee_create_gender"
                                            class="form-control select2bs4 {{ FormValidation::invalidClass('gender', 'employee_create') }}"
                                            data-placeholder="Select gender"
                                            required
                                        >
                                            <option value="">
                                                -- Select Gender --
                                            </option>
                                            <option
                                                value="male"
                                                {{ old('gender') === 'male' ? 'selected' : '' }}
                                                >Male
                                            </option>
                                            <option
                                                value="female"
                                                {{ old('gender') === 'female' ? 'selected' : '' }}
                                                >Female
                                            </option>
                                        </select>
                                        <x-ui.form-error field="gender" context="employee_create" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_address"
                                            >Address</label
                                        >
                                        <textarea
                                            name="address"
                                            id="employee_create_address"
                                            class="form-control {{ FormValidation::invalidClass('address', 'employee_create') }}"
                                            rows="2"
                                            placeholder="Enter current address"
                                            >{{ old('address') }}</textarea
                                        >
                                        <x-ui.form-error field="address" context="employee_create" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                Organizational Assignment
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_department"
                                            >Department</label
                                        >
                                        <select
                                            class="form-control employee-department {{ FormValidation::invalidClass('department_id', 'employee_create') }}"
                                            name="department_id"
                                            id="employee_create_department"
                                            required
                                        >
                                            <option value="">
                                                -- Select Department --
                                            </option>
                                            @forelse ($departments as $dept)
                                                <option
                                                    value="{{ $dept->id }}"
                                                    {{ old('department_id') == $dept->id ? 'selected' : '' }}
                                                >
                                                    {{ $dept->department }}
                                                </option>
                                            @empty
                                                <option value="" disabled>
                                                    No departments found
                                                </option>
                                            @endforelse
                                        </select>
                                        <x-ui.form-error field="department_id" context="employee_create" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        @php $createSelectedPositions = collect(old('position_ids', old('position_id') ? [old('position_id')] : []))->map(fn ($value) => (string) $value)->all(); @endphp
                                        <label for="employee_create_positions"
                                            >Positions</label
                                        >
                                        <select
                                            class="form-control select2bs4 employee-positions {{ FormValidation::invalidClass('position_ids', 'employee_create') }}"
                                            name="position_ids[]"
                                            id="employee_create_positions"
                                            data-placeholder="Select positions"
                                            data-selected='@json(old("position_ids", old("position_id") ? [old("position_id")] : []))'
                                            data-url-base="{{ url('departments') }}"
                                            multiple
                                            required
                                        >
                                            @forelse ($positions as $pos)
                                                <option
                                                    value="{{ $pos->id }}"
                                                    {{ in_array((string) $pos->id, $createSelectedPositions, true) ? 'selected' : '' }}
                                                >
                                                    {{ $pos->position }}
                                                </option>
                                            @empty
                                                <option value="" disabled>
                                                    No positions found
                                                </option>
                                            @endforelse
                                        </select>
                                        <x-ui.form-error field="position_ids" context="employee_create" />
                                        <small
                                            class="employee-availability text-muted d-block mt-1"
                                        ></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                Access Credential
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_create_nfc_uid"
                                            >RFID</label
                                        >
                                        <input
                                            type="text"
                                            name="nfc_uid"
                                            id="employee_create_nfc_uid"
                                            class="form-control employee-rfid-input"
                                            value="{{ old('nfc_uid') }}"
                                            readonly
                                        />
                                        <small
                                            class="employee-rfid-status text-muted d-block mt-1"
                                            id="employee_create_rfid_status"
                                            >Waiting for RFID scan...</small
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
        </x-slot:body>
        <x-slot:footer>
            <form method="POST" id="employeeReactivateForm" class="d-none">
                @csrf
            </form>
            <button
                type="submit"
                class="btn btn-success btn-sm d-none"
                id="employeeReactivateButton"
                form="employeeReactivateForm"
            >
                Reactivate
            </button>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn btn-primary btn-sm"
                form="employeeCreateForm"
            >
                Register
            </button>
        </x-slot:footer>
    </x-modal>
    <x-modal
        id="employeeEditModal"
        size="xl"
        title="Edit Employee Profile"
        subtitle="Update personal details, assignments, and access."
        content-class="employee-modal employee-modal--form"
    >
        <x-slot:body>
                <form
                    id="employeeEditForm"
                    action="#"
                    method="POST"
                    class="employee-form hrms-form-layout"
                >
                    @csrf
                    @method ('PUT')
                    <input
                        type="hidden"
                        name="form_context"
                        value="employee_edit"
                    />
                    <input
                        type="hidden"
                        name="update_url"
                        id="employee_edit_update_url"
                        value="{{ old('update_url') }}"
                    />
                    <div>
                        <div class="form-section">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_employee_id"
                                            >Employee ID</label
                                        >
                                        <input
                                            type="text"
                                            name="employee_id"
                                            id="employee_edit_employee_id"
                                            class="form-control {{ FormValidation::invalidClass('employee_id', 'employee_edit') }}"
                                            value="{{ old('employee_id') }}"
                                            required
                                            readonly
                                        />
                                        <x-ui.form-error field="employee_id" context="employee_edit" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_status"
                                            >Employment Status</label
                                        >
                                        <select
                                            name="status"
                                            id="employee_edit_status"
                                            class="form-control select2bs4 {{ FormValidation::invalidClass('status', 'employee_edit') }}"
                                            data-placeholder="Select status"
                                            required
                                        >
                                            <option
                                                value="active"
                                                {{ old('status') == 'active' ? 'selected' : '' }}
                                                >Active
                                            </option>
                                            <option
                                                value="inactive"
                                                {{ old('status') == 'inactive' ? 'selected' : '' }}
                                                >Inactive
                                            </option>
                                        </select>
                                        <x-ui.form-error field="status" context="employee_edit" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_first_name"
                                            >First Name</label
                                        >
                                        <input
                                            type="text"
                                            name="first_name"
                                            id="employee_edit_first_name"
                                            class="form-control {{ FormValidation::invalidClass('first_name', 'employee_edit') }}"
                                            value="{{ old('first_name') }}"
                                            pattern="[A-Za-z]+(?:[ .'-][A-Za-z]+)*"
                                            data-validation-message="First name can contain letters, spaces, apostrophes, periods, and hyphens only."
                                            maxlength="255"
                                            autocomplete="given-name"
                                            required
                                        />
                                        <x-ui.form-error field="first_name" context="employee_edit" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_last_name"
                                            >Last Name</label
                                        >
                                        <input
                                            type="text"
                                            name="last_name"
                                            id="employee_edit_last_name"
                                            class="form-control {{ FormValidation::invalidClass('last_name', 'employee_edit') }}"
                                            value="{{ old('last_name') }}"
                                            pattern="[A-Za-z]+(?:[ .'-][A-Za-z]+)*"
                                            data-validation-message="Last name can contain letters, spaces, apostrophes, periods, and hyphens only."
                                            maxlength="255"
                                            autocomplete="family-name"
                                            required
                                        />
                                        <x-ui.form-error field="last_name" context="employee_edit" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_gender"
                                            >Gender</label
                                        >
                                        <select
                                            name="gender"
                                            id="employee_edit_gender"
                                            class="form-control select2bs4 {{ FormValidation::invalidClass('gender', 'employee_edit') }}"
                                            data-placeholder="Select gender"
                                            required
                                        >
                                            <option value="">
                                                -- Select Gender --
                                            </option>
                                            <option
                                                value="male"
                                                {{ old('gender') === 'male' ? 'selected' : '' }}
                                                >Male
                                            </option>
                                            <option
                                                value="female"
                                                {{ old('gender') === 'female' ? 'selected' : '' }}
                                                >Female
                                            </option>
                                        </select>
                                        <x-ui.form-error field="gender" context="employee_edit" />
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="employee_edit_address"
                                            >Address</label
                                        >
                                        <textarea
                                            name="address"
                                            id="employee_edit_address"
                                            class="form-control {{ FormValidation::invalidClass('address', 'employee_edit') }}"
                                            rows="2"
                                            placeholder="Enter current address"
                                            >{{ old('address') }}</textarea
                                        >
                                        <x-ui.form-error field="address" context="employee_edit" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_department"
                                            >Department</label
                                        >
                                        <select
                                            name="department_id"
                                            id="employee_edit_department"
                                            class="form-control employee-department {{ FormValidation::invalidClass('department_id', 'employee_edit') }}"
                                            required
                                        >
                                            <option value="">
                                                -- Select Department --
                                            </option>
                                            @forelse ($departments as $dept)
                                                <option
                                                    value="{{ $dept->id }}"
                                                    {{ (string) old('department_id') === (string) $dept->id ? 'selected' : '' }}
                                                >
                                                    {{ $dept->department }}
                                                </option>
                                            @empty
                                                <option value="" disabled>
                                                    No departments found
                                                </option>
                                            @endforelse
                                        </select>
                                        <x-ui.form-error field="department_id" context="employee_edit" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        @php $editSelectedPositions = collect(old('position_ids', old('position_id') ? [old('position_id')] : []))->map(fn ($value) => (string) $value)->all(); @endphp
                                        <label for="employee_edit_positions"
                                            >Positions</label
                                        >
                                        <select
                                            name="position_ids[]"
                                            id="employee_edit_positions"
                                            class="form-control select2bs4 employee-positions {{ FormValidation::invalidClass('position_ids', 'employee_edit') }}"
                                            data-placeholder="Select positions"
                                            data-selected='@json(old("position_ids", old("position_id") ? [old("position_id")] : []))'
                                            data-employee-id="{{ old('employee_id') }}"
                                            data-url-base="{{ url('departments') }}"
                                            multiple
                                            required
                                        >
                                            @forelse ($positions as $pos)
                                                <option
                                                    value="{{ $pos->id }}"
                                                    {{ in_array((string) $pos->id, $editSelectedPositions, true) ? 'selected' : '' }}
                                                >
                                                    {{ $pos->position }}
                                                </option>
                                            @empty
                                                <option value="" disabled>
                                                    No positions found
                                                </option>
                                            @endforelse
                                        </select>
                                        <x-ui.form-error field="position_ids" context="employee_edit" />
                                        <small
                                            class="employee-availability text-muted d-block mt-1"
                                        ></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_edit_nfc_uid"
                                            >RFID</label
                                        >
                                        <input
                                            type="text"
                                            name="nfc_uid"
                                            id="employee_edit_nfc_uid"
                                            class="form-control employee-rfid-input"
                                            readonly
                                            value="{{ old('nfc_uid') }}"
                                        />
                                        <small
                                            class="employee-rfid-status text-muted d-block mt-1"
                                            id="employee_edit_rfid_status"
                                            >Tap a card to capture or replace
                                            RFID.</small
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
        </x-slot:body>
        <x-slot:footer>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn btn-primary btn-sm"
                form="employeeEditForm"
            >
                Save
            </button>
        </x-slot:footer>
    </x-modal>
    <x-modal
        id="nfcDuplicateModal"
        title="RFID Already Registered"
        content-class="employee-modal employee-modal--notice"
    >
        <x-slot:body>
            <p class="mb-0 text-muted">
                The scanned RFID is already registered to another employee.
            </p>
        </x-slot:body>
        <x-slot:footer>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Close
            </button>
        </x-slot:footer>
    </x-modal>
    <x-modal
        id="employeeRfidModal"
        title="Register Employee RFID"
        subtitle="Tap a card to capture or replace the employee RFID."
        content-class="employee-modal employee-modal--rfid"
    >
        <x-slot:body>
            <div class="employee-modal-panel employee-modal-panel--rfid">
                <div class="employee-rfid-summary">
                    <span class="employee-rfid-summary__name" id="employee_rfid_name">Employee</span>
                    <span class="employee-rfid-summary__id text-muted" id="employee_rfid_employee_id">#-</span>
                </div>
                <form method="POST" action="{{ route('attendance.rfid.assign') }}" id="employeeRfidForm">
                    @csrf
                    <input type="hidden" name="employee_id" id="employee_rfid_employee_row_id" value="" />
                    <div class="employee-rfid-stack">
                        <div class="employee-rfid-row">
                            <div class="employee-rfid-input-wrap">
                                <label for="employee_modal_nfc_uid" class="employee-rfid-title">RFID</label>
                                <input
                                    type="text"
                                    name="nfc_uid"
                                    id="employee_modal_nfc_uid"
                                    class="form-control employee-rfid-input"
                                    readonly
                                    value=""
                                />
                                <small
                                    class="employee-rfid-status text-muted d-block mt-1"
                                    id="employee_modal_rfid_status"
                                >Tap a card to capture or replace RFID.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn btn-primary btn-sm"
                form="employeeRfidForm"
            >
                Save
            </button>
        </x-slot:footer>
    </x-modal>
    <x-modal
        id="employeeDetailsModal"
        size="xl"
        title="Employee Details"
        dialog-class="employee-detail-modal-dialog"
        content-class="employee-modal employee-modal--details"
    >
        <x-slot:body>
            <div class="employee-modal-panel employee-modal-panel--details">
                <div class="employee-detail-shell">
                    <section class="employee-detail-card employee-detail-card--identity">
                        <div class="employee-detail-header">
                            <div class="employee-detail-identity">
                                <img
                                    id="employee_detail_avatar"
                                    src=""
                                    alt="Employee avatar"
                                    class="employee-avatar-lg"
                                />
                                <div class="employee-detail-identity-copy">
                                    <div class="employee-detail-eyebrow">Profile Overview</div>
                                    <div
                                        class="employee-detail-name"
                                        id="employee_detail_name"
                                    >
                                        Employee
                                    </div>
                                    <div
                                        class="employee-detail-id text-muted"
                                        id="employee_detail_id"
                                    >
                                        #-
                                    </div>
                                </div>
                            </div>
                            <div class="employee-detail-identity-side">
                                @if (Auth::user()->isAdmin())
                                    <form
                                        method="POST"
                                        id="employeeDetailResetPasswordForm"
                                        class="employee-detail-reset-form d-none"
                                        action="#"
                                        data-confirm-message="Reset this employee password to the default system password?"
                                        data-confirm-title="Reset Password"
                                        data-confirm-label="Reset"
                                        data-confirm-variant="warning"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn btn-outline-warning employee-detail-reset-btn"
                                            aria-label="Reset Password"
                                            title="Reset Password"
                                        >
                                            <i class="cil-lock-locked hrms-btn__icon" aria-hidden="true"></i>
                                            <span class="sr-only">Reset Password</span>
                                        </button>
                                    </form>
                                @endif
                                <span
                                    id="employee_detail_archived_badge"
                                    class="badge badge-danger d-none"
                                >Archived Account</span>
                                <div class="employee-detail-mini-note">
                                    Employee profile snapshot
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="employee-detail-card">
                        <div class="employee-detail-card__header">
                            <div>
                                <div class="employee-detail-card__eyebrow">At a Glance</div>
                                <div class="employee-detail-section-title">Employment Summary</div>
                            </div>
                        </div>
                        <div class="employee-detail-grid employee-detail-grid--summary">
                            <div class="detail-item">
                                <span class="detail-label">Gender</span>
                                <span
                                    class="detail-value"
                                    id="employee_detail_gender"
                                >-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Employment Status</span>
                                <span
                                    class="detail-value"
                                    id="employee_detail_status"
                                >-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Hire Date</span>
                                <span
                                    class="detail-value"
                                    id="employee_detail_hire_date"
                                >-</span>
                            </div>
                        </div>
                    </section>

                    <div class="employee-detail-columns">
                        <section class="employee-detail-card">
                            <div class="employee-detail-card__header">
                                <div>
                                    <div class="employee-detail-card__eyebrow">Workplace</div>
                                    <div class="employee-detail-section-title">Organization</div>
                                </div>
                            </div>
                            <div class="employee-detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Department</span>
                                    <span
                                        class="detail-value"
                                        id="employee_detail_department"
                                    >-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Position</span>
                                    <span
                                        class="detail-value"
                                        id="employee_detail_position"
                                    >-</span>
                                </div>
                            </div>
                        </section>

                        <section class="employee-detail-card">
                            <div class="employee-detail-card__header">
                                <div>
                                    <div class="employee-detail-card__eyebrow">Reachability</div>
                                    <div class="employee-detail-section-title">Contact</div>
                                </div>
                            </div>
                            <div class="employee-detail-grid">
                                <div class="detail-item detail-item--wide">
                                    <span class="detail-label">Email</span>
                                    <span
                                        class="detail-value"
                                        id="employee_detail_email"
                                    >-</span>
                                </div>
                                <div class="detail-item detail-item--wide">
                                    <span class="detail-label">Address</span>
                                    <span
                                        class="detail-value"
                                        id="employee_detail_address"
                                    >-</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Close
            </button>
        </x-slot:footer>
    </x-modal>
    <x-modal
        id="employeeDocumentsModal"
        size="xl"
        title="Employee Documents"
        scrollable="true"
        content-class="employee-modal employee-modal--documents"
    >
        <x-slot:body>
            <div class="employee-modal-panel employee-modal-panel--documents">
            <div class="employee-documents-frame-wrap">
                <iframe
                    id="employee_documents_frame"
                    title="Employee documents"
                    class="employee-documents-frame"
                    src="about:blank"
                    loading="lazy"
                ></iframe>
            </div>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button
                type="button"
                class="btn btn-light btn-sm"
                data-coreui-dismiss="modal"
            >
                Close
            </button>
        </x-slot:footer>
    </x-modal>
@endsection
