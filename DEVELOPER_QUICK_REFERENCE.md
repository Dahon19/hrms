# HRMS Developer Quick Reference

## File Organization & Navigation Quick Map

### Core Application Structure
```
app/
├── Http/Controllers/          ← Web request handlers
│   ├── AttendanceController.php        → Attendance logic
│   ├── EmployeeController.php          → Employee CRUD
│   ├── OffboardingController.php       → Employee separation
│   ├── SpmsController.php              → Performance reviews
│   ├── TravelOrderController.php       → Travel requests
│   ├── LeaveRequestController.php      → Leave management
│   └── [...33 other controllers]
│
├── Models/                    ← Eloquent ORM models (48 total)
│   ├── Employee.php                    → Primary employee model
│   ├── User.php                        → Authentication + profile
│   ├── Attendance.php                  → Clock in/out records
│   ├── LeaveRequest.php                → Leave request workflow
│   ├── SpmsProfile.php                 → Performance evaluations
│   ├── OffboardingRecord.php           → Separation tracking
│   ├── TravelOrder.php                 → Travel management
│   ├── JobPosting.php & Applicant.php  → Recruitment
│   ├── PdsProfile.php & Relations      → Personal data (encrypted)
│   └── [...40 more models]
│
├── Services/                  ← Business logic layer (12+ services)
│   ├── AttendanceKpiScoringService.php → KPI computation
│   ├── OffboardingWorkflowService.php  → Separation orchestration
│   ├── SpmsScoringService.php          → Performance evaluation
│   ├── RecruitmentActionService.php    → Hiring workflow
│   ├── HrmsNotificationService.php     → Notification dispatch
│   ├── DashboardService.php            → Analytics aggregation
│   └── [More services...]
│
├── Domain/                    ← Domain-driven design (DDD)
│   ├── Offboarding/                    → Separation domain logic
│   ├── Spms/                           → Performance domain logic
│   └── TravelOrders/                   → Travel domain logic
│
├── Events/                    ← Event classes
│   ├── JobApplicationSubmitted.php
│   ├── AttendanceRecorded.php
│   └── HrmsNotificationCreated.php
│
├── Listeners/                 ← Event handlers
│   └── SendJobApplicationNotifications.php
│
├── Policies/                  ← Authorization rules
│   └── [Model-based policies]
│
├── Mail/                      ← Mailable classes
│   └── PasswordResetRequestAdminMail.php
│
└── Casts/                     ← Custom Eloquent casts
    └── EncryptedValueCast.php         → Encrypts sensitive data

database/
├── migrations/                ← 100+ schema migrations
│   ├── create_employees_table.php
│   ├── create_attendance_table.php
│   ├── create_leave_requests_table.php
│   ├── create_spms_*.php               → Performance tables
│   └── [...100+ migrations in chronological order]
│
└── seeders/                   ← Database population

routes/
├── web.php                    ← User-facing web routes
├── api.php                    ← API endpoints
├── auth.php                   ← Authentication routes
└── channels.php               ← Broadcasting channels

config/
├── app.php                    ← Application configuration
├── database.php               ← DB connection settings
├── mail.php                   ← Email configuration
├── cache.php                  ← Cache driver settings
├── auth.php                   ← Authentication settings
├── services.php               ← Third-party service configs
└── rewards.php                ← Custom reward configuration

resources/
├── views/                     ← Blade templates
│   ├── layouts/
│   ├── components/
│   └── [Module-specific views]
├── js/                        ← Vue.js components
└── css/                       ← Tailwind CSS styles

tests/                         ← Pest test suites
├── Feature/                   ← Integration tests
├── Unit/                      ← Unit tests
├── TestCase.php               ← Test base class
└── Pest.php                   ← Pest configuration
```

---

## Quick Navigation Guide

### Finding a Feature

**Need to understand how Attendance works?**
- Models: `app/Models/Attendance.php`, `AttendanceKpi.php`, `AttendanceMonthlyScore.php`
- Controller: `app/Http/Controllers/AttendanceController.php`
- Service: `app/Services/AttendanceKpiScoringService.php`
- Routes: `routes/web.php` (search "attendance")
- Tests: `tests/Feature/AttendanceTest.php`

