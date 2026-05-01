<header class="header header-sticky p-0 mb-4 app-header">
    @php
    $canAccessOffboarding = \Illuminate\Support\Facades\Gate::forUser($authUser)->allows('viewAny', \App\Models\OffboardingRecord::class);
    $showOffboardingInUserMenu = $canAccessOffboarding
        && !$authUser->isAdmin()
        && !\App\Services\AccessControl::isHeadOrDean($authUser);
    $routeName = request()->route()?->getName() ?? 'dashboard';
    $breadcrumbHead = 'Core';
    $breadcrumbTree = 'Dashboard';
    $breadcrumbItem = 'Dashboard';
    if (request()->routeIs('dashboard')) {
        $breadcrumbHead = 'Core';
        $breadcrumbTree = 'Dashboard';
        $breadcrumbItem = 'Dashboard';
    } elseif (request()->routeIs('job-postings.index')) {
        $breadcrumbHead = 'Core';
        $breadcrumbTree = 'Recruitment';
        $breadcrumbItem = 'Job Postings';
    } elseif (request()->routeIs('job-postings.applicants')) {
        $breadcrumbHead = 'Core';
        $breadcrumbTree = 'Recruitment';
        $breadcrumbItem = 'Applications';
    } elseif (request()->routeIs('employees.*')) {
        $breadcrumbHead = 'Workforce';
        $breadcrumbTree = 'Employees';
        $breadcrumbItem = 'Employees';
    } elseif (request()->routeIs('departments.*')) {
        $breadcrumbHead = 'Workforce';
        $breadcrumbTree = 'Organization';
        $breadcrumbItem = 'Departments';
    } elseif (request()->routeIs('offboarding.*')) {
        $breadcrumbHead = 'Workforce';
        $breadcrumbTree = 'Employees';
        $breadcrumbItem = 'Offboarding';
    } elseif (request()->routeIs('positions.*')) {
        $breadcrumbHead = 'Workforce';
        $breadcrumbTree = 'Organization';
        $breadcrumbItem = 'Positions';
    } elseif (request()->routeIs('attendance.index')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Attendance';
        $breadcrumbItem = 'Daily';
    } elseif (request()->routeIs('attendance.history')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Attendance';
        $breadcrumbItem = 'Records';
    } elseif (request()->routeIs('travel-orders.transport-options.*')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Travel Orders';
        $breadcrumbItem = 'Transport Options';
    } elseif (request()->routeIs('travel-orders.index') || request()->routeIs('travel-orders.create') || request()->routeIs('travel-orders.show')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Travel Orders';
        $breadcrumbItem = 'Travel Orders';
    } elseif (request()->routeIs('travel-orders.approvals')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Travel Orders';
        $breadcrumbItem = 'Approvals';
    } elseif (request()->routeIs('leaves.index')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Leave';
        $breadcrumbItem = 'Requests';
    } elseif (request()->routeIs('leave-balances.*')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Leave';
        $breadcrumbItem = 'Balances';
    } elseif (request()->routeIs('leaves.approvals')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Leave';
        $breadcrumbItem = 'Approvals';
    } elseif (request()->routeIs('leave-types.*')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Leave';
        $breadcrumbItem = 'Types';
    } elseif (request()->routeIs('documents.*') || request()->routeIs('employee-documents.*')) {
        $breadcrumbHead = 'Operations';
        $breadcrumbTree = 'Records';
        $breadcrumbItem = request()->routeIs('employee-documents.*') ? 'Documents' : 'Catalog';
    } elseif (request()->routeIs('pds.*')) {
        $breadcrumbHead = 'Workforce';
        $breadcrumbTree = 'Personal Data Sheet';
        $breadcrumbItem = 'PDS';
    } elseif (request()->routeIs('eligibility.*') || request()->routeIs('rewards.eligibility.*')) {
        $breadcrumbHead = 'Rewards & Recognition';
        $breadcrumbTree = 'Eligibility Dashboard';
        $breadcrumbItem = 'Eligibility Dashboard';
    } elseif (request()->routeIs('rewards.*')) {
        $breadcrumbHead = 'Rewards & Recognition';
        $breadcrumbTree = 'Rewards History';
        $breadcrumbItem = 'Rewards History';
    } elseif (request()->routeIs('reports.index')) {
        $breadcrumbHead = 'Reports';
        $breadcrumbTree = 'Console';
        $breadcrumbItem = 'Console';
    } elseif (request()->routeIs('audit-logs.*')) {
        $breadcrumbHead = 'System';
        $breadcrumbTree = 'Audit Logs';
        $breadcrumbItem = 'Audit Logs';
    } elseif (request()->routeIs('profile.*')) {
        $breadcrumbHead = 'Account';
        $breadcrumbTree = 'Profile';
        $breadcrumbItem = 'Profile';
    }
