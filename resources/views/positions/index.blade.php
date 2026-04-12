@extends ('layouts.admin')
@section ('content')
    @php use App\Support\FormValidation; @endphp
    <div
        id="positionsIndexPage"
        data-create-error="{{ $errors->any() && old('form_context') === 'position_create' ? '1' : '0' }}"
        data-edit-error="{{ $errors->any() && old('form_context') === 'position_edit' ? '1' : '0' }}"
    >
        <x-ui.hero
            title="Positions"
            subtitle="Manage role titles and review employees assigned to each position."
        >
            @if (Auth::user()->role == 'admin')
                <x-slot:actions>
                    <x-ui.button
                        variant="outline-primary"
                        size="sm"
                        icon="cil-plus"
                        data-toggle="modal"
                        data-target="#positionCreateModal"
                    >
                        Add Position
                    </x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.hero>
        <x-ui.table-card
            title="Position List"
            class="positions-card hrms-list-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('positions.index')"
                    class="positions-index-toolbar mb-0"
                >
                        <div class="positions-index-toolbar-grid">
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                            >
                            <label class="form-label" for="positionsTableSearch"
                                >Search</label
                            >
                            <input
                                id="positionsTableSearch"
                                type="search"
                                name="search"
                                value="{{ $search ?? '' }}"
                                class="form-control form-control-sm positions-toolbar-search"
                                    placeholder="Search position name"
                            />
                            </div>
                            <div
                                class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                            >
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                size="sm"
                                id="positionsToolbarApply"
                            >
                                Apply
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <table
                class="table table-hover align-middle mb-0 hrms-table positions-table"
                id="positionsTable"
            >
                <thead class="bg-light">
                    <tr class="text-uppercase text-muted small">
                        <th class="pl-4 py-3">Position Name</th>
                        <th class="py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($positions as $pos)
                        @php
                            $positionDepartmentIdList = ($positionDepartmentIds[$pos->position] ?? collect([$pos->department_id]))
                                ->filter()
                                ->values();
                            $positionEditPayload = [
                                'update_url' => route('positions.update', $pos),
                                'name' => $pos->position,
                                'department_ids' => $positionDepartmentIdList->all(),
                                'employee_limit' => $hasEmployeeLimitScope ? ($pos->employee_limit ?? null) : null,
                            ];
                        @endphp
                        <tr class="position-row">
                            <td class="align-middle pl-4 text-capitalize">
                                <button
                                    type="button"
                                    class="position-pill position-members-trigger border-0"
                                    data-toggle="modal"
                                    data-target="#positionMembersModal"
                                    data-position-name="{{ ucfirst($pos->position) }}"
                                    data-members-url="{{ route('positions.members', $pos) }}"
                                >
                                    <span
                                        class="icon-shape icon-sm bg-light rounded text-primary border"
                                    >
                                        <i class="cil-badge"></i>
                                    </span>
                                    <span>{{ ucfirst($pos->position) }}</span>
                                </button>
                            </td>
                            <td class="align-middle text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        class="position-members-trigger"
                                        data-toggle="modal"
                                        data-target="#positionMembersModal"
                                        data-position-name="{{ ucfirst($pos->position) }}"
                                        data-members-url="{{ route('positions.members', $pos) }}"
                                        aria-label="View Position Members"
                                        title="View Position Members"
                                    />
                                    @can ('manage-positions')
                                        <x-ui.button
                                            type="edit"
                                            size="sm"
                                            data-toggle="modal"
                                            data-target="#positionEditModal"
                                            data-edit='@json($positionEditPayload)'
                                            data-update-url="{{ route('positions.update', $pos) }}"
                                            data-name="{{ $pos->position }}"
                                            data-employee-limit="{{ $hasEmployeeLimitScope ? ($pos->employee_limit ?? '') : '' }}"
                                            data-department-ids='@json($positionDepartmentIdList->all())'
                                            data-department-ids-csv="{{ $positionDepartmentIdList->implode(',') }}"
                                            aria-label="Edit Position"
                                            title="Edit Position"
                                        />
                                    @endcan
                                    @if (Auth::user()->isAdmin())
                                        <form
                                            action="{{ route('positions.destroy', $pos) }}"
                                            method="POST"
                                            class="d-inline"
                                            data-confirm-message="Delete {{ ucfirst($pos->position) }}?"
                                            data-confirm-title="Delete Position"
                                            data-confirm-label="Delete"
                                            data-confirm-variant="danger"
                                        >
                                            @csrf
                                            @method ('DELETE')
                                            <x-ui.button
                                                type="submit"
                                                variant="delete"
                                                size="sm"
                                                aria-label="Delete Position"
                                                title="Delete Position"
                                            />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center py-4 text-muted">
                                <i class="cil-info mr-1"></i> No positions
                                found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:footer>
                {{ $positions->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
    @if (Auth::user()->role == 'admin')
        <x-ui.modal
            id="positionCreateModal"
            size="lg"
        >
                    <x-ui.modal-header
                        icon="cil-briefcase"
                        title="Create Position"
                        subtitle="Add a new role that can be assigned to employees."
                    />
                    <form action="{{ route('positions.store') }}" method="POST">
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="position_create"
                        />
                        <div class="modal-body">
                            <x-ui.form-section title="Position Definition">
                                <div class="form-group">
                                    @if ($hasDepartmentScope ?? false)
                                        <label for="position_create_department"
                                            >Department
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            id="position_create_department"
                                            name="department_ids[]"
                                            class="form-control select2bs4 {{ FormValidation::invalidClass(['department_ids', 'department_ids.*'], 'position_create') }}"
                                            data-placeholder="Select departments"
                                            multiple
                                            required
                                        >
                                            @php
                                                $selectedCreateDepartmentIds = collect(old('department_ids', old('department_id') ? [old('department_id')] : []))
                                                    ->map(fn ($id) => (string) $id)
                                                    ->all();
                                            @endphp
                                            @foreach ($departments as $department)
                                                <option
                                                    value="{{ $department->id }}"
                                                    {{ in_array((string) $department->id, $selectedCreateDepartmentIds, true) ? 'selected' : '' }}
                                                >
                                                    {{ $department->department }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-ui.form-error :field="['department_ids', 'department_ids.*']" context="position_create" class="invalid-feedback d-block font-weight-bold" />
                                    @else
                                        <div class="alert alert-light border mb-0">
                                            Department assignment is unavailable on the current positions schema.
                                        </div>
                                    @endif
                                </div>
                                <div class="form-group mb-0">
                                    <label for="position_create_name"
                                        >Position Name
                                        <span class="text-danger"
                                            >*</span
                                        ></label
                                    >
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"
                                                ><i class="cil-briefcase"></i
                                            ></span>
                                        </div>
                                        <input
                                            type="text"
                                            id="position_create_name"
                                            name="position"
                                            class="form-control {{ FormValidation::invalidClass('position', 'position_create') }}"
                                            value="{{ old('position') }}"
                                            required
                                        />
                                    </div>
                                    <x-ui.form-error field="position" context="position_create" class="invalid-feedback d-block font-weight-bold" />
                                </div>
                                @if ($hasEmployeeLimitScope ?? false)
                                    <div class="form-group mt-3 mb-0">
                                        <label for="position_create_limit"
                                            >Employee Limit</label
                                        >
                                        <input
                                            type="number"
                                            id="position_create_limit"
                                            name="employee_limit"
                                            class="form-control {{ FormValidation::invalidClass('employee_limit', 'position_create') }}"
                                            value="{{ old('employee_limit') }}"
                                            min="1"
                                            max="500"
                                            placeholder="Leave blank for default or no limit"
                                        />
                                        <x-ui.form-error field="employee_limit" context="position_create" class="invalid-feedback d-block font-weight-bold" />
                                        <small class="text-muted d-block mt-1">
                                            Example: set <strong>Intern</strong> to <strong>10</strong> to limit that position to 10 employees per selected department.
                                        </small>
                                    </div>
                                @endif
                            </x-ui.form-section>
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
            id="positionEditModal"
            size="lg"
        >
                    <x-ui.modal-header
                        icon="cil-pencil"
                        title="Edit Position"
                        subtitle="Update the title used across departments."
                    />
                    <form id="positionEditForm" action="#" method="POST">
                        @csrf
                        @method ('PUT')
                        <input
                            type="hidden"
                            name="form_context"
                            value="position_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="position_edit_update_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <x-ui.form-section title="Position Definition">
                                <div class="form-group">
                                    @if ($hasDepartmentScope ?? false)
                                        <label for="position_edit_department"
                                            >Department
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            id="position_edit_department"
                                            name="department_ids[]"
                                            class="form-control select2bs4 {{ FormValidation::invalidClass(['department_ids', 'department_ids.*'], 'position_edit') }}"
                                            data-placeholder="Select departments"
                                            multiple
                                            required
                                        >
                                            @php
                                                $selectedEditDepartmentIds = collect(old('department_ids', old('department_id') ? [old('department_id')] : []))
                                                    ->map(fn ($id) => (string) $id)
                                                    ->all();
                                            @endphp
                                            @foreach ($departments as $department)
                                                <option
                                                    value="{{ $department->id }}"
                                                    {{ in_array((string) $department->id, $selectedEditDepartmentIds, true) ? 'selected' : '' }}
                                                >
                                                    {{ $department->department }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-ui.form-error :field="['department_ids', 'department_ids.*']" context="position_edit" class="invalid-feedback d-block font-weight-bold" />
                                    @else
                                        <div class="alert alert-light border mb-0">
                                            Department assignment is unavailable on the current positions schema.
                                        </div>
                                    @endif
                                </div>
                                <div class="form-group mb-0">
                                    <label for="position_edit_name"
                                        >Position Name
                                        <span class="text-danger"
                                            >*</span
                                        ></label
                                    >
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"
                                                ><i
                                                    class="cil-briefcase text-warning"
                                                ></i
                                            ></span>
                                        </div>
                                        <input
                                            type="text"
                                            id="position_edit_name"
                                            name="position"
                                            class="form-control {{ FormValidation::invalidClass('position', 'position_edit') }}"
                                            value="{{ old('position') }}"
                                            required
                                        />
                                    </div>
                                    <x-ui.form-error field="position" context="position_edit" class="invalid-feedback d-block font-weight-bold" />
                                </div>
                                @if ($hasEmployeeLimitScope ?? false)
                                    <div class="form-group mt-3 mb-0">
                                        <label for="position_edit_limit"
                                            >Employee Limit</label
                                        >
                                        <input
                                            type="number"
                                            id="position_edit_limit"
                                            name="employee_limit"
                                            class="form-control {{ FormValidation::invalidClass('employee_limit', 'position_edit') }}"
                                            value="{{ old('employee_limit') }}"
                                            min="1"
                                            max="500"
                                            placeholder="Leave blank for default or no limit"
                                        />
                                        <x-ui.form-error field="employee_limit" context="position_edit" class="invalid-feedback d-block font-weight-bold" />
                                        <small class="text-muted d-block mt-1">
                                            Applies to the selected departments for this position.
                                        </small>
                                    </div>
                                @endif
                            </x-ui.form-section>
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
    <x-modal
        id="positionMembersModal"
        size="lg"
        title="Position Members"
        subtitle="Employees currently assigned to this position."
        title-id="positionMembersModalTitle"
        class="position-members-modal"
    >
        <x-slot:body>
            <div
                id="positionMembersList"
                class="position-members-list"
            ></div>
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
