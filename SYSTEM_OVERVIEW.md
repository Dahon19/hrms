# HRMS System Overview

## Project Overview

**HumanResource Management System (HRMS)** is a comprehensive Laravel-based web application designed to manage all aspects of organizational human resources operations. The system is built with **Laravel 12** and implements a modern architecture with domain-driven design patterns, event-driven workflows, and real-time capabilities.

**Technology Stack:**
- **Backend Framework**: Laravel 12
- **PHP Version**: 8.2+
- **Frontend**: Vue.js with Vite
- **Real-time Communication**: Pusher + Laravel Reverb
- **PDF Generation**: DOMPDF
- **Testing**: Pest PHP 3.8+
- **Database**: MySQL/PostgreSQL (migrations-based schema)
- **Task Queue**: Redis (Laravel Queue)
- **File Generation**: Report exports with DomPDF

---

## System Architecture

### 1. Core Modules & Feature Domains

The application is organized into several major functional domains:

#### **A. Human Resource Management**
- **Employee Management** - Employee records, profiles, documents, and lifecycle
- **Attendance Tracking** - RFID/NFC-based attendance recording, history, anomaly detection
- **Leave Management** - Leave requests, balance tracking, leave types with gender/date constraints
- **Performance Management** - SPMS (Simplified Performance Management System) with evaluation cycles
- **Recruitment** - Job postings, applicant tracking, recruitment approvals
- **Offboarding** - Employee separation workflow with clearance management

#### **B.  Administrative Functions**
- **Department Management** - Organizational structure with department types
- **Position Management** - Job positions scoped to departments with employee limits
- **Document Management** - Employee documents with expiry tracking and categorization
- **Audit Logging** - System-wide audit trail for compliance and security
- **User Access Control** - Role-based authentication and permissions

#### **C. Specialized Systems**
- **Travel Orders** - Business travel management with transportation options
- **Personal Data Sheet (PDS)** - Government compliance forms with encrypted sensitive data

#### **D. Analytics & Reporting**
- **Dashboard** - Interactive KPI metrics, activity summaries, department analytics
- **Attendance KPI** - Scoring, computation, and monthly performance metrics
- **Department Metrics** - Performance tracking per department
- **Report Generation** - Export functionality with DOMPDF

#### **E. Real-time Communication**
- **Notifications** - User notifications with read status tracking
- **Event Broadcasting** - Real-time updates via Pusher/Reverb

---

## Data Model Architecture

### Core Entities

```
Users/Authentication
├── User (Authentication + Profile)
└── Role-based Authorization (Policies & Middleware)

Employee Management
├── Employee (Main employee record)
├── EmployeePosition (Position history)
├── EmployeeDocument (Document tracking)
├── EmployeeNfc (RFID/NFC card management)
└── Position (Job positions, dept-scoped)

Organizational Structure
├── Department (Departments with types)
├── DepartmentType (Enum: ACADEMIC, ADMINISTRATIVE, etc.)
├── DepartmentMetric (Performance metrics)
└── Position (Job classification)

Attendance & KPI
├── Attendance (Clock in/out records)
├── AttendanceAnomaly (Irregular patterns)
├── AttendanceSetting (Attendance configuration)
└── Holiday (Public holidays calendar)

Leave Management
├── LeaveType (Leave classifications)
├── LeaveRequest (Time-off requests)
├── LeaveBalance (Current balance tracking)
└── LeaveBalanceYearSetting (Year configurations)

Recruitment
├── JobPosting (Job openings)
├── Applicant (Job candidates)
├── RecruitmentApproval (Approval workflow)
└── EligibilityCache (Candidate eligibility cache)

Personal Data
├── PdsProfile (Government compliance forms)
├── PdsPersonalInfo, PdsEducation, PdsFamilyBackground
├── PdsWorkExperience, PdsTraining, PdsVoluntaryWork
└── PdsCivilServiceEligibility

Travel & Offboarding
├── TravelOrder (Business travel requests)
├── TravelOrderAttachment (Supporting documents)
├── TravelOrderTransportation (Transportation options)
├── OffboardingRecord (Separation workflow)
└── ClearanceItem (Clearance checklist)

Other
├── Document, DocumentCategory, DocumentSubcategory
├── AuditLog (System audit trail)
├── Notification (User notifications)
├── ReportRun (Report execution tracking)
└── User (Authentication)
```

