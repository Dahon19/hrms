@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid pt-4" data-page="travel-orders.transport-options">
        @php
            $openCreateModal = $errors->any() && old('_method') !== 'PATCH';
        @endphp

        <x-ui.hero
            title="Travel Transport Options"
            subtitle="Create and maintain the transportation list used in travel order filing."
        >
            <x-slot:actions>
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    icon="cil-arrow-left"
                    :href="route('travel-orders.index')"
                >
                    Back to Travel Orders
                </x-ui.button>
            </x-slot:actions>
        </x-ui.hero>

        <x-ui.table-card>
            <x-slot:header>
                <div class="d-flex align-items-center gap-2 w-100">
                    <div class="ui-table-card__heading-copy">
                        <h5 class="mb-1">Configured Transport Options</h5>
                        <small>Active options are available in travel order forms.</small>
                    </div>
                    <x-ui.button
                        class="ml-auto"
                        variant="primary"
                        size="sm"
                        icon="cil-plus"
                        data-coreui-toggle="modal"
                        data-coreui-target="#travelTransportOptionModal"
                    >
                        Add Option
                    </x-ui.button>
                </div>
            </x-slot:header>
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('travel-orders.transport-options.index')"
                    class="travel-order-transport-toolbar"
                >
                    <div class="travel-order-filter-grid">
                        <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search">
                            <label class="form-label" for="travelTransportSearch">Search</label>
                            <input
                                id="travelTransportSearch"
                                type="text"
                                name="search"
                                value="{{ $search ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Search transportation"
                            />
                        </div>
                        <div class="travel-order-filter-action ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action">
                            <x-ui.button type="submit" variant="primary" size="sm">
                                Apply
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <table class="table table-hover align-middle mb-0 hrms-table">
                <thead>
                    <tr>
                        <th>Transportation</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-center" style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transportations as $transportation)
                        @php
                            $updateFormId = 'transport_update_' . $transportation->id;
                        @endphp
                        <tr>
                            <td>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ $transportation->name }}"
                                    class="form-control form-control-sm"
                                    form="{{ $updateFormId }}"
                                    required
                                />
                            </td>
                            <td>
                                <input type="hidden" name="is_active" value="0" form="{{ $updateFormId }}" />
                                <div class="form-check m-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        id="transport_option_active_{{ $transportation->id }}"
                                        form="{{ $updateFormId }}"
                                        @checked($transportation->is_active)
                                    />
                                    <label class="form-check-label small" for="transport_option_active_{{ $transportation->id }}">
                                        Active
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="crud-actions justify-content-center">
                                    <form
                                        method="POST"
                                        id="{{ $updateFormId }}"
                                        action="{{ route('travel-orders.transport-options.update', $transportation) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        @method('PATCH')
                                    </form>
                                    <x-ui.button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        icon="cil-save"
                                        form="{{ $updateFormId }}"
                                    >
                                        Save
                                    </x-ui.button>
                                    <form
                                        method="POST"
                                        action="{{ route('travel-orders.transport-options.destroy', $transportation) }}"
                                        class="d-inline"
                                        data-confirm-message="Delete {{ $transportation->name }}?"
                                        data-confirm-title="Delete Transport Option"
                                        data-confirm-label="Delete"
                                        data-confirm-variant="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            type="submit"
                                            variant="delete"
                                            size="sm"
                                            aria-label="Delete Transport Option"
                                            title="Delete Transport Option"
                                        />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                No transport options found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>

        <x-ui.modal id="travelTransportOptionModal" size="md">
            <x-ui.modal-header
                title="Add Transport Option"
                subtitle="Create a transportation option for travel order filing."
            />
            <form method="POST" action="{{ route('travel-orders.transport-options.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="transport_option_name">Transportation Name</label>
                        <input
                            id="transport_option_name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror"
                            required
                        />
                        @error ('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-1">
                        <input type="hidden" name="is_active" value="0" />
                        <div class="form-check mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="1"
                                id="transport_option_active"
                                name="is_active"
                                @checked(old('is_active', true))
                            />
                            <label class="form-check-label" for="transport_option_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="cil-save">
                        Save Option
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-ui.modal>
    </div>
@endsection

@if ($openCreateModal)
    @push ('scripts')
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                if (window.jQuery) {
                    window.jQuery("#travelTransportOptionModal").modal("show");
                }
            });
        </script>
    @endpush
@endif
