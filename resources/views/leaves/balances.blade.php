@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid pt-4" id="leaveBalancesPage">
        <x-page-header
            eyebrow="Operations"
            title="Leave Balances"
            subtitle="Track earned, consumed, and remaining leaves across employees."
        >
            <x-slot:actions>
                @if (!empty($year))
                    <span class="badge badge-pill badge-soft-primary px-3 py-2">
                        <i class="cil-calendar mr-1"></i>{{ $year }}
                    </span>
                @endif
                @can ('manage-leave-balances')
                    <x-ui.button
                        type="button"
                        variant="outline-primary"
                        size="sm"
                        icon="cil-settings"
                        data-coreui-toggle="modal"
                        data-coreui-target="#leaveBalanceConfigModal"
                    >
                        Configure
                    </x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-ui.table-card
            title="Balance Overview"
            subtitle="Current leave balances by employee."
            class="leave-balances-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('leave-balances.index')"
                    class="leave-balances-toolbar"
                >
                    <div
                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                    >
                        <label class="form-label" for="leaveBalancesSearchInput"
                            >Search</label
                        >
                        <input
                            id="leaveBalancesSearchInput"
                            name="search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search"
                            value="{{ $search ?? '' }}"
                        />
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                        <label class="form-label" for="leaveBalancesDepartmentInput"
                            >Department</label
                        >
                        <select
                            id="leaveBalancesDepartmentInput"
                            name="department_id"
                            class="form-control form-control-sm select2bs4"
                            data-placeholder="All departments"
                            data-allow-clear="1"
                        >
                            <option value="">All departments</option>
                            @foreach (($departmentOptions ?? collect()) as $departmentOption)
                                <option
                                    value="{{ $departmentOption->id }}"
                                    {{ (int) ($selectedDepartmentId ?? 0) === (int) $departmentOption->id ? 'selected' : '' }}
                                >
                                    {{ $departmentOption->department }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field">
                        <label class="form-label" for="leaveBalancesYearInput"
                            >Year</label
                        >
                        <input
                            id="leaveBalancesYearInput"
                            name="year"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Year"
                            value="{{ $year ?? '' }}"
                        />
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            id="leaveBalancesApply"
                        >
                            Apply</x-ui.button
                        >
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <table
                class="table table-hover align-middle mb-0 leave-balances-table hrms-table"
                id="leaveBalancesTable"
                data-no-datatable="1"
            >
                <thead class="bg-light">
                    <tr class="text-uppercase text-muted small">
                        <th class="pl-4">Employee</th>
                        <th class="text-center">Year</th>
                        <th class="text-center">Earned</th>
                        <th class="text-center">Consumed</th>
                        <th class="text-center">Remaining</th>
                    </tr>
                </thead>
                <tbody id="leaveBalancesTableBody">
                    @forelse ($rows as $row)
                        @php
                        $employeeName = trim(($row['employee']->first_name ?? '') . ' ' . ($row['employee']->last_name ?? ''));
                    @endphp
                        <tr
                            data-search="{{ strtolower(trim($employeeName . ' ' . $row['year'])) }}"
                            data-year="{{ $row['year'] }}"
                            data-employee="{{ $employeeName }}"
                            data-earned="{{ $row['earned'] }}"
                            data-consumed="{{ number_format((float) $row['consumed'], 0) }}"
                            data-remaining="{{ $row['remaining'] }}"
                        >
                            <td class="font-weight-bold text-dark pl-4">
                                {{ $employeeName }}
                            </td>
                            <td class="text-center">{{ $row['year'] }}</td>
                            <td class="text-center">
                                <span
                                    class="balance-pill balance-earned"
                                    >{{ $row['earned'] }}</span
                                >
                            </td>
                            <td class="text-center">
                                <span
                                    class="balance-pill balance-consumed"
                                    >{{ number_format((float) $row['consumed'], 0) }}</span
                                >
                            </td>
                            <td class="text-center">
                                <span
                                    class="balance-pill balance-remaining"
                                    >{{ $row['remaining'] }}</span
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No leave balances found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:footer>
                {{ $rows->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
@endsection

@can ('manage-leave-balances')
    <x-ui.modal id="leaveBalanceConfigModal" size="md">
        <x-ui.modal-header title="Leave Balance Configuration" />
        <form action="{{ route('leave-balances.settings.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label for="leaveBalanceConfigYear">Year</label>
                    <input
                        id="leaveBalanceConfigYear"
                        type="number"
                        name="year"
                        class="form-control"
                        min="2000"
                        max="2100"
                        value="{{ $year ?? now()->year }}"
                        required
                    />
                </div>
                <div class="form-group mb-0">
                    <label for="leaveBalanceConfigStartingBalance">Starting Leave Balance</label>
                    <input
                        id="leaveBalanceConfigStartingBalance"
                        type="number"
                        name="starting_balance"
                        class="form-control"
                        min="0"
                        max="999.99"
                        step="0.01"
                        value="{{ old('starting_balance', $configuredStartingBalance ?? \App\Models\LeaveBalance::calculateEarnedForYear((int) ($year ?? now()->year))) }}"
                        required
                    />
                    <small class="form-text text-muted">
                        This fixed amount overrides the default computed leave balance for the selected year.
                    </small>
                </div>
            </div>
            <x-ui.modal-footer>
                <x-ui.button type="button" variant="cancel" data-coreui-dismiss="modal">
                    Cancel
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="cil-save">
                    Save
                </x-ui.button>
            </x-ui.modal-footer>
        </form>
    </x-ui.modal>
@endcan
