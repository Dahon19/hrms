<?php

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeRecruitmentAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'archived_at' => null,
    ]);
}

function makeRecruitmentHrUser(): User
{
    $department = makeRecruitmentDepartment();
    $user = User::factory()->create([
        'role' => 'employee',
        'archived_at' => null,
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => Employee::nextEmployeeId(),
        'first_name' => 'Hr',
        'last_name' => 'Reviewer',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    $position = Position::firstOrCreate([
        'position' => 'Staff',
    ]);

    \App\Models\EmployeePosition::firstOrCreate([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
    ]);

    return $user;
}

function makeRecruitmentDepartment(string $name = 'HR Department'): Department
{
    return Department::firstOrCreate([
        'department' => $name,
    ], [
        'department_type' => 'Administrative',
    ]);
}

function makeRecruitmentPosting(array $overrides = []): JobPosting
{
    $department = $overrides['department'] ?? makeRecruitmentDepartment();
    $position = $overrides['position'] ?? Position::firstOrCreate([
        'position' => 'Staff',
    ], [
        'department_id' => $department->id,
    ]);

    return JobPosting::create(array_merge([
        'position_id' => $position->id,
        'title' => $position->position,
        'department_id' => $department->id,
        'description' => 'Recruitment test posting.',
        'requirements' => 'Resume required.',
        'employment_type' => 'Full-time',
        'status' => 'open',
        'required_headcount' => 1,
    ], collect($overrides)->except(['department', 'position'])->all()));
}

test('archiving an applicant does not deactivate an existing user or employee with the same email', function () {
    $admin = makeRecruitmentAdmin();
    $department = makeRecruitmentDepartment('Accounting Office');
    $user = User::factory()->create([
        'email' => 'person@example.com',
        'role' => 'employee',
        'archived_at' => null,
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => Employee::nextEmployeeId(),
        'first_name' => 'Existing',
        'last_name' => 'Employee',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    $posting = makeRecruitmentPosting(['department' => $department]);

    $applicant = Applicant::create([
        'job_posting_id' => $posting->id,
        'full_name' => 'Rejected Applicant',
        'email' => $user->email,
        'gender' => 'male',
        'birthday' => now()->subYears(25)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Cagayan, Philippines',
        'status' => 'submitted',
        'account_status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->post(route('job-postings.applicants.archive', $applicant))
        ->assertRedirect(route('job-postings.applicants', ['view' => 'history']))
        ->assertSessionHas('success');

    expect($applicant->fresh()->status)->toBe('archived');
    expect($user->fresh()->archived_at)->toBeNull();
    expect($employee->fresh()->status)->toBe('active');
});

test('completing an applicant uses the short success message when the vacancy is not yet full', function () {
    $admin = makeRecruitmentAdmin();
    $posting = makeRecruitmentPosting(['required_headcount' => 2]);

    $applicant = Applicant::create([
        'job_posting_id' => $posting->id,
        'full_name' => 'First Hire',
        'email' => 'first.hire@example.com',
        'gender' => 'female',
        'birthday' => now()->subYears(24)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Tuguegarao, Philippines',
        'status' => 'submitted',
        'account_status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->post(route('job-postings.applicants.complete', $applicant))
        ->assertRedirect(route('job-postings.applicants', ['view' => 'history']))
        ->assertSessionHas('success', 'Applicant completed.');

    expect($posting->fresh()->status)->toBe('open');
    expect($applicant->fresh()->status)->toBe('hired');
});

test('completing the final applicant closes the posting and archives the remaining applicants', function () {
    $admin = makeRecruitmentAdmin();
    $posting = makeRecruitmentPosting(['required_headcount' => 1]);

    $winner = Applicant::create([
        'job_posting_id' => $posting->id,
        'full_name' => 'Winning Applicant',
        'email' => 'winner@example.com',
        'gender' => 'male',
        'birthday' => now()->subYears(23)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Ilagan, Philippines',
        'status' => 'submitted',
        'account_status' => 'inactive',
    ]);

    $otherApplicant = Applicant::create([
        'job_posting_id' => $posting->id,
        'full_name' => 'Other Applicant',
        'email' => 'other@example.com',
        'gender' => 'female',
        'birthday' => now()->subYears(22)->toDateString(),
        'phone' => '09181234567',
        'address' => 'Aparri, Philippines',
        'status' => 'submitted',
        'account_status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->post(route('job-postings.applicants.complete', $winner))
        ->assertRedirect(route('job-postings.applicants', ['view' => 'history']))
        ->assertSessionHas('success', 'Applicant completed. Vacancy filled. Remaining applicants archived.');

    expect($posting->fresh()->status)->toBe('closed');
    expect($winner->fresh()->status)->toBe('hired');
    expect($otherApplicant->fresh()->status)->toBe('archived');
});

test('opening the applicants page does not sync employee documents as a hidden side effect', function () {
    Storage::fake('local');

    $admin = makeRecruitmentAdmin();
    $department = makeRecruitmentDepartment('Library Services');
    $posting = makeRecruitmentPosting(['department' => $department]);

    $user = User::factory()->create([
        'email' => 'hired@example.com',
        'role' => 'employee',
        'archived_at' => null,
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => Employee::nextEmployeeId(),
        'first_name' => 'Hired',
        'last_name' => 'Employee',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    Storage::disk('local')->put('applicants/'.$posting->id.'/resume.pdf', 'resume');

    Applicant::create([
        'job_posting_id' => $posting->id,
        'full_name' => 'Hired Employee',
        'email' => $user->email,
        'gender' => 'male',
        'birthday' => now()->subYears(28)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Santiago, Philippines',
        'resume_path' => 'applicants/'.$posting->id.'/resume.pdf',
        'status' => 'hired',
        'account_status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('job-postings.applicants'))
        ->assertOk();

    expect(EmployeeDocument::where('employee_id', $employee->id)->count())->toBe(0);
});

test('public application submission stores files, creates applicant, and notifies hr recipients immediately', function () {
    Storage::fake('local');

    $admin = makeRecruitmentAdmin();
    $hrUser = makeRecruitmentHrUser();
    $posting = makeRecruitmentPosting();

    $response = $this->post(route('jobs.apply', $posting), [
        'full_name' => 'Portal Applicant',
        'email' => 'portal.applicant@gmail.com',
        'gender' => 'female',
        'birthday' => now()->subYears(22)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Tuguegarao City, Cagayan, Philippines',
        'message' => 'Interested in the vacancy.',
        'application_letter' => UploadedFile::fake()->create('application-letter.pdf', 200, 'application/pdf'),
        'resume' => UploadedFile::fake()->create('resume.pdf', 220, 'application/pdf'),
        'transcript' => UploadedFile::fake()->create('transcript.jpg', 180, 'image/jpeg'),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Application submitted successfully.');

    $applicant = Applicant::query()
        ->where('job_posting_id', $posting->id)
        ->where('email', 'portal.applicant@gmail.com')
        ->first();

    expect($applicant)->not->toBeNull();
    expect($applicant->status)->toBe('submitted');
    expect($applicant->account_status)->toBe('inactive');

    Storage::disk('local')->assertExists($applicant->application_letter_path);
    Storage::disk('local')->assertExists($applicant->resume_path);
    Storage::disk('local')->assertExists($applicant->transcript_path);

    $this->actingAs($admin)
        ->get(route('job-postings.applicants'))
        ->assertOk()
        ->assertSee('Portal Applicant');

    $this->actingAs($hrUser)
        ->get(route('job-postings.applicants'))
        ->assertOk()
        ->assertSee('Portal Applicant');

    expect(DatabaseNotification::query()
        ->where('notifiable_id', $admin->id)
        ->count())->toBeGreaterThan(0);
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $hrUser->id)
        ->count())->toBeGreaterThan(0);
});

test('public application validation failure returns a visible error flash', function () {
    $posting = makeRecruitmentPosting();

    $response = $this->from(route('landing'))->post(route('jobs.apply', $posting), [
        'full_name' => 'Portal Applicant',
        'email' => 'invalid-email',
        'gender' => 'female',
        'birthday' => now()->subYears(22)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Tuguegarao City, Cagayan, Philippines',
    ]);

    $response->assertRedirect(route('landing'));
    $response->assertSessionHas('error');
    $response->assertSessionHasErrors(['email', 'application_letter', 'resume']);
});

test('visiting the public portal auto closes open postings with expired closing dates', function () {
    $expired = makeRecruitmentPosting([
        'title' => 'Expired Vacancy',
        'status' => 'open',
        'closing_date' => now()->subDay()->toDateString(),
    ]);

    $active = makeRecruitmentPosting([
        'title' => 'Active Vacancy',
        'status' => 'open',
        'closing_date' => now()->addDay()->toDateString(),
    ]);

    $this->get(route('landing'))->assertOk();

    expect($expired->fresh()->status)->toBe('closed');
    expect($active->fresh()->status)->toBe('open');
});

test('expired posting rejects public application and closes the vacancy status', function () {
    $posting = makeRecruitmentPosting([
        'status' => 'open',
        'closing_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->from(route('landing'))->post(route('jobs.apply', $posting), [
        'full_name' => 'Late Applicant',
        'email' => 'late.applicant@example.com',
        'gender' => 'male',
        'birthday' => now()->subYears(21)->toDateString(),
        'phone' => '09171234567',
        'address' => 'Tuguegarao City, Cagayan, Philippines',
    ]);

    $response->assertRedirect(route('landing'));
    $response->assertSessionHas('warning', 'This posting is already closed because the application deadline has passed.');

    expect($posting->fresh()->status)->toBe('closed');
    expect(Applicant::where('email', 'late.applicant@example.com')->exists())->toBeFalse();
});

test('recruitment close-expired-postings command closes only open expired vacancies', function () {
    $expiredOpen = makeRecruitmentPosting([
        'status' => 'open',
        'closing_date' => now()->subDay()->toDateString(),
    ]);

    $futureOpen = makeRecruitmentPosting([
        'status' => 'open',
        'closing_date' => now()->addDays(3)->toDateString(),
    ]);

    $expiredClosed = makeRecruitmentPosting([
        'status' => 'closed',
        'closing_date' => now()->subDays(4)->toDateString(),
    ]);

    $this->artisan('recruitment:close-expired-postings')
        ->expectsOutput('Closed 1 expired job posting(s).')
        ->assertSuccessful();

    expect($expiredOpen->fresh()->status)->toBe('closed');
    expect($futureOpen->fresh()->status)->toBe('open');
    expect($expiredClosed->fresh()->status)->toBe('closed');
});