**Need to understand Leave Management?**
- Models: `app/Models/LeaveRequest.php`, `LeaveBalance.php`, `LeaveType.php`, `LeaveBalanceYearSetting.php`
- Controller: `app/Http/Controllers/LeaveRequestController.php`
- Service: Look in `DashboardService` or core controller logic
- Routes: `routes/web.php` (search "leave")
- Workflow: See `ARCHITECTURE_DIAGRAMS.md` - "Leave Request Workflow"

**Need to understand SPMS (Performance Evaluations)?**
- Models: `SpmsProfile.php`, `SpmsCycle.php`, `SpmsEvaluation.php`, `SpmsCriterion.php`
- Controller: `app/Http/Controllers/SpmsController.php`
- Service: `app/Services/SpmsScoringService.php`
- Domain: `app/Domain/Spms/`
- Routes: `routes/web.php` (search "spms")

**Need to understand Recruitment?**
- Models: `JobPosting.php`, `Applicant.php`, `RecruitmentApproval.php`, `EligibilityCache.php`
- Controller: `app/Http/Controllers/JobPostingController.php`, `RecruitmentApprovalController.php`
- Services: `RecruitmentActionService.php`, `RecruitmentApprovalService.php`
- Routes: `routes/web.php` (search "jobs" or "applicants")

**Need to understand Offboarding?**
- Models: `OffboardingRecord.php`, `ClearanceItem.php`
- Controller: `app/Http/Controllers/OffboardingController.php`
- Service: `app/Services/OffboardingWorkflowService.php`
- Domain: `app/Domain/Offboarding/`
- Routes: `routes/web.php` (search "offboarding")

---

## Model Relationships Cheat Sheet

### Common Relationships

```php
// Employee relationships
Employee::with('user', 'positions', 'department', 'documents', 'attendance')

// User relationships
User::with('employee', 'notifications', 'auditLogs')

// Attendance relationships
Attendance::with('employee', 'nfc')
AttendanceAnomaly::with('attendance')
AttendanceKpi::with('employee') // Usually for department-wide

// Leave relationships
LeaveRequest::with('employee', 'leaveType', 'approver')
LeaveBalance::with('employee', 'leaveType')

// Performance relationships
SpmsProfile::with('employee', 'cycle', 'evaluations')
SpmsEvaluation::with('profile', 'details', 'evaluator')
SpmsCriterion::with('cycle')

// Recruitment relationships
JobPosting::with('position', 'applicants', 'approvals')
Applicant::with('jobPosting', 'documents', 'approvals')

// Travel relationships
TravelOrder::with('employee', 'attachments', 'transportations', 'approvals')

// Offboarding relationships
OffboardingRecord::with('employee', 'clearanceItems', 'approvals')
ClearanceItem::with('offboarding', 'assignedTo')

// PDS relationships
PdsProfile::with('employee', 'personalInfo', 'education', 'workExperience', 'familyBackground')
```

---

## Key Service Methods Quick Reference

### AttendanceKpiScoringService
```php
// Compute monthly scores for employees
$service->computeMonthlyScores($month, $year);

// Calculate individual score
$service->calculateEmployeeScore($employee, $month);

// Get criteria-based scores
$service->getCriteriaScores($attendance);
```

### OffboardingWorkflowService
```php
// Initialize offboarding process
$service->initiate($employee);

// Route to next approval step
$service->routeForApproval($offboarding);

// Complete clearance
$service->completeClearance($offboarding);
```

### HrmsNotificationService
```php
// Send notification to user
$service->notify($user, $type, $relatedModel);

// Broadcast real-time update
$service->broadcast($channel, $event);
```

### RecruitmentActionService
```php
// Screen applicant
$service->screen($applicant, $decision);

// Route to approval
$service->routeForApproval($applicant);

// Generate offer
$service->generateOffer($applicant);
```

### SpmsScoringService
```php
// Calculate evaluation score
$service->calculateScore($evaluation);

// Validate ratings
$service->validateRatings($evaluationDetails);

// Lock evaluation
$service->lockEvaluation($profile);
```

