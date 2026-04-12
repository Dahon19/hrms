@extends ('layouts.admin')
@section ('content')
    @php use App\Support\FormValidation; @endphp
    <div
        class="container-fluid pt-3"
        id="leaveTypesPage"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-form-context="{{ old('form_context') }}"
    >
        <x-page-header
            eyebrow="Operations"
            title="Leave Types"
            subtitle="Maintain leave categories, rules, and submission requirements."
        />
        <x-ui.table-card
            title="Type Directory"
            subtitle="Configured leave types and restrictions."
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('leave-types.index')"
                    search-name="search"
                    :search-value="request('search')"
                    search-placeholder="Search leave type"
                    class="leave-types-toolbar"
                >
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button type="submit" variant="primary" size="md">
                            Apply
                        </x-ui.button>
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            variant="primary"
                            size="md"
                            icon="cil-plus"
                            data-toggle="modal"
                            data-target="#leaveTypeCreateModal"
                        >
                            New Type
                        </x-ui.button>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
                    <table class="table hrms-table" id="leaveTypesTable">
                        <thead class="bg-light">
                            <tr class="text-uppercase text-muted small">
                                <th>Name</th>
                                <th>Color</th>
                                <th>Attachment Required</th>
                                <th>Gender Restriction</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr>
                                    <td class="font-weight-bold text-dark">
                                        {{ $type->name }}
                                    </td>
                                    <td>
                                        <span
                                            class="badge"
                                            style="background-color: {{ $type->color_code }}; color: #fff; text-shadow: 0 1px 1px rgba(0,0,0,0.2);"
                                            >{{ $type->color_code }}</span
                                        >
                                    </td>
                                    <td>
                                        {{ $type->requires_attachment ? 'Yes' : 'No' }}
                                    </td>
                                    <td>
                                        @if ($type->gender === 'male')
                                            <span class="badge badge-primary"
                                                ><i class="cil-user mr-1"></i>
                                                Male Only</span
                                            >
                                        @elseif ($type->gender === 'female')
                                            <span
                                                class="badge badge-pink"
                                                style="
                                                    background-color: #e83e8c;
                                                    color: white;
                                                "
                                                ><i class="cil-user mr-1"></i>
                                                Female Only</span
                                            >
                                        @else
                                            <span class="badge badge-secondary"
                                                >All</span
                                            >
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="crud-actions justify-content-center">
                                            @if (auth()->user()->isAdmin())
                                                <form
                                                    action="{{ route('leave-types.destroy', $type) }}"
                                                    method="POST"
                                                    class="d-inline"
                                                    data-confirm-message="Delete {{ $type->name }}?"
                                                    data-confirm-title="Delete Leave Type"
                                                    data-confirm-label="Delete"
                                                    data-confirm-variant="danger"
                                                >
                                                    @csrf
                                                    @method ('DELETE')
                                                    <x-ui.button
                                                        type="submit"
                                                        variant="delete"
                                                        size="sm"
                                                        aria-label="Delete Leave Type"
                                                        title="Delete Leave Type"
                                                    />
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        No leave types found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
    </x-ui.table-card>
    </div>
    @if (auth()->user()->role === 'admin')
        <x-modal
            id="leaveTypeCreateModal"
            title="Create Leave Type"
            subtitle="Add a leave type and define its eligibility rules."
            size="lg"
        >
                    <form
                        action="{{ route('leave-types.store') }}"
                        method="POST"
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="form_context"
                            value="leave_type_create"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="leave_type_create_name"
                                    >Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="leave_type_create_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'leave_type_create') }}"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="leave_type_create" />
                            </div>
                            <div class="form-group">
                                <label for="leave_type_create_color"
                                    >Color Code
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="leave_type_create_color"
                                    name="color_code"
                                    class="form-control {{ FormValidation::invalidClass('color_code', 'leave_type_create') }}"
                                    value="{{ old('color_code', '#007bff') }}"
                                    required
                                />
                                <x-ui.form-error field="color_code" context="leave_type_create" />
                            </div>
                            <div class="form-group">
                                <label for="leave_type_create_gender"
                                    >Gender Restriction</label
                                >
                                <select
                                    id="leave_type_create_gender"
                                    name="gender"
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('gender', 'leave_type_create') }}"
                                    data-placeholder="All genders"
                                >
                                    <option
                                        value=""
                                        {{ old('gender') === null ? 'selected' : '' }}
                                        >All (No Restriction)
                                    </option>
                                    <option
                                        value="male"
                                        {{ old('gender') === 'male' ? 'selected' : '' }}
                                        >Male Only
                                    </option>
                                    <option
                                        value="female"
                                        {{ old('gender') === 'female' ? 'selected' : '' }}
                                        >Female Only
                                    </option>
                                </select>
                                <x-ui.form-error field="gender" context="leave_type_create" />
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input
                                        type="checkbox"
                                        id="leave_type_create_requires_attachment"
                                        name="requires_attachment"
                                        class="custom-control-input"
                                        {{ old('requires_attachment') ? 'checked' : '' }}
                                    />
                                    <label
                                        for="leave_type_create_requires_attachment"
                                        class="custom-control-label"
                                        >Requires Attachment</label
                                    >
                                </div>
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                variant="light"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </x-ui.button>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="cil-save"
                            >
                                Save
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
        <x-modal
            id="leaveTypeEditModal"
            title="Edit Leave Type"
            subtitle="Update the leave type settings and restrictions."
            size="lg"
        >
                    <form id="leaveTypeEditForm" action="#" method="POST">
                        @csrf
                        @method ('PUT')
                        <input
                            type="hidden"
                            name="form_context"
                            value="leave_type_edit"
                        />
                        <input
                            type="hidden"
                            name="update_url"
                            id="leave_type_edit_update_url"
                            value="{{ old('update_url') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="leave_type_edit_name"
                                    >Name
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="leave_type_edit_name"
                                    name="name"
                                    class="form-control {{ FormValidation::invalidClass('name', 'leave_type_edit') }}"
                                    value="{{ old('name') }}"
                                    required
                                />
                                <x-ui.form-error field="name" context="leave_type_edit" />
                            </div>
                            <div class="form-group">
                                <label for="leave_type_edit_color"
                                    >Color Code
                                    <span class="text-danger">*</span></label
                                >
                                <input
                                    type="text"
                                    id="leave_type_edit_color"
                                    name="color_code"
                                    class="form-control {{ FormValidation::invalidClass('color_code', 'leave_type_edit') }}"
                                    value="{{ old('color_code', '#007bff') }}"
                                    required
                                />
                                <x-ui.form-error field="color_code" context="leave_type_edit" />
                            </div>
                            <div class="form-group">
                                <label for="leave_type_edit_gender"
                                    >Gender Restriction</label
                                >
                                <select
                                    id="leave_type_edit_gender"
                                    name="gender"
                                    class="form-control select2bs4 {{ FormValidation::invalidClass('gender', 'leave_type_edit') }}"
                                    data-placeholder="All genders"
                                >
                                    <option value="">
                                        All (No Restriction)
                                    </option>
                                    <option value="male">Male Only</option>
                                    <option value="female">Female Only</option>
                                </select>
                                <x-ui.form-error field="gender" context="leave_type_edit" />
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input
                                        type="checkbox"
                                        id="leave_type_edit_requires_attachment"
                                        name="requires_attachment"
                                        class="custom-control-input"
                                        {{ old('requires_attachment') ? 'checked' : '' }}
                                    />
                                    <label
                                        for="leave_type_edit_requires_attachment"
                                        class="custom-control-label"
                                        >Requires Attachment</label
                                    >
                                </div>
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                variant="light"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </x-ui.button>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="cil-save"
                            >
                                Save
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
    @endif
@endsection
