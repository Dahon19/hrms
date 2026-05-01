@extends('layouts.admin')

@section('content')
    @php
        $header = $dashboard['header'] ?? [];
        $actionCenter = $dashboard['action_center'] ?? [];
        $kpis = $dashboard['kpis'] ?? [];
        $progressGroups = $dashboard['progress_groups'] ?? [];
        $charts = $dashboard['charts'] ?? [];
        $recruitment = $dashboard['recruitment'] ?? [];
        $calendar = $dashboard['calendar'] ?? [];
        $offboarding = $dashboard['offboarding'] ?? [];
        $emptyStates = $dashboard['empty_states'] ?? [];

        $displayKpis = collect($kpis)->take(6)->values()->all();
        $displayCharts = collect($charts)->take(6)->values()->all();
        $displayActionCenter = collect($actionCenter)->take(6)->values()->all();
        $displayProgressGroups = collect($progressGroups)->take(4)->values()->all();

        $calendarSummary = collect($calendar['summary'] ?? [])->take(2)->values()->all();
        $calendarEvents = collect($calendar['events'] ?? [])->take(4)->values()->all();

        $recruitmentRoles = collect($recruitment['roles'] ?? [])->take(4)->values()->all();
        $recentApplicants = collect($recruitment['recent_applicants'] ?? [])->take(4)->values()->all();
        $recruitmentRecords = $recentApplicants !== []
            ? $recentApplicants
            : collect($recruitmentRoles)->map(function (array $role) {
                return [
                    'title' => $role['title'] ?? 'Role',
                    'meta' => $role['department'] ?? 'Department',
                    'date' => (($role['count'] ?? 0) . ' applicants'),
                    'href' => $role['href'] ?? '#',
                ];
            })->values()->all();
        $compactHeroButtons = in_array($header['role_key'] ?? 'employee', ['department-head', 'employee'], true);
    @endphp

    <div class="dashboard-shell dashboard-bi">
        <x-ui.hero
            class="dashboard-hero mb-4{{ $compactHeroButtons ? ' dashboard-hero--compact-actions' : '' }}"
            eyebrow="Dashboard"
            :title="$header['title'] ?? 'Dashboard'"
            :subtitle="$header['subtitle'] ?? ''"
        >
            <x-slot:actions>
                <div class="hero-actions__stack dashboard-hero-stack">
                    <div class="hero-actions__row dashboard-hero-meta">
                        <span class="dashboard-chip">
                            <i class="cil-user me-2"></i>{{ $header['role_label'] ?? 'User' }}
                        </span>
                        <span class="dashboard-chip">
                            <i class="cil-calendar me-2"></i>{{ $header['date_label'] ?? now()->format('l, F j, Y') }}
                        </span>
                    </div>
                </div>
            </x-slot:actions>
        </x-ui.hero>

        @if ($offboarding !== [])
            <div class="card dashboard-panel mb-4">
                <div class="card-body p-3 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <div class="dashboard-eyebrow">Active Workflow</div>
                        <h2 class="dashboard-card-title mb-1">{{ $offboarding['title'] }}</h2>
                        <div class="text-muted small">{{ $offboarding['meta'] }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge badge-info px-3 py-2">{{ $offboarding['status'] }}</span>
                        <x-ui.button type="view" size="sm" variant="outline-primary" :href="$offboarding['href']">
                            View Workflow
                        </x-ui.button>
                        <x-ui.button type="print" size="sm" variant="outline-secondary" :href="$offboarding['print_href']">
                            Print Clearance
                        </x-ui.button>
                    </div>
                </div>
            </div>
        @endif

        @if ($displayKpis !== [])
            <div class="row g-3 mb-4">
                @foreach ($displayKpis as $metric)
                    <div class="col-xxl-2 col-xl-4 col-md-6">
                        <div class="card dashboard-panel dashboard-bi-kpi h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <span class="dashboard-bi-kpi__label">{{ $metric['label'] }}</span>
                                    <span class="dashboard-bi-kpi__icon">
                                        <i class="{{ $metric['icon'] }}"></i>
                                    </span>
                                </div>
                                <div class="dashboard-bi-kpi__value">{{ $metric['display_value'] }}</div>
                                <div class="dashboard-bi-kpi__meta">{{ $metric['meta'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-xxl-8">
                <div class="card dashboard-panel h-100">
                    <div class="card-header bg-white border-0 pb-0">
                        <div class="dashboard-eyebrow">Analytics Board</div>
                        <h2 class="dashboard-card-title mb-2">Operational intelligence</h2>
                    </div>
                    <div class="card-body pt-3">
                        @if ($displayCharts !== [])
                            <div id="dashboardCharts" data-dashboard-charts='@json($displayCharts)'>
                                <div class="row g-3">
                                    @foreach ($displayCharts as $chart)
                                        <div class="{{ $loop->first ? 'col-12' : 'col-md-6' }}">
                                            <div class="dashboard-bi-chart-card{{ $loop->first ? ' dashboard-bi-chart-card--wide' : '' }}">
                                                <div class="dashboard-card-title">{{ $chart['title'] }}</div>
                                                <div class="text-muted small">{{ $chart['subtitle'] }}</div>
                                                <div class="dashboard-chart-frame">
                                                    <div class="dashboard-chart-loading" data-dashboard-chart-loading>
                                                        <x-ui.spinner color="primary" centered />
                                                    </div>
                                                    <canvas id="dashboard-chart-{{ $chart['id'] }}"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty">No chart data is currently available.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xxl-4">
                <div class="card dashboard-panel mb-4">
                    <div class="card-header bg-white border-0 pb-0">
                        <div class="dashboard-eyebrow">Requests</div>
                        <h2 class="dashboard-card-title mb-2">Queue overview</h2>
                    </div>
                    <div class="card-body pt-3">
                        @if ($displayActionCenter !== [])
                            <div class="dashboard-bi-queue">
                                @foreach ($displayActionCenter as $item)
                                    <a href="{{ $item['href'] }}" class="dashboard-bi-queue__item">
                                        <span class="dashboard-bi-queue__icon">
                                            <i class="{{ $item['icon'] }}"></i>
                                        </span>
                                        <span class="dashboard-bi-queue__copy">
                                            <strong>{{ $item['label'] }}</strong>
                                            <small>{{ $item['meta'] }}</small>
                                        </span>
                                        <span class="dashboard-bi-queue__count">{{ $item['count_label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty">{{ $emptyStates['action_center'] ?? 'No action items.' }}</div>
                        @endif
                    </div>
                </div>

                <div class="card dashboard-panel">
                    <div class="card-header bg-white border-0 pb-0">
                        <div class="dashboard-eyebrow">Timeline</div>
                        <h2 class="dashboard-card-title mb-2">Calendar signals</h2>
                    </div>
                    <div class="card-body pt-3">
                        @if ($calendarSummary !== [])
                            <div class="dashboard-summary-grid mb-3">
                                @foreach ($calendarSummary as $item)
                                    <div class="dashboard-summary-card">
                                        <small>{{ $item['label'] }}</small>
                                        <strong>{{ $item['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($calendarEvents !== [])
                            <div class="dashboard-list-stack">
                                @foreach ($calendarEvents as $event)
                                    <div class="dashboard-list-item dashboard-list-item--static">
                                        <strong>{{ $event['title'] }}</strong>
                                        <span>{{ $event['meta'] }}</span>
                                        <small>{{ $event['date'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty">No upcoming dates to show.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @php
            $expiringDocs = [];
            if (Auth::user()?->canViewData()) {
                $expiringDocs = \App\Models\EmployeeDocument::query()
                    ->with('employee')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', today())
                    ->whereDate('expires_at', '<=', now()->addDays(30))
                    ->orderBy('expires_at')
                    ->limit(6)
                    ->get()
                    ->map(fn ($d) => [
                        'name'      => $d->document_name ?? 'Document',
                        'employee'  => trim(($d->employee?->first_name ?? '') . ' ' . ($d->employee?->last_name ?? '')),
                        'days_left' => (int) now()->diffInDays($d->expires_at, false),
                        'expires'   => $d->expires_at?->format('M d, Y'),
                    ])->all();
            }
        @endphp

        @if (!empty($expiringDocs))
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card dashboard-panel border-warning" style="border-left: 4px solid #f6c23e !important;">
                    <div class="card-header bg-white border-0 pb-0 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="dashboard-eyebrow text-warning">Document Monitoring</div>
                            <h2 class="dashboard-card-title mb-0">
                                <i class="cil-file mr-2 text-warning"></i>
                                Documents Expiring Within 30 Days
                            </h2>
                        </div>
                        <a href="{{ route('employee-documents.index') }}" class="btn btn-sm btn-outline-warning">
                            View All
                        </a>
                    </div>
                    <div class="card-body pt-3">
                        <div class="row g-2">
                            @foreach ($expiringDocs as $doc)
                                @php
                                    $urgency = $doc['days_left'] <= 7 ? 'danger' : ($doc['days_left'] <= 14 ? 'warning' : 'info');
                                @endphp
                                <div class="col-md-4 col-sm-6">
                                    <div class="p-2 border rounded d-flex align-items-start gap-2 bg-light h-100">
                                        <span class="badge badge-{{ $urgency }} mt-1" style="min-width:42px;text-align:center;">
                                            {{ $doc['days_left'] }}d
                                        </span>
                                        <div>
                                            <div class="font-weight-bold small text-dark">{{ $doc['name'] }}</div>
                                            <div class="text-muted" style="font-size:0.78rem;">{{ $doc['employee'] }}</div>
                                            <div class="text-muted" style="font-size:0.75rem;">Expires {{ $doc['expires'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-4 mb-4">
            @if ($recruitment !== [])
                <div class="col-xxl-7">
                    <div class="card dashboard-panel h-100">
                        <div class="card-header bg-white border-0">
                            <div class="dashboard-eyebrow">Recruitment</div>
                            <h2 class="dashboard-card-title mb-0">Recruitment progress</h2>
                        </div>
                        <div class="card-body pt-3">
                            <div class="dashboard-summary-grid mb-3">
                                @foreach ($recruitment['summary'] ?? [] as $item)
                                    <div class="dashboard-summary-card">
                                        <small>{{ $item['label'] }}</small>
                                        <strong>{{ $item['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>

                            <div class="dashboard-bi-table">
                                <div class="dashboard-bi-table__head">
                                    <span>Name / Role</span>
                                    <span>Current Stage</span>
                                    <span>Updated</span>
                                </div>
                                @foreach ($recruitmentRecords as $record)
                                    <a href="{{ $record['href'] }}" class="dashboard-bi-table__row">
                                        <span class="fw-semibold">{{ $record['title'] }}</span>
                                        <span>{{ $record['meta'] }}</span>
                                        <span>{{ $record['date'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-xxl-{{ $recruitment !== [] ? '5' : '12' }}">
                <div class="card dashboard-panel h-100">
                    <div class="card-header bg-white border-0">
                        <div class="dashboard-eyebrow">Performance Indicators</div>
                        <h2 class="dashboard-card-title mb-0">Coverage and completion</h2>
                    </div>
                    <div class="card-body pt-3">
                        @if ($displayProgressGroups !== [])
                            <div class="dashboard-progress-grid">
                                @foreach ($displayProgressGroups as $group)
                                    <x-ui.progress-group
                                        :icon="$group['icon']"
                                        :label="$group['label']"
                                        :value="$group['value']"
                                        :meta="$group['meta']"
                                        :percent="$group['percent']"
                                        :color="$group['color']"
                                    />
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty">No performance indicators available.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