### Database Characteristics
- **70+ tables** with proper indexing for performance
- **Encrypted sensitive fields** (PDS data uses EncryptedValueCast)
- **Soft deletes** for data retention and audit trail
- **Polymorphic relationships** for flexible attachments
- **Composite indexes** for complex queries (workflow, search)

---

## Service Layer

### Business Logic Services

| Service | Purpose |
|---------|---------|
| `AccessControl` | Role-based access control and permission management |
| `AuditLogger` | Centralized audit logging for compliance tracking |
| `AttendanceCalendarService` | Holiday management and calendar operations |
| `AttendancePolicyService` | Attendance validation and policy enforcement |
| `DashboardService` | Dashboard metrics aggregation |
| `DashboardMetricsService` | Real-time metric calculations |
| `DashboardActivityService` | Activity feed and recent events |
| `DepartmentMetricsService` | Department-level analytics |
| `HrmsNotificationService` | Notification creation and management |
| `OffboardingWorkflowService` | Separation process orchestration |
| `RecruitmentActionService` | Recruitment workflow operations |
| `RecruitmentApprovalService` | Approval chain management |
| `ReportExportService` | Report generation and export |
| `GeminiService` | AI-powered features (Google Gemini integration) |

---

## API Endpoints & Routes

### Authentication Routes
- POST `/login` - User authentication
- POST `/logout` - Session termination
- POST `/forgot-password` - Password recovery

### Core CRUD Operations
```
GET    /employees              - List employees
POST   /employees              - Create employee
GET    /employees/{id}         - View employee
PUT    /employees/{id}         - Update employee
DELETE /employees/{id}         - Delete employee

GET    /departments            - List departments
POST   /departments            - Create department
GET    /departments/{id}       - View department
PUT    /departments/{id}       - Update department

GET    /positions              - List positions
POST   /positions              - Create position
GET    /positions/{id}         - View position
PUT    /positions/{id}         - Update position
```

### Attendance Management
```
GET    /attendance             - List records
POST   /attendance             - Clock in/out
GET    /attendance/live        - Real-time data
GET    /attendance/history     - Historical records
POST   /attendance/history/print - Generate report
GET    /attendance/weekly      - Weekly summary
GET    /attendance/calendar    - Calendar view
```

### Leave Management
```
GET    /leave-requests         - List leave requests
POST   /leave-requests         - Create request
PUT    /leave-requests/{id}    - Update request (status changes)
GET    /leave-balance          - View balance
POST   /leave-balance/reset    - Annual reset
```

### Recruitment
```
GET    /jobs                   - Job postings portal
POST   /jobs/{id}/apply        - Job application
GET    /applicants             - List candidates
POST   /applicants/{id}/approve - Approve candidate
```

### Travel Orders
```
GET    /travel-orders          - List travel orders
POST   /travel-orders          - Create order
GET    /travel-orders/{id}     - View order
POST   /travel-orders/{id}/submit - Submit for approval
GET    /travel-orders/approvals - Approval queue
```

### Offboarding
```
GET    /offboarding            - List separations
POST   /offboarding            - Initiate separation
GET    /offboarding/{id}       - View details
POST   /offboarding/{id}/submit - Submit clearance
POST   /offboarding/{id}/finalize - Complete process
```

### Dashboard & Notifications
```
GET    /dashboard              - Dashboard metrics
GET    /notifications          - User notifications
POST   /notifications/{id}/read - Mark as read
POST   /notifications/read-all - Mark all read
```

---

## Event-Driven Architecture

### Key Events

| Event | Triggered By | Listeners |
|-------|------------|-----------|
| `JobApplicationSubmitted` | Job application creation | Email notifications, audit logging |
| `AttendanceRecorded` | Clock in/out | KPI updates, anomaly detection |
| `HrmsNotificationCreated` | System events | Push notifications, email |

### Event Listeners
- `SendJobApplicationNotifications` - Email notifications on job applications
- Custom listeners for audit logging, cache invalidation, and real-time updates

---

## Business Workflows

### 1. **Attendance Workflow**
```
Employee Clocks In/Out 
  → AttendanceRecorded Event 
  → Validate against policy 
  → Detect anomalies 
  → Update KPI scores 
  → Calculate monthly metrics
```

### 2. **Leave Request Workflow**
```
Employee Submits Leave Request 
  → Check balance eligibility 
  → Route to manager 
  → Manager approves/rejects 
  → President review (if needed) 
  → Update leave balance
```

