# HRMS Architecture Diagrams

## 1. System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND LAYER                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │  Vue.js SPA  │  │ Blade Views  │  │  Dashboard   │           │
│  │  Components  │  │  Templates   │  │  Components  │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
│                                                                  │
│            Vite • TailwindCSS • Pusher WebSocket               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      ROUTING LAYER                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │  Web Routes  │  │  API Routes  │  │  Auth Routes │           │
│  │  (web.php)   │  │  (api.php)   │  │  (auth.php)  │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   CONTROLLER LAYER (34)                         │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│  │ Employee│ │ Attend. │ │  Leave  │ │ Perf.   │ │ Travel  │   │
│  │ Ctrl    │ │ Ctrl    │ │ Ctrl    │ │ Ctrl    │ │ Ctrl    │   │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘   │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐              │
│  │ Recruit │ │ Offbd.  │ │Dashboard│ │ Notif.  │              │
│  │ Ctrl    │ │ Ctrl    │ │ Ctrl    │ │ Ctrl    │              │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘              │
│                                                                  │
│  Middleware: Auth, Role-based Access, Audit Logging            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                  SERVICE LAYER (12+ Services)                   │
│  ┌──────────────────────┐  ┌──────────────────────┐             │
│  │  Attendance Services │  │  Performance Services│             │
│  │• AttendanceKpiScoring│  │• SpmsScoringService │             │
│  │• AttendancePolicy    │  │• RewardEligibility  │             │
│  │• AttendanceCalendar  │  │• IdpService         │             │
│  └──────────────────────┘  └──────────────────────┘             │
│  ┌──────────────────────┐  ┌──────────────────────┐             │
│  │  Workflow Services   │  │  Support Services   │             │
│  │• OffboardingWorkflow │  │• AccessControl      │             │
│  │• RecruitmentAction   │  │• AuditLogger        │             │
│  │• ReportExport        │  │• HrmsNotification   │             │
│  └──────────────────────┘  └──────────────────────┘             │
│  ┌──────────────────────────────────────────────┐               │
│  │  Integration Services                        │               │
│  │• GeminiService (AI)  • DashboardService      │               │
│  └──────────────────────────────────────────────┘               │
└─────────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────┴──────────┐
                    │                    │
                    ▼                    ▼
┌──────────────────────────────────┐ ┌──────────────────────────────┐
│      ELOQUENT MODELS (48)        │ │    EVENT SYSTEM              │
│                                  │ │                              │
│ Employee Hierarchy:              │ │ Events:                      │
│  • Employee                      │ │  • JobApplicationSubmitted   │
│  • Position                      │ │  • AttendanceRecorded        │
│  • Department                    │ │  • HrmsNotificationCreated   │
│  • EmployeePosition              │ │  • OffboardingInitiated      │
│  • EmployeeDocument              │ │                              │
│  • EmployeeNfc                   │ │ Listeners:                   │
│                                  │ │  • SendNotifications         │
│ Attendance:                      │ │  • UpdateKpis                │
│  • Attendance                    │ │  • Audit Logging             │
│  • AttendanceAnomaly             │ │  • Cache Invalidation        │
│  • AttendanceKpi                 │ │                              │
│  • AttendanceMonthlyScore        │ │                              │
│  • Holiday                       │ │                              │
│                                  │ │                              │
│ Leave Management:                │ │                              │
│  • LeaveRequest                  │ │                              │
│  • LeaveBalance                  │ │                              │
│  • LeaveType                     │ │                              │
│  • LeaveBalanceYearSetting       │ │                              │
│                                  │ │                              │
│ Performance:                     │ │                              │
│  • SpmsProfile                   │ │                              │
│  • SpmsCycle                     │ │                              │
│  • SpmsEvaluation                │ │                              │
│  • PerformanceReview             │ │                              │
│                                  │ │                              │
│ Recruitment:                     │ │                              │
│  • JobPosting                    │ │                              │
│  • Applicant                     │ │                              │
│  • RecruitmentApproval           │ │                              │
│  • EligibilityCache              │ │                              │
│                                  │ │                              │
│ Special Domains:                 │ │                              │
│  • PdsProfile + Relations        │ │                              │
│  • TravelOrder + Relations       │ │                              │
│  • OffboardingRecord             │ │                              │
│  • Document + Categories         │ │                              │
│  • Notification                  │ │                              │
│  • AuditLog                      │ │                              │
│  • RewardRecord                  │ │                              │
│  • IndividualDevelopmentPlan     │ │                              │
│                                  │ │                              │
│ + 48 Total Eloquent Models       │ │                              │
└──────────────────────────────────┘ └──────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────────────────────────────┐
│                    DATABASE LAYER (90+ Tables)                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │   Employee   │  │  Attendance  │  │  Leave       │            │
│  │   Tables     │  │   Tables     │  │  Tables      │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │ Performance  │  │  Recruitment │  │  Travel/     │            │
│  │ Tables       │  │  Tables      │  │  Offboard    │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │ Organization │  │  Documents   │  │  Auditing    │            │
│  │ Tables       │  │  Tables      │  │  Tables      │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
│                                                                   │
│ Features: Soft Deletes • Encryption • Indexes • Relationships  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 2. Major Workflow: Employee Recruitment to Onboarding

