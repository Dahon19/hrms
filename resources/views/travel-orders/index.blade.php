@extends ('layouts.admin')

@section ('content')
    @php
    $statuses = [
        \App\Models\TravelOrder::STATUS_DRAFT => 'Draft',
        \App\Models\TravelOrder::STATUS_SUBMITTED => 'Submitted',
        \App\Models\TravelOrder::STATUS_DEPARTMENT_APPROVED => 'Department Approved',
        \App\Models\TravelOrder::STATUS_HR_REVIEW => 'For Final Approval',
        \App\Models\TravelOrder::STATUS_APPROVED => 'Approved',
        \App\Models\TravelOrder::STATUS_COMPLETED => 'Completed',
        \App\Models\TravelOrder::STATUS_CANCELLED => 'Cancelled',
        \App\Models\TravelOrder::STATUS_REJECTED => 'Rejected',
    ];
    $simpleStatusLabels = [
        \App\Models\TravelOrder::STATUS_DRAFT => 'Draft',
        \App\Models\TravelOrder::STATUS_SUBMITTED => 'Submitted',
        \App\Models\TravelOrder::STATUS_DEPARTMENT_APPROVED => 'Dept Approved',
        \App\Models\TravelOrder::STATUS_HR_REVIEW => 'Final Review',
        \App\Models\TravelOrder::STATUS_APPROVED => 'Approved',
        \App\Models\TravelOrder::STATUS_COMPLETED => 'Completed',
        \App\Models\TravelOrder::STATUS_CANCELLED => 'Cancelled',
        \App\Models\TravelOrder::STATUS_REJECTED => 'Rejected',
    ];
    $transportOptions = collect($transportOptions ?? [])->values();
    $pendingCount = (int) (($pending ?? collect())->count());