### 3. **Recruitment Workflow**
```
Job Posting Created 
  → Applicants submit applications 
  → HR screens candidates 
  → Shortlisted for interview 
  → Manager assessment 
  → HR approval 
  → Offer generated 
  → Onboarding initiated
```

### 4. **Offboarding Workflow**
```
Separation Initiated 
  → Generate clearance checklist 
  → Departments complete tasks 
  → Employee submits documents 
  → IT revokes access 
  → Final approval 
  → Record archived
```

### 5. **Travel Order Workflow**
```
Employee Requests Travel 
  → Department head approves 
  → HR verifies budget 
  → Travel arrangements confirmed 
  → Post-travel report due 
  → Finance settlement
```

---

## Key Features

### Real-time Capabilities
- **Live Attendance Dashboard** - Real-time clock in/out updates via WebSocket
- **Notification System** - Instant user alerts and reminders
- **Activity Feed** - Live system event broadcasting

### Security & Compliance
- **Encrypted PDS Data** - Sensitive employee data encrypted at rest
- **Audit Logging** - Complete system audit trail with user attribution
- **Role-Based Access Control** - Granular permission system via Policies
- **Soft Deletes** - Data retention for compliance

### Data Management
- **Document Expiry Tracking** - Automatic alerts for expiring credentials/documents
- **Employee Lifecycle** - Hire date to offboarding tracking
- **Search Optimization** - Indexed search for quick employee lookup
- **Report Export** - PDF generation with DOMPDF

### Integration Capabilities
- **NFC/RFID Card Assignment** - Hardware integration for attendance
- **Google Gemini AI** - AI-powered features (assistant/analysis)
- **Third-party APIs** - Via Guzzle HTTP client
- **Event Broadcasting** - Pusher integration for real-time features

---

## Controllers (37 main controllers)

| Category | Controllers |
|----------|-------------|
| **Attendance** | AttendanceController, AttendanceCalendarController, AttendanceSettingsController, AttendanceKpiController |
| **Employee** | EmployeeController, EmployeeSearchController, EmployeeNfcController, EmployeeDocumentController |
| **Organizations** | DepartmentController, DepartmentTypeController, PositionController |
| **Leave** | LeaveRequestController, LeaveTypeController, LeaveBalanceController |
| **Recruitment** | JobPostingController, RecruitmentApprovalController |
| **Travel** | TravelOrderController, TravelOrderApprovalController, TravelOrderTransportationController |
| **Offboarding** | OffboardingController |
| **Administration** | DocumentController, AuditLogController, UserController |
| **Analytics** | DashboardController, ReportController |
| **Other** | NotificationController, PdsController, ProfileController, AIController |

---

## Testing Framework

- **Pest PHP 3.8+** - Modern PHP testing framework
- **Test Structure**: `/tests/Feature/` and `/tests/Unit/`
- **Pest Laravel Plugin** - Laravel-specific assertions and helpers
- **Mockery** - Object mocking framework

---

## Domain-Driven Design Model

The application uses DDD principles with specialized domain packages:

```
App\Domain\
├── Offboarding/
│   └── OffboardingWorkflowService
└── TravelOrders/
    ├── TravelOrderWorkflowService
    └── TravelOrderAttendanceService
```

---

## Development Workflow

### Build & Development Tools
- **Vite** - Fast JavaScript bundler for frontend
- **Tailwind CSS** - Utility-first CSS framework
- **Composer** - PHP dependency management
- **npm/Node.js** - JavaScript dependency management
- **PHPUnit** - Testing framework configured via phpunit.xml

### NPM Scripts (via package.json)
- Development server with hot reload
- Production build optimization
- Code formatting with Pint

### Artisan Commands
- Database migrations
- Cache management
- Queue processing
- Custom commands for HRMS operations

---

## Middleware

| Middleware | Purpose |
|-----------|---------|
| `RequireDeviceToken` | Device token validation for API access |
| `Role` | Role-based request filtering |
| `SecurityHeaders` | HTTP security headers |

---

## Policies

| Policy | Purpose |
|--------|---------|
| `OffboardingRecordPolicy` | Authorization for offboarding records |
| `PdsProfilePolicy` | Authorization for PDS access |
| `TravelOrderPolicy` | Authorization for travel orders |

---

## File Structure Summary