```
┌────────────────────────────────────────────────────────────────┐
│                    RECRUITMENT WORKFLOW                         │
│                                                                 │
│  Step 1: Job Posting Creation                                 │
│  ┌─────────────────────────────────────┐                      │
│  │ HR Creates Job Posting              │                      │
│  │ - Position Selection                │                      │
│  │ - Required Headcount                │                      │
│  │ - Job Description                   │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │ (JobPosting Model Created)                   │
│                 ▼                                              │
│  Step 2: Application Portal                                   │
│  ┌─────────────────────────────────────┐                      │
│  │ Applicants Submit Applications      │                      │
│  │ - Personal Info                     │                      │
│  │ - Work Experience                   │                      │
│  │ - Documents                         │                      │
│  │ Event: JobApplicationSubmitted      │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │ (Applicant Model Created)                    │
│                 │ (Event Triggered)                            │
│                 ▼                                              │
│  Step 3: Screening                                            │
│  ┌─────────────────────────────────────┐                      │
│  │ HR Screens Candidates               │                      │
│  │ - Review Documents                  │                      │
│  │ - Initial Qualification Check       │                      │
│  │ - Update Eligibility Cache          │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │                                              │
│                 ▼                                              │
│  Step 4: Manager Assessment                                   │
│  ┌─────────────────────────────────────┐                      │
│  │ Department Manager Reviews          │                      │
│  │ - Technical Skills Match            │                      │
│  │ - Interview Feedback                │                      │
│  │ - Recommendation                    │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │                                              │
│                 ▼                                              │
│  Step 5: HR Approval                                          │
│  ┌─────────────────────────────────────┐                      │
│  │ RecruitmentApprovalService Actions  │                      │
│  │ - Background Check Review           │                      │
│  │ - Final Approval/Rejection          │                      │
│  │ - Approval Workflow Tracking        │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │ (RecruitmentApproval)                        │
│                 ▼                                              │
│  Step 6: Offer & Onboarding                                   │
│  ┌─────────────────────────────────────┐                      │
│  │ Create Employee & User Account      │                      │
│  │ - Issue Offer Letter (DOMPDF)       │                      │
│  │ - Generate Employee ID              │                      │
│  │ - Set Initial Position              │                      │
│  │ - Assign Department                 │                      │
│  │ - Create Audit Log Entry            │                      │
│  └──────────────┬──────────────────────┘                      │
│                 │ (Employee Model Created)                     │
│                 │ (User Model Created)                         │
│                 ▼                                              │
│  ✅ Employee Ready in HRMS System                             │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## 3. Attendance & Performance KPI Cycle

```
┌────────────────────────────────────────────────────────────────┐
│                  ATTENDANCE WORKFLOW                            │
│                                                                 │
│  Daily Operations:                                             │
│                                                                 │
│  Morning        ┌──────────────────────────────┐             │
│  ──────────────→│ Employee Clocks In (NFC/RFID)│             │
│                 └──────────────┬──────────────┘             │
│                                │                             │
│                                ▼                             │
│              ┌────────────────────────────────┐              │
│              │ Attendance Record Created      │              │
│              │ AttendanceRecorded Event       │              │
│              │ Triggers: Listeners            │              │
│              └────────────┬─────────────────┘              │
│                           │                                │
│          ┌────────────────┼────────────────┐              │
│          ▼                ▼                ▼              │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐     │
│  │ Validate     │ │ Detect       │ │ Update       │     │
│  │ Attendance   │ │ Anomalies    │ │ KPI Scores   │     │
│  │ Policy       │ │ (Late/Early) │ │              │     │
│  └──────────────┘ └──────────────┘ └──────────────┘     │
│                                                             │
│  Evening        ┌──────────────────────────────┐           │
│  ──────────────→│ Employee Clocks Out          │           │
│                 └──────────────┬──────────────┘           │
│                                │                           │
│                                ▼                           │
│              ┌────────────────────────────────┐            │
│              │ Update Duration & Status      │            │
│              │ Calculate Session Time         │            │
│              └────────────────────────────────┘            │
│                                                             │
│  ┌────────────────────────────────────────────────┐       │
│  │ MONTHLY KPI COMPUTATION                        │       │
│  │ (Triggered by AttendanceKpiController::compute)│       │
│  │                                                 │       │
│  │ Process:                                       │       │
│  │ 1. Aggregate monthly attendance records        │       │
│  │ 2. Run AttendanceKpiScoringService             │       │
│  │ 3. Calculate scores per criterion              │       │
│  │ 4. Generate AttendanceMonthlyScore records     │       │
│  │ 5. Lock scores for auditing                    │       │
│  │ 6. Update DepartmentMetrics aggregates         │       │
│  │ 7. Cache results for dashboard                 │       │
│  │                                                 │       │
│  │ Output: Monthly scores available for           │       │
│  │ performance reviews, bonus calculations         │       │
│  └────────────────────────────────────────────────┘       │
│                                                             │
└────────────────────────────────────────────────────────────────┘
```

---

## 4. Leave Request Workflow

```
┌────────────────────────────────────────────────────────────────┐
│                 LEAVE REQUEST WORKFLOW                          │
│                                                                 │
│  Employee Submits Request:                                     │
│  ┌─────────────────────────────────┐                          │
│  │ LeaveRequest Created            │                          │
│  │ • Select Leave Type             │                          │
│  │ • Choose Dates                  │                          │
│  │ • Status: pending               │                          │
│  └──────────────┬──────────────────┘                          │
│                 │                                              │
│                 ▼                                              │
│  Service Validation:                                          │
│  ┌──────────────────────────────────────────┐               │
│  │ LeaveBalanceService Checks:              │               │
│  │ ✓ Sufficient balance available           │               │
│  │ ✓ Leave type valid for employee gender   │               │
│  │ ✓ Within max_days limit                  │               │
│  │ ✓ Not overlapping existing requests      │               │
│  └──────────────┬───────────────────────────┘               │
│                 │                                            │
│         ┌───────┴───────┐                                    │
│         │               │                                    │
│    ✅ Valid        ❌ Invalid                                │
│         │               │                                    │
│         ▼               ▼                                    │
│  ┌────────────────┐ ┌─────────────┐                         │
│  │ Routing to     │ │ Rejection   │                         │
│  │ Manager        │ │ Notice sent │                         │
│  │ Status:pending │ │             │                         │
│  └────────────────┘ └─────────────┘                         │
│         │                                                    │
│         ▼                                                    │
│  Manager Review:                                            │
│  ┌──────────────────────────────────────┐                  │
│  │ Manager Approves/Rejects             │                  │
│  │ • Check workload impact              │                  │
│  │ • Approve or request reschedule      │                  │
│  │ • Add comments                       │                  │
│  │ Status: approved or rejected         │                  │
│  └──────────────┬───────────────────────┘                  │
│                 │                                           │
│         ┌───────┴───────────┐                              │
│    ✅ Approved        ❌ Rejected                           │
│         │                   │                              │
│         ▼                   ▼                              │
│  ┌─────────────────────┐ ┌──────────────┐                 │
│  │ President Review?   │ │ Rejection    │                 │
│  │ (if configured)     │ │ Notification │                 │
│  │ Status: requiring   │ │ Sent         │                 │
│  │ President approval  │ └──────────────┘                 │
│  └─────────────────────┘                                  │
│         │                                                 │
│         ▼                                                 │
│  ┌──────────────────────────────────┐                    │
│  │ President Approves/Rejects       │                    │
│  │ Status: approved or rejected      │                    │
│  └──────────────┬───────────────────┘                    │
│                 │                                         │
│        ✅ Approved                                        │
│                 │                                         │
│                 ▼                                         │
│  ┌──────────────────────────────────┐                    │
│  │ Automatically Update LeaveBalance │                    │
│  │ • Deduct days from balance        │                    │
│  │ • Create balance adjustment record│                    │
│  │ • Send approval notification      │                    │
│  │ • Mark attendance as leave        │                    │
│  │ • For those days                  │                    │
│  └──────────────────────────────────┘                    │
│                                                            │
│  Annual Reset (via LeaveBalanceYearSetting):               │
│  ┌──────────────────────────────────┐                    │
│  │ Year-end Process:                │                    │
│  │ • Check eligibility_months config │                    │
│  │ • Create new LeaveBalance entries │                    │
│  │ • Unused carry-over logic         │                    │
│  └──────────────────────────────────┘                    │
│                                                            │
└────────────────────────────────────────────────────────────────┘
```

---

## 5. Performance Management (SPMS) Workflow

```
┌────────────────────────────────────────────────────────────────┐
│            SIMPLIFIED PERFORMANCE MANAGEMENT (SPMS)             │
│                                                                 │
│  Cycle Management:                                             │
│  ┌──────────────────────────────────────────┐                │
│  │ SpmsCycle Created (HR)                   │                │
│  │ • Set evaluation period                  │                │
│  │ • Define deadline dates                  │                │
│  │ • Assign evaluators/roles                │                │
│  │ • Create SpmsCriteria                    │                │
│  │ • Status: active                         │                │
│  └──────────────┬───────────────────────────┘                │
│                 │ (SpmsCycleListener)                         │
│                 ▼                                             │
│  Employee Setup:                                              │
│  ┌──────────────────────────────────────────┐                │
│  │ SpmsProfile Created by HR                │                │
│  │ • Link to SpmsCycle                      │                │
│  │ • Link to Employee                       │                │
│  │ • Set target/actual rating scales        │                │
│  │ • Status: pending_self_assessment        │                │
│  └──────────────┬───────────────────────────┘                │
│                 │                                             │
│                 ▼                                             │
│  Step 1: Employee Self-Assessment                            │
│  ┌──────────────────────────────────────────┐                │
│  │ Employee Completes Form:                 │                │
│  │ • Reflects on accomplishments            │                │
│  │ • Rates self-performance                 │                │
│  │ • Comments on challenges/growth areas    │                │
│  │                                          │                │
│  │ Data stored in:                          │                │
│  │ • SpmsEvaluation (employee entry)        │                │
│  │ • SpmsEvaluationDetails (line items)     │                │
│  │ • Status: employee_submitted             │                │
│  └──────────────┬───────────────────────────┘                │
│                 │                                             │
│                 ▼                                             │
│  Step 2: Manager Evaluation                                  │
│  ┌──────────────────────────────────────────┐                │
│  │ Manager Reviews Performance:             │                │
│  │ • Reviews SPM criteria (SpmsCriteria)    │                │
│  │ • Scores against each criterion          │                │
│  │ • Adds detailed comments                 │                │
│  │ • Identifies strengths & development     │                │
│  │                                          │                │
│  │ SpmsScoringService:                      │                │
│  │ • Calculates weighted scores             │                │
│  │ • Validates rating ranges                │                │
│  │ • Status: manager_reviewed               │                │
│  └──────────────┬───────────────────────────┘                │
│                 │                                             │
│                 ▼                                             │
│  Step 3: Director Review & Approval                          │
│  ┌──────────────────────────────────────────┐                │
│  │ Director:                                │                │
│  │ • Reviews manager assessment             │                │
│  │ • Checks for consistency/fairness        │                │
│  │ • May adjust scores with justification   │                │
│  │ • Approves or sends back for revision    │                │
│  │ • Status: director_approved              │                │
│  └──────────────┬───────────────────────────┘                │
│                 │                                             │
│                 ▼                                             │
│  Step 4: HR Finalization                                     │
│  ┌──────────────────────────────────────────┐                │
│  │ HR Finalizes:                            │                │
│  │ • Lock evaluation for audit trail        │                │
│  │ • Generate performance report (DOMPDF)   │                │
│  │ • Archive evaluation                     │                │
│  │ • Status: finalized                      │                │
│  │                                          │                │
│  │ Post-process:                            │                │
│  │ • Eligibility recalculation via IDP      │                │
│  │ • Reward eligibility determination       │                │
│  │ • Bonus calculation inputs               │                │
│  │ • Send notifications via event           │                │
│  └──────────────────────────────────────────┘                │
│                                                                │
│  Output: Comprehensive performance record                    │
│  locked for audit, used for decisions                        │
│  (promotions, bonuses, development plans)                    │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## 6. Data Flow: Request → Response