@endphp
    <div class="container-fluid" data-page="travel-orders.index">
    <x-ui.hero
        title="Travel Orders"
        subtitle="Authorize official travel, route approvals, and keep attendance tagged as official business without affecting payroll."
    >
        <x-slot:actions>
            <div class="travel-order-hero-actions">
                <span class="badge badge-soft-primary px-3 py-2">{{ $travelOrders->total() }} records</span>
                @if (auth()->user()->isAdmin())
                    <x-ui.button
                        variant="outline-primary"
                        size="sm"
                        icon="cil-list"
                        :href="route('travel-orders.transport-options.index')"
                    >
                        Manage Transport
                    </x-ui.button>
                @endif
                @if ($canCreate)
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        icon="cil-plus"
                        data-toggle="modal"
                        data-target="#travelOrderCreateModal"
                        :disabled="!$travelOrdersAvailable"
                    >
                        New Travel Order
                    </x-ui.button>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.hero>

    <x-ui.table-card>
        <x-slot:header>
            <div class="travel-order-records-header">
                <div class="ui-table-card__heading-copy">
                    <h5 class="mb-1">Travel Orders</h5>
                    <small>
                        @if (($openApprovals ?? false) && ($canReview ?? false))
                            Showing requests pending your approval.
                        @else
                            Operational travel requests and approval status.
                        @endif
                    </small>
                </div>
                <span class="badge badge-info text-white px-3 py-2">{{ $pendingCount }} pending approvals</span>
            </div>
        </x-slot:header>
        <x-slot:controls>
            @php $showTravelOrderAdvancedFilters = filled($status); @endphp
            <x-ui.table-toolbar
                method="GET"
                :action="route('travel-orders.index')"
                class="travel-order-filters"
            >
                @if ($openApprovals ?? false)
                    <input type="hidden" name="open_approvals" value="1" />
                @endif
                <div class="travel-order-filter-shell">
                    <div class="travel-order-filter-primary">
                        <div
                            class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search travel-order-filter-search"
                            data-toolbar-label="Search"
                        >
                            <label class="form-label" for="travelOrdersSearch">Search</label>
                            <input
                                id="travelOrdersSearch"
                                type="text"
                                name="search"
                                value="{{ $search }}"
                                class="form-control form-control-sm"
                                placeholder="Search"
                            />
                        </div>
                        <div class="travel-order-filter-toggle-wrap">
                            <label class="form-label travel-order-filter-toggle-label" for="travelOrdersFiltersToggle">Filters</label>
                            <x-ui.button
                                type="button"
                                :variant="$showTravelOrderAdvancedFilters ? 'primary' : 'outline-secondary'"
                                size="sm"
                                icon="cil-filter"
                                id="travelOrdersFiltersToggle"
                                class="travel-order-filter-toggle"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#travelOrdersFiltersCollapse"
                                aria-expanded="{{ $showTravelOrderAdvancedFilters ? 'true' : 'false' }}"
                                aria-controls="travelOrdersFiltersCollapse"
                            >
                                Filters
                            </x-ui.button>
                        </div>
                    </div>

                    <div id="travelOrdersFiltersCollapse" class="travel-order-filter-panel collapse {{ $showTravelOrderAdvancedFilters ? 'show' : '' }}">
                        <div class="offcanvas-body travel-order-filter-offcanvas-body">
                            <div class="travel-order-filter-grid">
                                <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter" data-toolbar-label="Status">
                                    <label class="form-label" for="travelOrdersStatus">Status</label>
                                    <select
                                        id="travelOrdersStatus"
                                        name="status"
                                        class="form-control form-control-sm select2bs4"
                                        data-toolbar-select2="1"
                                        data-placeholder="All statuses"
                                        data-allow-clear="1"
                                    >
                                        <option value=""></option>
                                        @foreach ($statuses as $value => $label)
                                            <option
                                                value="{{ $value }}"
                                                @selected ($status === $value)
                                                >{{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="travel-order-filter-action ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                                >
                                    <x-ui.button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
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

        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 hrms-table travel-orders-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Destination</th>
                    <th>Travel Dates</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($travelOrders as $travelOrder)
                    @php
                        $employee = $travelOrder->employee;
                    @endphp
                    <tr>
                        <td data-label="Employee">
                            <div class="fw-bold">
                                {{ trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')) ?: 'Employee' }}
                            </div>
                            <div class="text-muted small">
                                {{ $employee?->department?->department ?? 'No department' }}
                            </div>
                            <div class="text-muted small d-none">
                                #{{ $employee?->employee_id ?? 'N/A' }} � {{ $employee?->department?->department ?? 'No department' }}
                            </div>
                        </td>
                        <td data-label="Destination">
                            <div>
                                {{ $travelOrder->destination }}
                            </div>
                        </td>
                        <td data-label="Travel Dates">
                            <div>
                                {{ $travelOrder->date_from?->format('M d, Y') }} to {{ $travelOrder->date_to?->format('M d, Y') }}
                            </div>
                        </td>
                        <td data-label="Status">
                            <span
                                class="badge {{ $travelOrder->statusBadgeClass() }} px-3 py-2"
                                >{{ $simpleStatusLabels[$travelOrder->status] ?? $travelOrder->statusLabel() }}</span
                            >
                        </td>
                        <td data-label="Actions" class="text-center">
                            <div class="crud-actions justify-content-center">
                                <x-ui.button
                                    type="view"
                                    size="sm"
                                    :href="route('travel-orders.show', $travelOrder)"
                                    aria-label="View Travel Order"
                                    title="View Travel Order"
                                />
                                <x-ui.button
                                    type="view"
                                    size="sm"
                                    icon="cil-print"
                                    :href="route('travel-orders.print', $travelOrder)"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Print Travel Order"
                                    title="Print Travel Order"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="hrms-empty-state">
                                <div class="hrms-empty-state__icon">
                                    <i class="cil-map"></i>
                                </div>
                                <div class="hrms-empty-state__title">No Travel Orders Found</div>
                                <div class="hrms-empty-state__text">No travel orders match the current filters.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <x-slot:footer>
            {{ $travelOrders->links() }}
        </x-slot:footer>
    </x-ui.table-card>
    @if ($canCreate)
        <x-ui.modal
            id="travelOrderCreateModal"
            size="xl"
            content-class="travel-order-modal"
        >
                    <x-ui.modal-header
                        title="File Travel Order"
                        subtitle="Create an operational request for official duties outside the workplace. This does not affect leave balances or payroll."
                    />
                    <div class="modal-body">
                        <form
                            method="POST"
                            action="{{ route('travel-orders.store') }}"
                            enctype="multipart/form-data"
                            id="travelOrderCreateForm"
                            class="row g-3 hrms-form-layout"
                        >
                            @csrf
                            <div class="col-12">
                                <div class="travel-order-form-section">
                                    <div
                                        class="travel-order-form-section__header"
                                    >
                                        <div
                                            class="travel-order-form-section__eyebrow"
                                        >
                                            Step 1
                                        </div>
                                        <div
                                            class="travel-order-form-section__title"
                                        >
                                            Travel details
                                        </div>
                                        <div
                                            class="travel-order-form-section__text"
                                        >
                                            Set the destination, purpose, and
                                            basic transport information for the
                                            official trip.
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Destination</label
                                            >
                                            <input
                                                type="text"
                                                name="destination"
                                                value="{{ old('destination', $prefill['destination'] ?? '') }}"
                                                class="form-control @error('destination') is-invalid @enderror"
                                                required
                                            />
                                            @error ('destination')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Transport Mode</label
                                            >
                                            @if ($transportOptions->isNotEmpty())
                                                <select
                                                    name="transport_mode"
                                                    class="form-control @error('transport_mode') is-invalid @enderror"
                                                    required
                                                >
                                                    <option value="" disabled @selected(!old('transport_mode', $prefill['transport_mode'] ?? ''))>
                                                        Select transportation
                                                    </option>
                                                    @foreach ($transportOptions as $transportOption)
                                                        <option value="{{ $transportOption }}" @selected(old('transport_mode', $prefill['transport_mode'] ?? '') === $transportOption)>
                                                            {{ $transportOption }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input
                                                    type="text"
                                                    name="transport_mode"
                                                    value="{{ old('transport_mode', $prefill['transport_mode'] ?? '') }}"
                                                    class="form-control @error('transport_mode') is-invalid @enderror"
                                                    placeholder="Enter transportation"
                                                    required
                                                />
                                            @endif
                                            @error ('transport_mode')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Budget Proposal</label
                                            >
                                            <input
                                                type="number"
                                                name="budget_proposal"
                                                value="{{ old('budget_proposal', $prefill['budget_proposal'] ?? '') }}"
                                                class="form-control @error('budget_proposal') is-invalid @enderror"
                                                min="0"
                                                step="0.01"
                                                placeholder="0.00"
                                            />
                                            @error ('budget_proposal')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label"
                                                >Purpose</label
                                            >
                                            <textarea
                                                name="purpose"
                                                rows="4"
                                                class="form-control @error('purpose') is-invalid @enderror"
                                                required
                                                >{{ old('purpose', $prefill['purpose'] ?? '') }}</textarea
                                            >
                                            @error ('purpose')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="travel-order-form-section">
                                    <div
                                        class="travel-order-form-section__header"
                                    >
                                        <div
                                            class="travel-order-form-section__eyebrow"
                                        >
                                            Step 2
                                        </div>
                                        <div
                                            class="travel-order-form-section__title"
                                        >
                                            Schedule and files
                                        </div>
                                        <div
                                            class="travel-order-form-section__text"
                                        >
                                            Define the official travel window
                                            and attach supporting files if they
                                            are already available.
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Date From</label
                                            >
                                            <input
                                                type="date"
                                                name="date_from"
                                                value="{{ old('date_from', $prefill['date_from'] ?? '') }}"
                                                class="form-control @error('date_from') is-invalid @enderror"
                                                required
                                            />
                                            @error ('date_from')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Date To</label
                                            >
                                            <input
                                                type="date"
                                                name="date_to"
                                                value="{{ old('date_to', $prefill['date_to'] ?? '') }}"
                                                class="form-control @error('date_to') is-invalid @enderror"
                                                required
                                            />
                                            @error ('date_to')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Departure Time</label
                                            >
                                            <input
                                                type="time"
                                                name="departure_time"
                                                value="{{ old('departure_time', $prefill['departure_time'] ?? '') }}"
                                                class="form-control @error('departure_time') is-invalid @enderror"
                                            />
                                            @error ('departure_time')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Return Time</label
                                            >
                                            <input
                                                type="time"
                                                name="return_time"
                                                value="{{ old('return_time', $prefill['return_time'] ?? '') }}"
                                                class="form-control @error('return_time') is-invalid @enderror"
                                            />
                                            @error ('return_time')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label"
                                                >Attachments</label
                                            >
                                            <input
                                                type="file"
                                                name="attachments[]"
                                                class="filepond @error('attachments.*') is-invalid @enderror travel-order-file-input"
                                                multiple
                                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                data-accepted-file-types=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                data-max-file-size="5MB"
                                                data-filepond-label-idle='Drop travel attachments here or <span class="filepond--label-action">Browse</span>'
                                            />
                                            @error ('attachments.*')
                                                <div
                                                    class="invalid-feedback d-block"
                                                >
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <x-ui.modal-footer>
                                    <x-ui.button
                                        variant="light"
                                        data-coreui-dismiss="modal"
                                    >
                                        Cancel
                                    </x-ui.button>
                                    <x-ui.button type="submit" variant="primary">
                                    Save Draft
                                    </x-ui.button>
                                </x-ui.modal-footer>
                            </div>
                        </form>
                    </div>
        </x-ui.modal>
    @endif
    @if (($canCreate && $openCreateModal) || ($canCreate && $errors->any()))
        @push ('scripts')
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    if (window.jQuery) {
                        window.jQuery("#travelOrderCreateModal").modal("show");
                    }
                });
            </script>
        @endpush
    @endif
    </div>
@endsection
