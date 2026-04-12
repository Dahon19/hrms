<aside class="sidebar sidebar-fixed border-end app-sidebar" id="sidebar">
    @php
    $isHeadOrDean = \App\Services\AccessControl::isHeadOrDean($user);
    $canAccessDashboard = \App\Services\AccessControl::canAccessDashboard($user);
    $employeeAttendanceRoute = route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
    ]);
    $brandRoute = $canAccessDashboard ? route('dashboard') : $employeeAttendanceRoute;
    $brandLogo = asset('assets/img/Northeastern College.webp');
    $brandLogoFallback = asset('assets/dist/img/AdminLTELogo.png');
    $isAttendanceActive = request()->routeIs('attendance.*');
    $isAttendanceKpiActive = request()->routeIs('attendance.kpi.*');
    $isTravelOrderActive = request()->routeIs('travel-orders.*');
    $isLeaveActive = request()->routeIs('leaves.*') || request()->routeIs('leave-balances.*') || request()->routeIs('leave-types.*');
    $isDocumentsActive = request()->routeIs('documents.*') || request()->routeIs('employee-documents.*');
    $isRecruitmentActive = request()->routeIs('job-postings.*');
    $isPdsActive = request()->routeIs('pds.*');
    $isOrganizationActive = request()->routeIs('departments.*') || request()->routeIs('positions.*');
    $isOffboardingActive = request()->routeIs('offboarding.*');
    $isWorkforceActive = request()->routeIs('employees.*') || $isPdsActive || $isOrganizationActive || $isOffboardingActive;
    $isSpmsTreeActive = request()->routeIs('spms.cycles.*') || request()->routeIs('spms.cycle.*') || request()->routeIs('spms.evaluations.*') || request()->routeIs('spms.evaluation.*') || request()->routeIs('spms.my-performance');
    $isPerformanceActive = $isSpmsTreeActive || request()->routeIs('idp.*');
    $isRewardsActive = request()->routeIs('eligibility.*') || request()->routeIs('rewards.*') || request()->routeIs('rewards.eligibility.*');
    $isReportsActive = request()->routeIs('reports.*');
    $isLogsActive = request()->routeIs('audit-logs.*');
    $canViewLeaveBalances = $user->isAdmin() || $isHrHead || $isPresidentApprover;
    $isDepartmentSupport = \App\Services\AccessControl::isDepartmentSupport($user);
    $showLeaveNav = !$user->isAdmin() && !$isPresidentApprover;
    $employeeDocumentsRoute = $user->employee
        ? route('employee-documents.index', ['employee_id' => $user->employee->id])
        : route('employee-documents.index');
    $employeePdsRoute = $user->employee
        ? route('pds.show', $user->employee)
        : route('pds.index');
    $managementDocumentsRoute = $user->isAdmin()
        ? route('documents.index')
        : route('employee-documents.index');
    $managementDocumentsLabel = $user->isAdmin() ? 'Documents Setup' : 'Documents';
    $isHrRole = (bool) (($isHrStaff ?? false) || ($isHrHead ?? false));
    $canAccessAttendanceRecords = \Illuminate\Support\Facades\Gate::forUser($user)->allows('view-attendance-records');
    $canAccessAttendanceCalendar = \Illuminate\Support\Facades\Gate::forUser($user)->allows('view-attendance-calendar');
    $canAccessAttendanceKpi = \Illuminate\Support\Facades\Gate::forUser($user)->allows('view-attendance-kpi');