```
┌──────────────────────────────────────────────────────────────┐
│              TYPICAL REQUEST-RESPONSE FLOW                    │
│                                                              │
│  1. Client Request:                                         │
│     POST /employees/5/update                                │
│     {employee_data}                                         │
│     ↓                                                        │
│  2. Routing:                                                │
│     → routes/web.php matches route                          │
│     → Determines controller + action                        │
│     ↓                                                        │
│  3. Middleware Pipeline:                                    │
│     → Auth::check() - verify user logged in                 │
│     → VerifyCsrfToken                                       │
│     → CheckRole (custom) - verify permissions              │
│     → TrustHosts, ShareErrorsFromSession                    │
│     ↓ ✓ Middleware passes if authorized                    │
│     ↓ ✗ 403 Forbidden if not                               │
│  4. Controller Action:                                      │
│     EmployeeController::update(id, request)                 │
│     ↓                                                        │
│  5. Service Layer Call:                                     │
│     $service = new EmployeeService();                       │
│     $result = $service->updateEmployee($id, $data);         │
│     ↓                                                        │
│  6. Model Interaction:                                      │
│     Employee::findOrFail($id)                               │
│     $employee->update($validated_data)                      │
│     Triggers Model Events:                                  │
│       • updating()                                          │
│       • updated()                                           │
│     ↓                                                        │
│  7. Event Broadcasting:                                     │
│     Dispatch custom event (e.g., EmployeeUpdated)          │
│     → Push to Pusher/Reverb channels                        │
│     → Notify other clients                                  │
│     ↓                                                        │
│  8. Audit Logging:                                          │
│     AuditLogger::log('Update', 'Employee', $id, $changes)  │
│     Creates AuditLog record in DB                          │
│     ↓                                                        │
│  9. Response Generation:                                    │
│     • If JSON API: return json response                    │
│     • If HTML: return view with data                       │
│     • Status code 200, 422 (validation), 403, etc.         │
│     ↓                                                        │
│  10. Client Receives:                                       │
│     Response with updated data or error messages            │
│     Front-end updates via Vue.js                           │
│     WebSocket receives real-time updates                   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

## 7. Module Dependency Graph

```
CORE DEPENDENCIES:
└── User/Authentication
    ├── Employee Management
    │   ├── Attendance System
    │   │   ├── KPI Scoring
    │   │   └── Department Metrics
    │   ├── Leave Management
    │   ├── Performance (SPMS)
    │   │   ├── IDP
    │   │   └── Rewards
    │   ├── Recruitment
    │   │   └── Eligibility Cache
    │   ├── Travel Orders
    │   └── Offboarding
    ├── Personal Data (PDS)
    │   └── Encryption Service
    ├── Document Management
    │   └── Categories/Subcategories
    └── Administration
        ├── Audit Logging
        └── Notifications
            └── Event Broadcasting
```

These diagrams provide visual context for understanding the HRMS architecture, workflows, and data flows.
