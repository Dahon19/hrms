# HRMS Developer Quick Reference

Last reviewed: 2026-05-02

## Stack

- Laravel 12, PHP 8.2+
- Blade + Vite + Alpine + jQuery
- CoreUI/Bootstrap styling, DataTables, Select2, FilePond
- Reverb/Pusher through Laravel Echo
- Pest for tests
- Scribe for API documentation
- DomPDF for PDF exports

## Directory Map

```text
app/
  Domain/
    Offboarding/Services/OffboardingWorkflowService.php
    TravelOrders/Services/TravelOrderWorkflowService.php
    TravelOrders/Services/TravelOrderAttendanceService.php
  Http/
    Controllers/          Web, workflow, report, and API/device controllers
    Controllers/Api/      BaseApiController
    Middleware/           role, security headers, device token, query logging
    Requests/             extracted validation classes
    Resources/            JSON API resources
  Models/                 40 Eloquent models
  Observers/              AuditObserver
  Policies/               OffboardingRecordPolicy, PdsProfilePolicy, TravelOrderPolicy
  Services/               shared business/application services

database/
  migrations/
  seeders/
  factories/              broad factory coverage for current models

resources/
  views/                  Blade UI
  css/                    Vite CSS entries
  js/                     Vite JS entries

routes/
  web.php                 main application routes
  api.php                 NFC/user API routes
  auth.php                auth scaffolding
  channels.php            broadcast channels

public/
  build/                  Vite production output
  docs/                   generated Scribe docs
```

## Current Counts

- Models: 40
- Controllers in `app/Http/Controllers`: 29 plus API base controller
- Services in `app/Services`: 14
- Domain services: 3
- API resources: 9
- Form requests: 7
- Factories: 39
- Routes: 176

## Common Commands

```bash
php artisan test
npm.cmd run build
php artisan route:list
php artisan migrate
php artisan db:seed
php artisan cache:clear
php artisan config:clear
php artisan scribe:generate
```

Use `npm.cmd` from PowerShell if script execution policy blocks `npm`.

## Key Routes

```text
GET  /dashboard
GET  /employees
GET  /attendance
GET  /attendance/history
GET  /attendance/calendar
GET  /leaves
GET  /leaves/approvals
GET  /job-postings
GET  /jobs
GET  /employee-documents
GET  /documents
GET  /departments
GET  /positions
GET  /offboarding
GET  /travel-orders
GET  /travel-orders/approvals
GET  /pds
GET  /reports
GET  /audit-logs
```

API/device routes:

```text
POST /api/nfc/receive
POST /api/nfc/scan
GET  /api/user
GET  /api/employees/search
GET  /api/nfc/latest
```

## Core Services

```php
App\Services\AccessControl
App\Services\AttendanceCalendarService
App\Services\AttendancePolicyService
App\Services\AuditLogger
App\Services\DashboardActivityService
App\Services\DashboardMetricsService
App\Services\DashboardService
App\Services\DepartmentMetricsService
App\Services\GeminiService
App\Services\HrmsNotificationService
App\Services\OffboardingWorkflowService
App\Services\RecruitmentActionService
App\Services\RecruitmentApprovalService
App\Services\ReportExportService
```

Domain workflow services:

```php
App\Domain\Offboarding\Services\OffboardingWorkflowService
App\Domain\TravelOrders\Services\TravelOrderWorkflowService
App\Domain\TravelOrders\Services\TravelOrderAttendanceService
```

## Validation Pattern

Use a form request when adding or changing mutating endpoints.

Existing examples:

```php
StoreEmployeeRequest
UpdateEmployeeRequest
StoreLeaveRequestRequest
UpdateLeaveRequestRequest
LeaveApprovalActionRequest
PdsSectionRequest
ProfileUpdateRequest
```

Still-good candidates for extraction:

- Travel order mutations and decisions
- Offboarding mutations and decisions
- Document/category/subcategory mutations
- Department and position mutations
- Recruitment mutations

## API Pattern

The API foundation exists but is not fully applied across the app.

Use these pieces for new JSON endpoints:

```php
App\Http\Controllers\Api\BaseApiController
App\Http\Resources\EmployeeResource
App\Http\Resources\UserResource
App\Http\Resources\DepartmentResource
App\Http\Resources\DepartmentTypeResource
App\Http\Resources\PositionResource
App\Http\Resources\EmployeeNfcResource
App\Http\Resources\LeaveRequestResource
App\Http\Resources\LeaveTypeResource
App\Http\Resources\AttendanceResource
```

If the API grows, add versioned routing before exposing broad resources:

```php
Route::prefix('v1')->group(function () {
    // API resources here
});
```

## Authorization

Gates are registered in `AppServiceProvider`.

Policies:

```php
OffboardingRecordPolicy
PdsProfilePolicy
TravelOrderPolicy
```

Route middleware aliases are registered in `bootstrap/app.php`:

```php
role
device.token
```

`query.log` exists in `app/Http/Kernel.php`, but Laravel 12 uses `bootstrap/app.php` for active middleware registration.

## Security

Implemented:

- Global `SecurityHeadersMiddleware`
- HSTS on secure requests
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- Secure file serving route for private uploads
- Device-token middleware for NFC ingestion
- PDS encryption with tests
- Audit observer for core models

Not implemented as of this review:

- Two-factor authentication
- OAuth/SAML SSO
- Broad scoped API token management
- Password history/expiration policy

## Testing

Run:

```bash
php artisan test
```

Current feature coverage includes:

- Attendance workflow and attendance settings
- Auth/profile
- Dashboard access
- Employee documents
- Leave approval workflow
- Offboarding workflow
- PDS autosave/encryption/access
- Recruitment workflow
- Travel order workflow and transport options

## CI Notes

`.github/workflows/ci.yml` has:

- Pest test job with MySQL
- Pint code style job
- PHPStan static-analysis job

Before depending on the PHPStan job, add a direct PHPStan/Larastan dev dependency and a config file.

## Asset Pipeline

Use Vite entries in `vite.config.js`.

The app uses dynamic Blade arrays in `resources/views/layouts/admin.blade.php` and `resources/views/layouts/embedded.blade.php` to load module-specific CSS/JS. Avoid reintroducing large public template distributions; install frontend packages through npm and import them from `resources/js` or `resources/css`.

## Documentation

- `SYSTEM_OVERVIEW.md`: high-level architecture and modules
- `ARCHITECTURE_DIAGRAMS.md`: text diagrams and workflows
- `DEVELOPER_QUICK_REFERENCE.md`: operational developer reference
- `TODO.md`: implementation status
- `IMPROVEMENT_RECOMMENDATIONS.md`: prioritized improvement roadmap
- `public/docs`: generated Scribe API docs