```
hrms/
├── app/                          # Application code
│   ├── Casts/                   # Eloquent casts (EncryptedValueCast)
│   ├── Console/                 # Artisan commands
│   ├── Domain/                  # DDD domains (Offboarding, TravelOrders)
│   ├── Events/                  # System events
│   ├── Http/                    # Controllers, middleware, requests
│   ├── Listeners/               # Event listeners
│   ├── Mail/                    # Mailable classes
│   ├── Models/                  # Eloquent models (39 models)
│   ├── Observers/               # Model observers
│   ├── Policies/                # Authorization policies
│   ├── Providers/               # Service providers
│   ├── Services/                # Business logic (12+ services)
│   └── Support/                 # Helper utilities
├── database/
│   ├── migrations/              # 76+ schema migrations
│   ├── seeders/                 # Database seeders
│   └── factories/               # Model factories
├── routes/
│   ├── web.php                  # Web routes
│   ├── api.php                  # API routes
│   ├── auth.php                 # Auth routes
│   └── channels.php             # Broadcasting channels
├── resources/
│   ├── views/                   # Blade templates
│   ├── css/                     # Tailwind styles
│   └── js/                      # Vue.js components
├── tests/                        # Pest test suites
├── config/                       # Configuration files
├── storage/                      # Logs, uploads, cache
└── public/                       # Web-accessible files
```

---

## Database Schema Highlights

### Key Relationships

1. **Polymorphic**: Attachments/Documents can belong to multiple entity types
2. **Many-to-Many**: Employees have multiple positions, departments have multiple types
3. **JSON Columns**: Flexible metadata (workflow status, approval chain)
4. **Encrypted Columns**: PDS sensitive data encrypted using Laravel encryption
5. **Soft Deletes**: Retention and audit trail support

### Performance Optimizations
- Strategic foreign key indexes
- Composite indexes for complex queries
- Search indexes on `name`, `email`, `id_number`
- Workflow state query optimization

---

## Configuration Files

| File | Purpose |
|------|---------|
| `config/app.php` | Application settings, providers |
| `config/database.php` | Database connection settings |
| `config/mail.php` | Mail server configuration |
| `config/queue.php` | Job queue configuration |
| `config/session.php` | Session settings |
| `config/auth.php` | Authentication configuration |
| `config/cache.php` | Cache driver settings |
| `config/services.php` | Third-party service configs |
| `tailwind.config.js` | Tailwind CSS configuration |
| `vite.config.js` | Vite bundler configuration |

---

## Deployment Architecture

### Environment Setup
- **Local Development**: `php artisan serve`
- **Production**: Apache/Nginx with PHP-FPM
- **Queue Processing**: Redis-backed queue workers
- **Real-time**: Pusher/Reverb server for WebSocket connections
- **Storage**: Local disk + cloud storage support (filesystems.php)

---

## Security Considerations

1. **Authentication**: Laravel Sanctum (API) + Session-based (Web)
2. **Authorization**: Policies for fine-grained permissions
3. **Data Encryption**: Laravel encryption for sensitive PDS fields
4. **Audit Trail**: Complete audit logging via AuditLogger service
5. **CSRF Protection**: Built-in Laravel middleware
6. **Rate Limiting**: Laravel rate limiter for API endpoints
7. **Middleware**: Custom middleware in `app/Http/Middleware/`

---

## Performance & Scaling

### Optimization Strategies
- **Caching**: Laravel cache system with Redis
- **Query Optimization**: Eager loading, query scopes
- **Pagination**: Efficient record pagination
- **Batch Operations**: Queue jobs for bulk processing
- **Dashboard Caching**: Pre-computed metrics

### Scalability Considerations
- Database indexing strategy
- Queue-based background processing
- Session store (Redis/Memcached)
- Stateless API design

---

## Summary

The HRMS is a comprehensive, enterprise-grade HR management system built with modern Laravel practices. It features:

✅ **39 Eloquent Models** covering all HR functions  
✅ **12+ Service Classes** for business logic  
✅ **37 Controllers** handling diverse operations  
✅ **76+ Database Migrations** tracking schema evolution  
✅ **Event-Driven Architecture** for loose coupling  
✅ **Domain-Driven Design** for complex workflows  
✅ **Real-time Capabilities** via WebSocket broadcasting  
✅ **Complete Audit Trail** for compliance  
✅ **Role-Based Access Control** for security  
✅ **Extensible Design** for future enhancements  

The system is production-ready with comprehensive testing infrastructure (Pest PHP), proper error handling, and scalable architecture supporting thousands of employees.