@endphp
    @php
        $hour = now()->hour;
        $greetingPrefix = 'Good day';
        if ($hour < 12) $greetingPrefix = 'Good morning';
        elseif ($hour < 18) $greetingPrefix = 'Good afternoon';
        else $greetingPrefix = 'Good evening';
        $firstName = $authUser->employee->first_name ?? (explode(' ', trim($authUser->name))[0] ?? 'User');
    @endphp
    <div class="container-fluid px-4 border-bottom">
        <div class="navbar navbar-expand align-items-center min-h-auto w-100">
            <div class="app-header-start">
                <button
                    class="btn btn-ghost-primary app-header-toggle header-toggler"
                    data-sidebar-toggle
                    type="button"
                    aria-label="Toggle navigation"
                >
                    <i class="cil-menu"></i>
                </button>
                


                <div class="app-header-breadcrumb-wrap">
                    <nav class="app-header-breadcrumb" aria-label="breadcrumb">
                        <ol class="breadcrumb my-0 py-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}">{{ $breadcrumbHead }}</a>
                            </li>
                            <li class="breadcrumb-item">{{ $breadcrumbTree }}</li>
                            <li class="breadcrumb-item active" aria-current="page">
                                {{ $breadcrumbItem }}
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div
                class="navbar-nav align-items-center gap-2 gap-lg-3 app-header-actions"
            >
                <div class="nav-item dropdown hrms-notification-nav-item">
                    <a
                        class="nav-link d-flex align-items-center position-relative hrms-notification-toggle"
                        data-coreui-toggle="dropdown"
                        data-coreui-auto-close="outside"
                        data-bs-auto-close="outside"
                        href="#"
                        id="hrmsNotificationBell"
                        aria-label="Open notifications"
                    >
                        <i class="cil-bell fs-5"></i>
                        <span
                            class="badge badge-danger navbar-badge hrms-notification-badge d-none"
                            id="hrmsNotificationBadge"
                            >0</span
                        >
                    </a>
                    <div
                        class="dropdown-menu dropdown-menu-end p-0 hrms-notification-dropdown"
                    >
                        <div
                            class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between"
                        >
                            <span class="text-muted small fw-bold"
                                >Notifications</span
                            >
                            <x-ui.button
                                type="button"
                                variant="outline-secondary"
                                size="sm"
                                class="hrms-mark-all-read"
                            >
                                Mark all as read
                            </x-ui.button>
                        </div>
                        <div
                            id="hrmsNotificationList"
                            class="hrms-notification-list"
                        >
                            <div class="px-3 py-3 text-muted small text-center">
                                No notifications yet.
                            </div>
                        </div>
                        <div class="hrms-notification-footer">
                            <button
                                type="button"
                                class="btn btn-light btn-sm hrms-notification-expand d-none"
                            >
                                See all notifications
                            </button>
                        </div>
                    </div>
                </div>
                <div class="nav-item dropdown">
                    <a
                        href="#"
                        class="topbar-user-chip"
                        id="topbarUserDropdown"
                        data-coreui-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Open user menu"
                    >
                        <span class="topbar-user-avatar-wrap">
                            <img
                                src="{{ $avatarUrl ?: $avatarFallback }}"
                                class="border topbar-user-avatar"
                                alt="User"
                            />
                        </span>
                        <span
                            class="topbar-user-name d-none d-md-inline"
                            >{{ $authUser->name }}</span
                        >
                    </a>
                    <div
                        class="dropdown-menu dropdown-menu-end topbar-user-dropdown"
                        aria-labelledby="topbarUserDropdown"
                    >
                        <a
                            href="#"
                            class="dropdown-item topbar-user-menu-item"
                            data-coreui-toggle="modal"
                            data-coreui-target="#profileEditModal"
                        >
                            <i class="cil-user"></i> <span>Profile</span>
                        </a>
                        @if ($showOffboardingInUserMenu)
                            <a
                                href="{{ route('offboarding.index') }}"
                                class="dropdown-item topbar-user-menu-item"
                            >
                                <i class="cil-user-x"></i>
                                <span>Offboarding</span>
                            </a>
                        @endif
                        <a
                            href="#"
                            class="dropdown-item topbar-user-menu-item"
                            data-coreui-toggle="modal"
                            data-coreui-target="#settingsModal"
                        >
                            <i class="cil-settings"></i>
                            <span>Preferences</span>
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <button
                            type="button"
                            class="dropdown-item topbar-user-menu-item topbar-user-menu-logout"
                            onclick="
                                document.getElementById('logout-form').submit()
                            "
                        >
                            <i class="cil-account-logout"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
