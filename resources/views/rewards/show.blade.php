@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid pt-4" id="rewardsShowPage">
        <x-page-header
            eyebrow="Recognition & Rewards"
            title="Recognition Record"
            subtitle="{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }} · {{ $employee->employee_id }}"
        >
            <x-slot:actions>
                @can ('manage-rewards')
                    <x-ui.button
                        variant="outline-light"
                        size="sm"
                        class="px-3"
                        icon="cil-arrow-left"
                        :href="route('rewards.index')"
                    >
                        Back
                    </x-ui.button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card shadow-sm border-0 rewards-metric-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted mb-2">
                            Tenure Eligibility
                        </h6>
                        <div class="h5 font-weight-bold mb-1">
                            {{ number_format((float) ($eligibility['tenure']['years'] ?? 0), 2) }} years
                        </div>
                        @if ($eligibility['tenure']['eligible'] ?? false)
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="tenure"
                                :text="$eligibility['tenure']['title']"
                                variant="primary"
                            />
                        @else
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="inactive"
                                text="Not yet at milestone"
                                variant="secondary"
                            />
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="card shadow-sm border-0 rewards-metric-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted mb-2">
                            Attendance Eligibility
                        </h6>
                        <div class="h5 font-weight-bold mb-1">
                            {{ (int) ($eligibility['attendance']['absent_days'] ?? 0) }} absent
                            days
                        </div>
                        @if ($eligibility['attendance']['eligible'] ?? false)
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="attendance"
                                :text="$eligibility['attendance']['title']"
                                variant="success"
                            />
                        @else
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="pending"
                                text="Threshold not met"
                                variant="warning"
                            />
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="card shadow-sm border-0 rewards-metric-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted mb-2">
                            SPMS Performance
                        </h6>
                        <div class="h5 font-weight-bold mb-1">
                            {{ number_format((float) ($eligibility['performance']['score'] ?? 0), 2) }}
                            <small class="text-muted"
                                >({{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'n/a')) }})</small
                            >
                        </div>
                        @if ($eligibility['performance']['eligible'] ?? false)
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="performance"
                                :text="$eligibility['performance']['title']"
                                variant="info"
                            />
                        @else
                            <x-ui.status-badge
                                class="px-3 py-2"
                                status="inactive"
                                text="Below threshold"
                                variant="secondary"
                            />
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <x-ui.table-card
            title="Recognition History"
            subtitle="All recognitions assigned to this employee."
            class="hrms-list-card"
        >
            <table
                class="table table-hover align-middle mb-0 hrms-table hrms-list-table"
            >
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="pl-4 py-3">Award</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                        $badgeVariant = match ($record->award_type) {
                            'tenure' => 'primary',
                            'attendance' => 'success',
                            'performance' => 'info',
                            'special' => 'warning',
                            default => 'secondary',
                        };
                    @endphp
                        <tr>
                            <td class="pl-4 font-weight-bold text-dark">
                                {{ $record->award_title }}
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge
                                    class="px-3 py-2 text-uppercase"
                                    :status="$record->award_type"
                                    :text="$record->award_type"
                                    :variant="$badgeVariant"
                                />
                            </td>
                            <td class="text-center">
                                {{ optional($record->award_date)->format('M d, Y') }}
                            </td>
                            <td class="text-muted">
                                {{ $record->remarks ?: 'No remarks.' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="rewards-empty-state">
                                    <i class="cil-star"></i>
                                    <h6>No recognitions assigned yet</h6>
                                    <p class="mb-0">Recognition entries will appear once awards are recorded.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <x-slot:footer>
                {{ $records->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
@endsection