@endphp
    <div class="sidebar-header mt-2">
        <a href="{{ $brandRoute }}" class="sidebar-brand mb-2">
            <span class="sidebar-brand-mark">
                <img
                    src="{{ $brandLogo }}"
                    data-fallback="{{ $brandLogoFallback }}"
                    class="sidebar-brand-logo"
                    width="27"
                    height="27"
                    style="
                        display: block;
                        width: 1.7rem;
                        height: 1.7rem;
                        max-width: 1.7rem;
                        max-height: 1.7rem;
                        object-fit: contain;
                    "
                    alt="Northeastern College Logo"
                    onerror="
                        this.onerror = null;
                        this.src = this.dataset.fallback;
                    "
                />
            </span>
            <span class="sidebar-brand-copy">
                <span class="sidebar-brand-line">Northeastern</span>
                <span class="sidebar-brand-line"
                    >College
                    <span class="sidebar-brand-accent">HRMS</span></span
                >
            </span>
        </a>
        <button
            class="btn-close d-lg-none"
            type="button"
            aria-label="Close"
            data-sidebar-close
        ></button>
    </div>
    <div class="sidebar-body mb-3">
        <ul class="sidebar-nav nav" role="menu">
            @if ($canAccessDashboard)
                <li class="nav-header">Core</li>
                <li class="nav-item">
                    <a
                        href="{{ route('dashboard') }}"
                        class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-speedometer"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>
                @if ($user->isAdmin() || ($isHrStaff ?? false))
                    <li
                        class="nav-group {{ $isRecruitmentActive ? 'show' : '' }}"
                    >
                        <a
                            href="#"
                            class="nav-link nav-group-toggle {{ $isRecruitmentActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-bullhorn"></i>
                            <span class="nav-label">Recruitment</span>
                        </a>
                        <ul class="nav-group-items">
                            <li class="nav-item">
                                <a
                                    href="{{ route('job-postings.index') }}"
                                    class="nav-link {{ request()->routeIs('job-postings.index') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-briefcase"></i>
                                    <span class="nav-label">Job Postings</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    href="{{ route('job-postings.applicants') }}"
                                    class="nav-link {{ request()->routeIs('job-postings.applicants') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-description"></i>
                                    <span class="nav-label">Applications</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif
                <li class="nav-header">Workforce</li>
                <li class="nav-item">
                    <a
                        href="{{ route('employees.index') }}"
                        class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-people"></i>
                        <span class="nav-label">Employees</span>
                    </a>
                </li>
                <li class="nav-item">
                        <a
                        href="{{ route('pds.index') }}"
                        class="nav-link {{ $isPdsActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-address-book"></i>
                        <span class="nav-label">Personal Data Sheet</span>
                    </a>
                </li>
                @if ($user->isAdmin() || $isHeadOrDean)
                    <li class="nav-item">
                        <a
                            href="{{ route('offboarding.index') }}"
                            class="nav-link {{ $isOffboardingActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-user-x"></i>
                            <span class="nav-label">Offboarding</span>
                        </a>
                    </li>
                @endif
                @if ($user->canViewData() || \App\Services\AccessControl::isOrgChartViewer($user) || $user->isAdmin())
                    @if ($isHrRole)
                        <li class="nav-item">
                            <a
                                href="{{ route('departments.index') }}"
                                class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-building"></i>
                                <span class="nav-label">Departments</span>
                            </a>
                        </li>
                    @elseif ($user->isAdmin() || $isPresidentApprover)
                        <li
                            class="nav-group {{ $isOrganizationActive ? 'show' : '' }}"
                        >
                            <a
                                href="#"
                                class="nav-link nav-group-toggle {{ $isOrganizationActive ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-sitemap"></i>
                                <span class="nav-label">Organization</span>
                            </a>
                            <ul class="nav-group-items">
                                <li class="nav-item">
                                    <a
                                        href="{{ route('departments.index') }}"
                                        class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                                    >
                                        <i
                                            class="nav-icon cil-building"
                                        ></i>
                                        <span class="nav-label">Departments</span>
                                    </a>
                                </li>
                                @if ($user->isAdmin() || $isHrHead || $isPresidentApprover)
                                    <li class="nav-item">
                                        <a
                                            href="{{ route('positions.index') }}"
                                            class="nav-link {{ request()->routeIs('positions.*') ? 'active' : '' }}"
                                        >
                                            <i class="nav-icon cil-contact"></i>
                                            <span class="nav-label">Positions</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a
                                href="{{ route('departments.index') }}"
                                class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-building"></i>
                                <span class="nav-label">Department</span>
                            </a>
                        </li>
                    @endif
                @endif
                <li class="nav-header">Operations</li>
                <li
                    class="nav-group {{ $isAttendanceActive || $isAttendanceKpiActive ? 'show' : '' }}"
                >
                    <a
                        href="#"
                        class="nav-link nav-group-toggle {{ $isAttendanceActive || $isAttendanceKpiActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-clock"></i>
                        <span class="nav-label">Attendance</span>
                    </a>
                    <ul class="nav-group-items">
                        <li class="nav-item">
                            <a
                                href="{{ route('attendance.index') }}"
                                class="nav-link {{ request()->routeIs('attendance.index') ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-calendar-check"></i>
                                <span class="nav-label">Daily</span>
                            </a>
                        </li>
                        @if ($canAccessAttendanceRecords)
                            <li class="nav-item">
                                <a
                                    href="{{ route('attendance.history') }}"
                                    class="nav-link {{ request()->routeIs('attendance.history') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-history"></i>
                                    <span class="nav-label">Records</span>
                                </a>
                            </li>
                        @endif
                        @if ($canAccessAttendanceCalendar)
                            <li class="nav-item">
                                <a
                                    href="{{ route('attendance.calendar') }}"
                                    class="nav-link {{ request()->routeIs('attendance.calendar') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-calendar"></i>
                                    <span class="nav-label">Calendar</span>
                                </a>
                            </li>
                        @endif
                        @if ($canAccessAttendanceKpi)
                            <li class="nav-item">
                                <a
                                    href="{{ route('attendance.kpi.index') }}"
                                    class="nav-link {{ request()->routeIs('attendance.kpi.*') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-chart-line"></i>
                                    <span class="nav-label">KPI</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                <li class="nav-item">
                    <a
                        href="{{ route('travel-orders.index') }}"
                        class="nav-link {{ $isTravelOrderActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-location-pin"></i>
                        <span class="nav-label">Travel Orders</span>
                    </a>
                </li>
                @if ($user->isAdmin())
                    <li class="nav-item">
                        <a
                            href="{{ $managementDocumentsRoute }}"
                            class="nav-link {{ $isDocumentsActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-folder-open"></i>
                            <span class="nav-label">{{ $managementDocumentsLabel }}</span>
                        </a>
                    </li>
                @elseif (!$isHrRole && $isHeadOrDean && $user->employee)
                    <li class="nav-item">
                        <a
                            href="{{ $employeeDocumentsRoute }}"
                            class="nav-link {{ $isDocumentsActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-folder-open"></i>
                            <span class="nav-label">Documents</span>
                        </a>
                    </li>
                @endif
                @if ($showLeaveNav || $canViewLeaveBalances)
                    <li class="nav-group {{ $isLeaveActive ? 'show' : '' }}">
                        <a
                            href="#"
                            class="nav-link nav-group-toggle {{ $isLeaveActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-calendar"></i>
                            <span class="nav-label">Leave</span>
                        </a>
                        <ul class="nav-group-items">
                            @if ($showLeaveNav)
                                <li class="nav-item">
                                    <a
                                        href="{{ route('leaves.index') }}"
                                        class="nav-link {{ request()->routeIs('leaves.index') ? 'active' : '' }}"
                                    >
                                        <i class="nav-icon cil-description"></i>
                                        <span class="nav-label">Requests</span>
                                    </a>
                                </li>
                            @endif
                            @if ($canViewLeaveBalances)
                                <li class="nav-item">
                                    <a
                                        href="{{ route('leave-balances.index') }}"
                                        class="nav-link {{ request()->routeIs('leave-balances.*') ? 'active' : '' }}"
                                    >
                                        <i class="nav-icon cil-balance-scale"></i>
                                        <span class="nav-label">Balances</span>
                                    </a>
                                </li>
                            @endif
                            @if ($user->isAdmin() || $positionName === 'head')
                                <li class="nav-item">
                                    <a
                                        href="{{ route('leaves.approvals') }}"
                                        class="nav-link {{ request()->routeIs('leaves.approvals') ? 'active' : '' }}"
                                    >
                                        <i
                                            class="nav-icon cil-check-circle"
                                        ></i>
                                        <span class="nav-label">
                                            Approvals
                                            @if ($positionName === 'head' && $pendingLeaveCount)
                                                <span
                                                    class="badge badge-danger ml-2 sidebar-nav-badge"
                                                    >{{ $pendingLeaveCount }}</span
                                                >
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
                <li class="nav-header">Performance</li>
                <li class="nav-group {{ $isSpmsTreeActive ? 'show' : '' }}">
                    <a
                        href="#"
                        class="nav-link nav-group-toggle {{ $isSpmsTreeActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-calculator"></i>
                        <span class="nav-label">SPMS</span>
                    </a>
                    <ul class="nav-group-items">
                        <li class="nav-item">
                            <a
                                href="{{ route('spms.cycles.index') }}"
                                class="nav-link {{ request()->routeIs('spms.cycles.*') || request()->routeIs('spms.cycle.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-loop-circular"></i>
                                <span class="nav-label">Cycles</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a
                                href="{{ route('spms.evaluations.index') }}"
                                class="nav-link {{ request()->routeIs('spms.evaluations.*') || request()->routeIs('spms.evaluation.*') ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-list-rich"></i>
                                <span class="nav-label">Evaluations</span>
                            </a>
                        </li>
                        @if ($user->employee)
                            <li class="nav-item">
                                <a
                                    href="{{ route('spms.my-performance') }}"
                                    class="nav-link {{ request()->routeIs('spms.my-performance') ? 'active' : '' }}"
                                >
                                    <i class="nav-icon cil-user"></i>
                                    <span class="nav-label">Performance</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
                <li class="nav-item">
                    <a
                        href="{{ route('idp.index') }}"
                        class="nav-link {{ request()->routeIs('idp.*') ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-layers"></i>
                        <span class="nav-label"
                            >Individual Development Plan</span
                        >
                    </a>
                </li>
                @if ($user->isAdmin() || $isHrHead || $isPresidentApprover)
                    <li class="nav-header">Rewards &amp; Recognition</li>
                @endif
                @if ($user->isAdmin() || $isHrHead)
                    <li class="nav-item">
                        <a
                            href="{{ route('rewards.eligibility.index') }}"
                            class="nav-link {{ request()->routeIs('eligibility.*') || request()->routeIs('rewards.eligibility.*') ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-badge"></i>
                            <span class="nav-label">Eligibility Dashboard</span>
                        </a>
                    </li>
                @endif
                @if ($user->isAdmin() || $isHrHead || $isPresidentApprover)
                    <li class="nav-item">
                        <a
                            href="{{ route('rewards.index') }}"
                            class="nav-link {{ request()->routeIs('rewards.index') || request()->routeIs('rewards.show') || request()->routeIs('rewards.print') ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-star"></i>
                            <span class="nav-label">Rewards History</span>
                        </a>
                    </li>
                @endif
                @if ($user->isAdmin() || $isHrHead || $isPresidentApprover)
                    <li class="nav-header">Reports</li>
                    <li class="nav-item">
                        <a
                            href="{{ route('reports.index') }}"
                            class="nav-link {{ $isReportsActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-spreadsheet"></i>
                            <span class="nav-label">Summary</span>
                        </a>
                    </li>
                @endif
                @if ($user->isAdmin())
                    <li class="nav-header">System</li>
                    <li class="nav-item">
                        <a
                            href="{{ route('audit-logs.index') }}"
                            class="nav-link {{ $isLogsActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-clipboard"></i>
                            <span class="nav-label">Audit Logs</span>
                        </a>
                    </li>
                @endif
            @else
                <li class="nav-header">Operations</li>
                <li class="nav-item">
                    <a
                        href="{{ $employeeAttendanceRoute }}"
                        class="nav-link {{ $isAttendanceActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-clock"></i>
                        <span class="nav-label">Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        href="{{ route('travel-orders.index') }}"
                        class="nav-link {{ $isTravelOrderActive ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-location-pin"></i>
                        <span class="nav-label">Travel Orders</span>
                    </a>
                </li>
                @if ($showLeaveNav)
                    <li class="nav-item">
                        <a
                            href="{{ route('leaves.index') }}"
                            class="nav-link {{ $isLeaveActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-calendar"></i>
                            <span class="nav-label">Leave</span>
                        </a>
                    </li>
                @endif
                @if ($isDepartmentSupport)
                    <li class="nav-header">Workforce</li>
                    <li class="nav-item">
                        <a
                            href="{{ route('employees.index') }}"
                            class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-people"></i>
                            <span class="nav-label">Employees</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a
                            href="{{ route('departments.index') }}"
                            class="nav-link {{ $isOrganizationActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-building"></i>
                            <span class="nav-label">Department</span>
                        </a>
                    </li>
                    @if (!$isHrRole)
                        <li class="nav-item">
                            <a
                                href="{{ route('employee-documents.index') }}"
                                class="nav-link {{ $isDocumentsActive ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-folder-open"></i>
                                <span class="nav-label">Documents</span>
                            </a>
                        </li>
                    @endif
                @endif
                @if ($user->employee)
                    <li class="nav-item">
                        <a
                            href="{{ $employeePdsRoute }}"
                            class="nav-link {{ $isPdsActive ? 'active' : '' }}"
                        >
                            <i class="nav-icon cil-address-book"></i>
                            <span class="nav-label">Personal Data Sheet</span>
                        </a>
                    </li>
                    @if (!$isHrRole)
                        <li class="nav-item">
                            <a
                                href="{{ $employeeDocumentsRoute }}"
                                class="nav-link {{ $isDocumentsActive ? 'active' : '' }}"
                            >
                                <i class="nav-icon cil-folder-open"></i>
                                <span class="nav-label">Documents</span>
                            </a>
                        </li>
                    @endif
                @endif
                <li class="nav-header">Performance</li>
                <li class="nav-item">
                    <a
                        href="{{ route('spms.my-performance') }}"
                        class="nav-link {{ request()->routeIs('spms.my-performance') || request()->routeIs('spms.evaluation.*') ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-user"></i>
                        <span class="nav-label">Performance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        href="{{ route('idp.index') }}"
                        class="nav-link {{ request()->routeIs('idp.*') ? 'active' : '' }}"
                    >
                        <i class="nav-icon cil-layers"></i>
                        <span class="nav-label"
                            >Individual Development Plan</span
                        >
                    </a>
                </li>
            @endif
        </ul>
    </div>
</aside>
