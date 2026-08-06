# HRMS System Overview

Last reviewed: 2026-05-02

## Project Summary

The Human Resource Management System is a Laravel 12 application for employee administration, attendance, leave, recruitment, travel orders, offboarding, PDS records, documents, notifications, audit logs, and reporting.

The current implementation is primarily a Blade-rendered web application with a small API/device surface for NFC attendance, authenticated user lookup, employee search, and generated Scribe documentation.

## Technology Stack

| Layer | Current Tools |
| --- | --- |
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Blade, Vite, Alpine, jQuery |
| UI/assets | CoreUI, Bootstrap, DataTables, Select2, FilePond |
| Realtime | Laravel Reverb/Pusher through Laravel Echo |
| PDF/export | DomPDF and custom report export service |
| API docs | Scribe |
| Testing | Pest |
| Database | Laravel migrations, MySQL-compatible schema |
| CI | GitHub Actions workflow for tests, Pint, and intended PHPStan analysis |

## Current Modules

### Employee and Organization

- Employee CRUD and profile data.
- Department and department type management.
- Position management scoped to departments.
- Employee position assignments.
- Employee NFC/RFID assignment.

### Attendance

- Attendance recording through web and NFC/device endpoints.
- Attendance history, weekly view, live view, print view, and calendar view.
- Attendance settings for shift start/end, break times, grace period, overtime threshold, weekend overtime, and two-tap/four-tap behavior.
- Attendance anomaly handling through `AttendancePolicyService`.

### Leave

- Leave request filing, update, cancellation, and approval.
- Department head, HR, and president decision paths.
- Leave type management.
- Leave balance view and yearly setting management.
- Approved leave renders into attendance history as excused time.

### Recruitment

- Public job portal.
- Job posting management.
- Applicant submission and document upload.
- Applicant archive/reactivate/complete actions.
- Recruitment approval actions.
- Job application notifications through an event/listener.

### Travel Orders

- Travel order filing and attachments.
- Configurable transport options.
- Department, HR, and final approval stages.
- Cancellation, completion, reminders, print/export.
- Approved travel orders render into attendance history as official business.

### Offboarding

- Draft and submitted offboarding records.
- Clearance item workflow.
- Department, finance, HR, finalization, close, reopen, reminder, and cancellation-review actions.
- Account deactivation logic after the final working date.

### PDS

- PDS profile and related sections.
- Autosave by section.
- Submission, verification, correction request, and print.
- Sensitive PDS content is encrypted and covered by tests.

### Documents and Reports

- Document taxonomy through categories and subcategories.
- Employee document upload/review/reupload/reminder flows.
- Report hub and export support.
- Audit log view/export/print.

### Notifications and AI

- Notification listing, unread count, mark-read, mark-all-read, and redirect.
- Realtime notification infrastructure through Echo/Reverb/Pusher.
- Gemini-backed AI controller/service for chat/test interactions.

## Data Model Summary

The current model set contains 40 Eloquent models:

```text
Applicant
Attendance
AttendanceAnomaly
AttendanceSetting
AuditLog
ClearanceItem
Department
DepartmentMetric
DepartmentType
Document
DocumentCategory
DocumentSubcategory
Employee
EmployeeDocument
EmployeeNfc
EmployeePosition
Holiday
JobPosting
LeaveBalance
LeaveBalanceYearSetting
LeaveRequest
LeaveType
OffboardingRecord
PdsChild
PdsCivilServiceEligibility
PdsEducation
PdsFamilyBackground
PdsOtherInfo
PdsPersonalInfo
PdsProfile
PdsTraining
PdsVoluntaryWork
PdsWorkExperience
Position
RecruitmentApproval
ReportRun
TravelOrder
TravelOrderAttachment
TravelOrderTransportation
User
```

## Service Layer

Application services in `app/Services`:

| Service | Responsibility |
| --- | --- |
| `AccessControl` | Role and organizational access helpers |
| `AttendanceCalendarService` | Attendance calendar/holiday support |
| `AttendancePolicyService` | Shift, overtime, anomaly, and policy calculations |
| `AuditLogger` | Central audit logging helper |
| `DashboardActivityService` | Dashboard activity feed |
| `DashboardMetricsService` | Cached dashboard metrics |
| `DashboardService` | Dashboard composition |
| `DepartmentMetricsService` | Cached department metrics |
| `GeminiService` | AI integration |
| `HrmsNotificationService` | Notification creation/dispatch |
| `OffboardingWorkflowService` | Offboarding workflow support |
| `RecruitmentActionService` | Applicant/recruitment actions |
| `RecruitmentApprovalService` | Recruitment approval flow |
| `ReportExportService` | Export/report generation |

Domain services:

- `App\Domain\Offboarding\Services\OffboardingWorkflowService`
- `App\Domain\TravelOrders\Services\TravelOrderWorkflowService`
- `App\Domain\TravelOrders\Services\TravelOrderAttendanceService`

## API and Documentation

Current API endpoints:

```text
POST /api/nfc/receive
POST /api/nfc/scan
GET  /api/user
GET  /api/employees/search
GET  /api/nfc/latest
```

API support exists through:

- `App\Http\Controllers\Api\BaseApiController`
- 9 resource classes in `app/Http/Resources`
- `config/scribe.php`
- generated docs in `public/docs`

API versioning is not implemented yet.

## Middleware and Security

Registered through `bootstrap/app.php`:

- `SecurityHeadersMiddleware`
- `role`
- `device.token`

Additional middleware class:

- `QueryLogMiddleware` exists, but should be registered through `bootstrap/app.php` if it is intended to run in Laravel 12.

Security features:

- Secure response headers.
- HSTS on secure requests.
- Role middleware and gate/policy checks.
- Device-token checks for NFC ingestion when enabled.
- Private file access route with user/role/ownership checks.
- PDS encryption and audit metadata redaction tests.
- `AuditObserver` registered against core models.

## Testing

The Pest suite currently covers:

- Attendance workflows and settings.
- Auth/profile flows.
- Dashboard access.
- Employee document behavior.
- Leave approval workflow.
- Offboarding workflow.
- PDS autosave, encryption, and personal-info permissions.
- Recruitment workflow.
- Travel order workflow and transport-option management.

Latest local verification during this review:

```text
php artisan test
95 passed, 7 skipped
```

## CI/CD

`.github/workflows/ci.yml` includes:

- MySQL-backed Pest test job.
- Laravel Pint style job.
- PHPStan static-analysis job.

Follow-up needed:

- Add and configure PHPStan/Larastan directly before depending on the static-analysis job.
- Confirm whether the configured 80% coverage threshold is realistic for CI.

## Implementation Status Against Recommendations

Done:

- Base API controller.
- Initial API resources.
- Initial form requests.
- Factories for the current model set.
- Dashboard and department metrics caching.
- Security headers middleware.
- Scribe package/config/generated docs.
- GitHub Actions workflow scaffold.
- Query logging middleware class.

Partial:

- API standardization across all endpoints.
- Form request coverage across all mutating controllers.
- CI static analysis readiness.
- Query logging runtime registration.
- Cache invalidation strategy.
- Test coverage breadth.

Not yet implemented:

- API versioning.
- Broad role-aware API rate limiting.
- SPMS/IDP/rewards as implemented first-class modules.
- PWA/native mobile app.
- Biometric/geofencing attendance.
- Two-factor authentication and SSO.
- Notification preference/template system.
- Webhook system.

## Development Notes

- Prefer adding new frontend dependencies through npm and importing from `resources/js` or `resources/css`.
- Keep public assets small; avoid committing full vendor template distributions.
- Use form requests for new mutating controller actions.
- Use resources and `BaseApiController` for new JSON API endpoints.
- Add tests for workflow changes, especially authorization and state transitions.