---

## Common Patterns

### Adding a New Feature

1. **Create Model** (if needed)
   ```bash
   php artisan make:model FeatureName -m
   ```
   Edit: `app/Models/FeatureName.php` with relationships

2. **Create Migration**
   ```bash
   php artisan make:migration create_feature_table
   ```
   Edit: `database/migrations/XXXX_create_feature_table.php`

3. **Create Controller**
   ```bash
   php artisan make:controller FeatureController
   ```
   Edit: `app/Http/Controllers/FeatureController.php` with CRUD methods

4. **Create Service** (if business logic needed)
   ```php
   // Create app/Services/FeatureService.php
   ```

5. **Create Events** (if workflow needed)
   ```bash
   php artisan make:event FeatureCreated
   ```
   Edit: `app/Events/FeatureCreated.php`

6. **Create Listeners**
   ```bash
   php artisan make:listener ReactToFeatureCreated --event=FeatureCreated
   ```

7. **Add Routes**
   Edit: `routes/web.php` or `routes/api.php`

8. **Add Tests**
   ```bash
   php artisan make:test FeatureTest
   ```

### Creating a Workflow

1. **Define States**: Use enums or model status fields
2. **Create Service**: Class to orchestrate workflow
3. **Create Events**: Dispatch on state transitions
4. **Create Listeners**: Handle next steps
5. **Add Audit**: Log state changes via `AuditLogger::log()`
6. **Notify**: Use `HrmsNotificationService::notify()`

### Adding Authorization

1. **Create Policy**
   ```bash
   php artisan make:policy FeaturePolicy --model=Feature
   ```

2. **Define Methods in Policy**
   ```php
   public function view(User $user, Feature $feature) { ... }
   public function create(User $user) { ... }
   ```

3. **Use in Controller**
   ```php
   $this->authorize('view', $feature);
   ```

4. **Use in Routes**
   ```php
   Route::middleware('auth')->group(function () {
       Route::resource('features', FeatureController::class)->middleware('verified');
   });
   ```

---

## Database Query Tips

### Eager Loading (Prevent N+1 queries)
```php
// ✅ Good - Load relationships upfront
$employees = Employee::with('positions', 'department', 'attendance')->get();

// ❌ Bad - Creates N+1 queries
$employees = Employee::all();
foreach ($employees as $emp) {
    echo $emp->positions->count();
}
```

### Filtering by Status/State
```php
// Use model scopes for common queries
Employee::active()->with('attendance')->get();
LeaveRequest::pending()->approved()->get();
SpmsProfile::under_review()->get();
```

### Aggregations
```php
// Count by department
$bydept = Attendance::groupBy('employee.department_id')
    ->selectRaw('count(*) as total')
    ->get();

// Sum leave balance
$balance = LeaveBalance::where('employee_id', $id)->sum('balance');
```

### Pagination
```php
// Always paginate large result sets
$employees = Employee::with('attendance')
    ->paginate(15);

// Use in view: {{ $employees->links() }}
```

---

## Event Broadcasting

### Real-time Updates
```php
// Broadcast event to channel
broadcast(new EmployeeUpdated($employee))->toOthers();

// In Blade/JS: Listen and react
Echo.channel('employee.updated')
    .listen('EmployeeUpdated', (data) => {
        // Update UI
    });
```

### Triggering Events
```php
// In Model
event(new AttendanceRecorded($attendance));

// Listener receives event
class SendNotificationListener {
    public function handle(AttendanceRecorded $event) {
        // Send notification
    }
}
```

---

## Testing Quick Start

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/EmployeeTest.php

# Run with coverage
php artisan test --coverage

