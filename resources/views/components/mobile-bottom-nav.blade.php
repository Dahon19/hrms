@props (['user'])

@php
    $positionName = strtolower(optional(optional($user->employee)->position)->name ?? '');
    $isHrHead = \App\Services\AccessControl::isHrHead($user);
    $isHrStaff = \App\Services\AccessControl::isHrStaff($user);
    $isDepartmentSupport = \App\Services\AccessControl::isDepartmentSupport($user);
    $canAccessDashboard = \App\Services\AccessControl::canAccessDashboard($user);
    $employeeAttendanceRoute = route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
    ]);
    $isPresidentApprover = strtolower(trim($user->employee?->department?->department ?? '')) === 'presidents office'
        && in_array($positionName, ['head', 'dean'], true);
    $isManagement = $user->isAdmin() || in_array($positionName, ['head', 'dean'], true) || $isHrStaff;

    $isDashboardActive = request()->routeIs('dashboard');
    $isEmployeesActive = request()->routeIs('employees.*');
    $isAttendanceActive = request()->routeIs('attendance.*');
    $isLeaveActive = request()->routeIs('leaves.*')
        || request()->routeIs('leave-balances.*')
        || request()->routeIs('leave-types.*');
    $isDocumentsActive = request()->routeIs('documents.*')
        || request()->routeIs('employee-documents.*');
    $isTravelActive = request()->routeIs('travel-orders.*');
    $isSpmsActive = request()->routeIs('spms.*');
    $isIdpActive = request()->routeIs('idp.*');
    $isRewardsActive = request()->routeIs('eligibility.*')
        || request()->routeIs('rewards.*')
        || request()->routeIs('rewards.eligibility.*');
    $isReportsActive = request()->routeIs('reports.*');
    $isOffboardingActive = request()->routeIs('offboarding.*');
    $isProfileActive = request()->routeIs('profile.*');

    $isMoreActive = $isTravelActive
        || $isDocumentsActive
        || $isSpmsActive
        || $isIdpActive
        || $isRewardsActive
        || $isOffboardingActive
        || $isReportsActive
        || $isProfileActive;

    $showLeaveNav = !$user->isAdmin() && !$isPresidentApprover;
    $employeeDocumentsRoute = $user->employee
        ? route('employee-documents.index', ['employee_id' => $user->employee->id])
        : route('employee-documents.index');
    $employeePdsRoute = $user->employee
        ? route('pds.show', $user->employee)
        : route('pds.index');
@endphp

<nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
    @if ($canAccessDashboard)
        <a
            href="{{ route('dashboard') }}"
            data-mobile-bottom-nav-link="1"
            class="mobile-bottom-nav__item {{ $isDashboardActive ? 'active' : '' }}"
        >
            <i class="cil-speedometer mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">Dashboard</span>
        </a>
    @endif

    @if ($isManagement || $isDepartmentSupport)
        <a
            href="{{ route('employees.index') }}"
            data-mobile-bottom-nav-link="1"
            class="mobile-bottom-nav__item {{ $isEmployeesActive ? 'active' : '' }}"
        >
            <i class="cil-people mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">Employees</span>
        </a>
    @else
        <a
            href="{{ route('travel-orders.index') }}"
            data-mobile-bottom-nav-link="1"
            class="mobile-bottom-nav__item {{ $isTravelActive ? 'active' : '' }}"
        >
            <i class="cil-location-pin mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">Travel</span>
        </a>
    @endif

    <a
        href="{{ $canAccessDashboard ? route('attendance.index') : $employeeAttendanceRoute }}"
        data-mobile-bottom-nav-link="1"
        class="mobile-bottom-nav__item {{ $isAttendanceActive ? 'active' : '' }}"
    >
        <i class="cil-clock mobile-bottom-nav__icon"></i>
        <span class="mobile-bottom-nav__label">Attendance</span>
    </a>

    @if ($isManagement)
        <a
            href="{{ route('travel-orders.index') }}"
            data-mobile-bottom-nav-link="1"
            class="mobile-bottom-nav__item {{ $isTravelActive ? 'active' : '' }}"
        >
            <i class="cil-location-pin mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">Travel</span>
        </a>
    @elseif ($showLeaveNav)
        <a
            href="{{ route('leaves.index') }}"
            data-mobile-bottom-nav-link="1"
            class="mobile-bottom-nav__item {{ $isLeaveActive ? 'active' : '' }}"
        >
            <i class="cil-calendar mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">Leave</span>
        </a>
    @endif

    <div class="dropup mobile-bottom-nav__more">
        <button
            type="button"
            class="mobile-bottom-nav__item {{ $isMoreActive ? 'active' : '' }}"
            data-mobile-bottom-nav-toggle="1"
            aria-controls="mobileBottomNavMenu"
            aria-expanded="false"
            aria-label="More navigation"
        >
            <i class="cil-options mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">More</span>
        </button>

        <div
            class="dropdown-menu dropdown-menu-end mobile-bottom-nav__menu"
            id="mobileBottomNavMenu"
        >
            @if ($isManagement)
                <a
                    class="dropdown-item"
                    href="{{ route('spms.cycles.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-loop-circular mr-2"></i>SPMS
                </a>
                <a
                    class="dropdown-item"
                    href="{{ route('idp.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-layers mr-2"></i>IDP
                </a>
                @if ($user->isAdmin() || $isHrHead || $isPresidentApprover)
                    <a
                        class="dropdown-item"
                        href="{{ route('rewards.index') }}"
                        data-mobile-bottom-nav-link="1"
                    >
                        <i class="cil-star mr-2"></i>Rewards
                    </a>
                @endif
                <a
                    class="dropdown-item"
                    href="{{ route('offboarding.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-user-x mr-2"></i>Offboarding
                </a>
                @if ($user->isAdmin() || $isHrStaff)
                    <a
                        class="dropdown-item"
                        href="{{ route('reports.index') }}"
                        data-mobile-bottom-nav-link="1"
                    >
                        <i class="cil-chart-pie mr-2"></i>Reports
                    </a>
                @endif
            @elseif ($isDepartmentSupport)
                <a
                    class="dropdown-item"
                    href="{{ route('departments.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-sitemap mr-2"></i>Organization
                </a>
                @if ($user->employee)
                    <a
                        class="dropdown-item"
                        href="{{ $employeePdsRoute }}"
                        data-mobile-bottom-nav-link="1"
                    >
                        <i class="cil-address-book mr-2"></i>Personal Data Sheet
                    </a>
                @endif
            @else
                <a
                    class="dropdown-item"
                    href="{{ route('spms.my-performance') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-user mr-2"></i>Performance
                </a>
                <a
                    class="dropdown-item"
                    href="{{ route('idp.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-layers mr-2"></i>IDP
                </a>
                <a
                    class="dropdown-item"
                    href="{{ route('offboarding.index') }}"
                    data-mobile-bottom-nav-link="1"
                >
                    <i class="cil-user-x mr-2"></i>Offboarding
                </a>
                @if ($user->employee)
                    <a
                        class="dropdown-item"
                        href="{{ $employeePdsRoute }}"
                        data-mobile-bottom-nav-link="1"
                    >
                        <i class="cil-address-book mr-2"></i>Personal Data Sheet
                    </a>
                @endif
            @endif

            <div class="dropdown-divider"></div>

            <button
                type="button"
                class="dropdown-item"
                data-coreui-toggle="modal"
                data-coreui-target="#mobileNotificationsModal"
                data-mobile-notification-open="1"
            >
                <i class="cil-bell mr-2"></i>Notifications
            </button>

            <button
                type="button"
                class="dropdown-item"
                data-coreui-toggle="modal"
                data-coreui-target="#profileEditModal"
            >
                <i class="cil-user mr-2"></i>Profile
            </button>

            <button
                type="button"
                class="dropdown-item"
                data-coreui-toggle="modal"
                data-coreui-target="#settingsModal"
            >
                <i class="cil-settings mr-2"></i>Preferences
            </button>

            <button
                type="button"
                class="dropdown-item"
                onclick="document.getElementById('logout-form').submit()"
            >
                <i class="cil-account-logout mr-2"></i>Logout
            </button>
        </div>
    </div>
</nav>
