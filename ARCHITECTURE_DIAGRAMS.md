# HRMS Architecture Diagrams

Last reviewed: 2026-05-02

These diagrams reflect the current Laravel 12 application structure after reviewing the codebase, routes, services, models, middleware, and tests.

## 1. System Architecture

```text
Browser / NFC Device / API Consumer
        |
        v
Laravel Routing
  - routes/web.php
  - routes/api.php
  - routes/auth.php
  - routes/channels.php
        |
        v
Middleware
  - SecurityHeadersMiddleware (global via bootstrap/app.php)
  - auth / verified / role
  - device.token + throttle for NFC API
        |
        v
Controllers
  - Web controllers return Blade views, redirects, downloads, JSON fragments
  - API/device endpoints currently focus on NFC and authenticated user/search
        |
        v
Services and Domain Services
  - AccessControl, AuditLogger, Dashboard*, DepartmentMetrics
  - AttendancePolicy, AttendanceCalendar
  - Recruitment*, OffboardingWorkflow, ReportExport, Gemini
  - Domain/TravelOrders and Domain/Offboarding workflow services
        |
        v
Eloquent Models and Observers
  - 40 current models
  - AuditObserver registered for core model changes
        |
        v
Database / Cache / Files / Broadcasts
  - MySQL-compatible schema through migrations
  - Laravel cache for dashboard, reports, NFC state, and debounce locks
  - Local/private files served through controlled routes
  - Reverb/Pusher broadcasting for notifications
```

## 2. Frontend Asset Flow

```text
resources/views/*.blade.php
        |
        | @vite([...module CSS/JS entries...])
        v
resources/css and resources/js
        |
        | vite.config.js
        v
public/build/manifest.json + hashed assets
        |
        v
Browser
```

Key frontend facts:

- Blade is the primary server-rendered UI.
- Vite builds module-specific CSS/JS entries.
- Core libraries include Alpine, jQuery, DataTables, Select2, FilePond, CoreUI, Bootstrap, Laravel Echo, and Pusher.
- Legacy public template assets have been pruned to only files still referenced by layouts.

## 3. API and Documentation Surface

```text
routes/api.php
  POST /api/nfc/receive     -> EmployeeNfcController@receiveNfc
  POST /api/nfc/scan        -> EmployeeNfcController@scan
  GET  /api/user            -> authenticated user

routes/web.php authenticated JSON helpers
  GET /api/employees/search -> EmployeeSearchController
  GET /api/nfc/latest       -> EmployeeNfcController@latestNfc

API infrastructure
  App\Http\Controllers\Api\BaseApiController
  App\Http\Resources\*Resource
  config/scribe.php
  public/docs
```

The API layer has a foundation, but there is no versioned `/api/v1` structure yet.

## 4. Domain Model Map

```text
User and Access
  User
  AccessControl service
  Gates and policies

Organization
  Department
  DepartmentType
  DepartmentMetric
  Position

Employee
  Employee
  EmployeePosition
  EmployeeDocument
  EmployeeNfc

Attendance
  Attendance
  AttendanceAnomaly
  AttendanceSetting
  Holiday

Leave
  LeaveType
  LeaveRequest
  LeaveBalance
  LeaveBalanceYearSetting

Recruitment
  JobPosting
  Applicant
  RecruitmentApproval

PDS
  PdsProfile
  PdsPersonalInfo
  PdsFamilyBackground
  PdsChild
  PdsEducation
  PdsCivilServiceEligibility
  PdsWorkExperience
  PdsVoluntaryWork
  PdsTraining
  PdsOtherInfo

Travel Orders
  TravelOrder
  TravelOrderAttachment
  TravelOrderTransportation

Offboarding
  OffboardingRecord
  ClearanceItem

Administration
  Document
  DocumentCategory
  DocumentSubcategory
  AuditLog
  ReportRun
```

## 5. Attendance Workflow

```text
Employee / NFC tap
        |
        v
AttendanceController or EmployeeNfcController
        |
        v
Cache debounce + latest NFC state
        |
        v
AttendanceSetting
  - shift start/end
  - break start/end
  - grace period
  - overtime threshold
  - weekend overtime
  - two-tap or four-tap mode
        |
        v
Attendance record update
        |
        v
AttendancePolicyService
        |
        v
AttendanceAnomaly records and history/calendar views
```

## 6. Leave Workflow

```text
Employee files leave
        |
        v
StoreLeaveRequestRequest / LeaveRequestController
        |
        v
Leave type + balance checks
        |
        v
Approval route
  - department head
  - HR
  - president when required
        |
        v
Approve / decline / revise / cancel actions
        |
        v
Leave balance and attendance history rendering
```

## 7. Recruitment Workflow

```text
Public job portal
        |
        v
Applicant submits application and documents
        |
        v
JobApplicationSubmitted event
        |
        v
SendJobApplicationNotifications listener
        |
        v
HR applicant dashboard
        |
        v
Archive / activate / complete applicant
        |
        v
RecruitmentApprovalService / RecruitmentActionService
        |
        v
Employee/user creation when completed
```

## 8. Travel Order Workflow

```text
Employee creates travel order
        |
        v
TravelOrderWorkflowService
        |
        v
Submit
        |
        v
Department approval
        |
        v
HR approval
        |
        v
Final approval
        |
        v
Approved travel appears as official business in attendance history
        |
        v
Completion / cancellation / print export
```

## 9. Offboarding Workflow

```text
Admin/authorized user creates draft offboarding record
        |
        v
Submit into workflow
        |
        v
Clearance items
  - department
  - finance
  - HR
        |
        v
Finalize and close
        |
        v
Account deactivation after last working day
        |
        v
Optional cancellation request/review/reopen paths
```

## 10. Testing and CI

```text
Local
  php artisan test
        |
        v
Pest feature/unit suites
  - attendance
  - auth/profile
  - leave approvals
  - recruitment
  - travel orders
  - offboarding
  - PDS
  - employee documents

GitHub Actions
  - test job
  - code-style job using Pint
  - static-analysis job intended for PHPStan
```

CI exists, but PHPStan should be added/configured directly before treating static analysis as production-ready.