# Watch mode
php artisan test --watch
```

### API Testing Template
```php
public function test_can_create_employee()
{
    $response = $this->post('/employees', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('employees', [
        'first_name' => 'John'
    ]);
}
```

---

## Debugging Tips

### Enable Query Logging
```php
// In service/controller
DB::listen(function ($query) {
    Log::debug($query->sql, $query->bindings);
});
```

### Dump Data
```php
dd($variable);        // Dump and die
dump($variable);      // Dump without dying
Log::debug('Key', ['data' => $variable]);
```

### Test Email Sending
```php
Mail::fake();
// ... run code that sends email
Mail::assertSent(PasswordReset::class);
```

### Check Active Queries
```php
// In tinker (php artisan tinker)
>>> DB::getQueryLog()
>>> DB::enableQueryLog()
```

---

## Deployment Checklist

- [ ] Run migrations: `php artisan migrate --force`
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Build assets: `npm run build`
- [ ] Set encryption key: `php artisan key:generate`
- [ ] Configure `.env` file
- [ ] Set file permissions: `chmod -R 755 storage bootstrap/cache`
- [ ] Generate API documentation (if applicable)
- [ ] Run test suite: `php artisan test`
- [ ] Check seed data: `php artisan db:seed`

---

## Useful Artisan Commands

```bash
# Database
php artisan migrate              # Run migrations
php artisan migrate:rollback     # Rollback last migration
php artisan migrate:refresh      # Rollback and re-run
php artisan db:seed             # Run seeders
php artisan tinker              # PHP interactive shell

# Code Generation
php artisan make:model Model -m -f  # Model + migration + factory
php artisan make:controller ControllerName --model=ModelName
php artisan make:service ServiceName
php artisan make:event EventName
php artisan make:listener ListenerName --event=EventName

# Maintenance
php artisan cache:clear         # Clear cache
php artisan config:cache        # Cache configurations
php artisan view:clear          # Clear view cache
php artisan optimize            # Optimize autoloader

# Testing
php artisan test               # Run tests
php artisan test --coverage    # Generate coverage report

# Utility
php artisan route:list         # List all routes
php artisan queue:work         # Process queued jobs
php artisan schedule:work      # Run scheduled tasks
```

---

## Commonly Used Models & Their Key Methods

```php
// Employee - Main employee record
Employee::find($id)->load('positions', 'department', 'user')
Employee::whereActive(true)
$employee->hasMany('attendance')

// User - Authentication
User::whereEmail($email)->first()
Auth::user()->load('employee')

// Attendance - Daily records
Attendance::where('date', $date)->where('employee_id', $empId)
$attendance->whereNfc($rfidCode)

// LeaveRequest - Leave workflow
LeaveRequest::whereStatus('pending')->whereDepartment($deptId)
$leaveRequest->approve()    // Custom method

// SpmsProfile - Performance evaluation
SpmsProfile::whereCycleId($id)->load('evaluations')
$profile->calculateFinalScore()

// OffboardingRecord - Employee separation
OffboardingRecord::whereStatus('pending')->load('clearanceItems')
```

---

## System Constants & Enums

### Status Values
```php
// Attendance Anomaly Status
'present', 'absent', 'late', 'early_out'

// Leave Request Status
'pending', 'approved', 'rejected', 'cancelled'

// SPMS Status
'pending_self_assessment', 'employee_submitted', 'manager_reviewed', 
'director_approved', 'finalized'

// Offboarding Status
'pending', 'submitted', 'approved', 'finalized', 'cancelled'

// Travel Order Status
'pending', 'approved', 'rejected', 'completed'
```

---

## External Services Integration

### Pusher/Reverb (Real-time)
```php
// Broadcasting events
broadcast(new NotificationSent($user, $message))->toOthers();
```

### Google Gemini (AI)
```php
// Access via GeminiService
$service = new GeminiService();
$response = $service->analyze($data);
```

### DOMPDF (PDF Generation)
```php
// Generate PDF reports
$pdf = PDF::loadView('reports.spms', $data);
return $pdf->download('spms_report.pdf');
```

### Guzzle HTTP (API Calls)
```php
// Make external API requests
$client = new Client();
$response = $client->get('https://api.example.com/data');
```

---

This quick reference should help you navigate and extend the HRMS system efficiently. Refer to `SYSTEM_OVERVIEW.md` for comprehensive documentation and `ARCHITECTURE_DIAGRAMS.md` for visual workflow diagrams.
