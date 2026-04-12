@extends ('layouts.admin')
@section ('content')
    <div class="container-fluid pt-4" id="eligibilityShowPage">
        <x-page-header
            eyebrow="Recognition & Rewards"
            title="Eligibility Summary"
            subtitle="{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }} &middot; {{ $employee->employee_id }}"
        >
            <x-slot:actions>
                @can ('view-eligibility-list')
                    <a
                        href="{{ route('eligibility.index') }}"
                        class="btn btn-outline-light btn-sm px-3"
                    >
                        <i class="cil-arrow-left mr-1"></i> Back
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-header>
        @php
            $qualifiedRewards = collect([
                'Service Award' => (bool) ($eligibility['tenure']['eligible'] ?? false),
                'Attendance Incentive' => (bool) ($eligibility['attendance']['eligible'] ?? false),
                'Performance Award' => (bool) ($eligibility['performance']['eligible'] ?? false),
            ])->filter()->keys()->values();
        @endphp
        <div class="card shadow-sm border-0 eligibility-overview-card mb-3">
            <div class="card-body">
                <div class="eligibility-overview-grid">
                    <div>
                        <div class="eligibility-section-label">Qualified for</div>
                        @if ($qualifiedRewards->isNotEmpty())
                            <div class="eligibility-pill-group">
                                @foreach ($qualifiedRewards as $rewardLabel)
                                    <span class="badge badge-success px-3 py-2">{{ $rewardLabel }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="eligibility-overview-note">No reward category is currently qualified.</div>
                        @endif
                    </div>
                    <div>
                        <div class="eligibility-section-label">Current basis</div>
                        <div class="eligibility-overview-note">
                            Service length, current attendance result, and latest finalized SPMS result are used to determine eligibility.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 eligibility-metric-card">
                    <div class="card-body">
                        <div class="eligibility-section-label">Service Award</div>
                        <div class="h5 font-weight-bold mb-1">
                            {{ number_format((float) ($eligibility['tenure']['years'] ?? 0), 2) }} years served
                        </div>
                        @if ($eligibility['tenure']['eligible'])
                            <span class="badge badge-primary px-3 py-2">Qualified</span>
                            <div class="eligibility-card-note mt-2">
                                Counts toward the {{ (int) ($eligibility['tenure']['milestone'] ?? 0) }}-year service milestone.
                            </div>
                        @else
                            <span class="badge badge-secondary px-3 py-2">Not Qualified</span>
                            <div class="eligibility-card-note mt-2">
                                {{ $eligibility['tenure']['reason'] ?? 'Service milestone not reached yet.' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 eligibility-metric-card">
                    <div class="card-body">
                        <div class="eligibility-section-label">Attendance Incentive</div>
                        <div class="h5 font-weight-bold mb-1">
                            {{ number_format((float) ($eligibility['attendance']['final_score'] ?? 0), 2) }}%
                            <small class="text-muted">(Rating {{ (int) ($eligibility['attendance']['rating'] ?? 0) }})</small>
                        </div>
                        <small class="text-muted d-block mb-2">
                            Presence {{ number_format((float) ($eligibility['attendance']['attendance_rate'] ?? 0), 2) }}%
                            | On-Time {{ number_format((float) ($eligibility['attendance']['punctuality_rate'] ?? 0), 2) }}%
                        </small>
                        @if ($eligibility['attendance']['eligible'])
                            <span class="badge badge-success px-3 py-2">Qualified</span>
                        @else
                            <span class="badge badge-warning px-3 py-2">Not Qualified</span>
                        @endif
                        <div class="eligibility-card-note mt-2">{{ $eligibility['attendance']['reason'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 eligibility-metric-card">
                    <div class="card-body">
                        <div class="eligibility-section-label">Performance Award</div>
                        <div class="h5 font-weight-bold mb-1">
                            {{ number_format((float) ($eligibility['performance']['score'] ?? 0), 2) }}
                        </div>
                        <small class="text-muted d-block mb-2">{{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'N/A')) }}</small>
                        @if ($eligibility['performance']['eligible'])
                            <span class="badge badge-info px-3 py-2">Qualified</span>
                        @else
                            <span class="badge badge-secondary px-3 py-2">Not Qualified</span>
                        @endif
                        <div class="eligibility-card-note mt-2">{{ $eligibility['performance']['reason'] ?? '' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-7 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header border-0 bg-transparent py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="cil-list-rich mr-2 text-primary"></i>Basis Used
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="eligibility-basis-stack">
                            <div class="eligibility-basis-item">
                                <div class="eligibility-basis-title">Service basis</div>
                                <div class="eligibility-basis-value">
                                    {{ number_format((float) ($eligibility['tenure']['years'] ?? 0), 2) }} years served
                                </div>
                                <div class="eligibility-basis-note">
                                    {{ $eligibility['tenure']['reason'] ?? 'No service basis available.' }}
                                </div>
                            </div>
                            <div class="eligibility-basis-item">
                                <div class="eligibility-basis-title">Attendance basis</div>
                                <div class="eligibility-basis-value">
                                    Score {{ number_format((float) ($eligibility['attendance']['final_score'] ?? 0), 2) }}%
                                    · Rating {{ (int) ($eligibility['attendance']['rating'] ?? 0) }}
                                </div>
                                <div class="eligibility-basis-note">
                                    {{ $eligibility['attendance']['reason'] ?? 'No attendance basis available.' }}
                                </div>
                            </div>
                            <div class="eligibility-basis-item">
                                <div class="eligibility-basis-title">Performance basis</div>
                                <div class="eligibility-basis-value">
                                    {{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'N/A')) }}
                                    · {{ number_format((float) ($eligibility['performance']['score'] ?? 0), 2) }}
                                </div>
                                <div class="eligibility-basis-note">
                                    {{ $eligibility['performance']['reason'] ?? 'No performance basis available.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mb-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header border-0 bg-transparent py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="cil-chart mr-2 text-primary"></i>Performance Result Used
                        </h3>
                    </div>
                    <div class="card-body">
                        @if ($performanceReview)
                            <div class="eligibility-summary-list">
                                <div class="eligibility-summary-row">
                                    <span>Review year</span>
                                    <strong>{{ $performanceReview->review_year }}</strong>
                                </div>
                                <div class="eligibility-summary-row">
                                    <span>Score</span>
                                    <strong>{{ number_format((float) $performanceReview->spms_score, 2) }}</strong>
                                </div>
                                <div class="eligibility-summary-row">
                                    <span>Rating</span>
                                    <strong>{{ strtoupper($performanceReview->rating) }}</strong>
                                </div>
                            </div>
                            <div class="eligibility-card-note mt-3">
                                {{ $performanceReview->remarks ?: 'No remarks.' }}
                            </div>
                        @else
                            <div class="eligibility-empty-state text-center py-4">
                                <i class="cil-clipboard"></i>
                                <h6>No finalized SPMS result found</h6>
                                <p class="mb-0">Performance eligibility will update once a finalized SPMS evaluation is available.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
