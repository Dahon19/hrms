@props(['user'])

@php
    $hour = now()->hour;
    $greeting = 'Good day';
    if ($hour < 12) $greeting = 'Good morning';
    elseif ($hour < 18) $greeting = 'Good afternoon';
    else $greeting = 'Good evening';

    $firstName = $user->employee->first_name ?? (explode(' ', trim($user->name))[0] ?? 'User');

    $isHrHead = \App\Services\AccessControl::isHrHead($user);
    $isHrStaff = \App\Services\AccessControl::isHrStaff($user);
    $isDepartmentSupport = \App\Services\AccessControl::isDepartmentSupport($user);
    $canAccessDashboard = \App\Services\AccessControl::canAccessDashboard($user);
    $employeeAttendanceRoute = route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
    ]);

    $isDashboardActive = request()->routeIs('dashboard');
    $isEmployeesActive = request()->routeIs('employees.*');
    $isAttendanceActive = request()->routeIs('attendance.*');
    $isLeaveActive = request()->routeIs('leaves.*') || request()->routeIs('leave-balances.*') || request()->routeIs('leave-types.*');
    $isTravelActive = request()->routeIs('travel-orders.*');
    $isReportsActive = request()->routeIs('reports.*');
    $isProfileActive = request()->routeIs('profile.*');

    // Updated: More is active if Leave, Travel, Reports or Profile is active
    $isMoreActive = $isLeaveActive || $isTravelActive || $isReportsActive || $isProfileActive;
    
    $employeePdsRoute = $user->employee ? route('pds.show', $user->employee) : '#';
@endphp

<nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
    <a href="{{ route('dashboard') }}" data-mobile-bottom-nav-link="1" class="mobile-bottom-nav__item {{ $isDashboardActive ? 'active' : '' }}">
        <i class="cil-speedometer mobile-bottom-nav__icon"></i>
        <span class="mobile-bottom-nav__label">Main</span>
    </a>

    <a href="{{ route('employees.index') }}" data-mobile-bottom-nav-link="1" class="mobile-bottom-nav__item {{ $isEmployeesActive ? 'active' : '' }}">
        <i class="cil-people mobile-bottom-nav__icon"></i>
        <span class="mobile-bottom-nav__label">Staff</span>
    </a>

    <a href="{{ $canAccessDashboard ? route('attendance.index') : $employeeAttendanceRoute }}" data-mobile-bottom-nav-link="1" class="mobile-bottom-nav__item {{ $isAttendanceActive ? 'active' : '' }}">
        <i class="cil-clock mobile-bottom-nav__icon"></i>
        <span class="mobile-bottom-nav__label">Attendance</span>
    </a>

    <div class="dropup mobile-bottom-nav__more">
        <button type="button" class="mobile-bottom-nav__item {{ $isMoreActive ? 'active' : '' }}" data-mobile-bottom-nav-toggle="1" aria-label="More">
            <i class="cil-options mobile-bottom-nav__icon"></i>
            <span class="mobile-bottom-nav__label">More</span>
        </button>

        <div class="dropdown-menu dropdown-menu-end mobile-bottom-nav__menu">
            <a href="{{ route('profile.show') }}" class="dropdown-item" data-mobile-bottom-nav-link="1">
                <i class="cil-user mr-2"></i>Profile
            </a>
            
            <a href="{{ route('leaves.index') }}" class="dropdown-item {{ $isLeaveActive ? 'active' : '' }}" data-mobile-bottom-nav-link="1">
                <i class="cil-calendar mr-2"></i>Leave
            </a>

            <a href="{{ route('travel-orders.index') }}" class="dropdown-item" data-mobile-bottom-nav-link="1">
                <i class="cil-location-pin mr-2"></i>Travel
            </a>
            <a href="{{ route('reports.index') }}" class="dropdown-item" data-mobile-bottom-nav-link="1">
                <i class="cil-chart-pie mr-2"></i>Reports
            </a>
            <div class="dropdown-divider"></div>
            <button type="button" class="dropdown-item text-danger" onclick="document.getElementById('logout-form').submit()">
                <i class="cil-account-logout mr-2"></i>Logout
            </button>
        </div>
    </div>
</nav>
