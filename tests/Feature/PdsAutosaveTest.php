<?php

use App\Models\Employee;
use App\Models\PdsProfile;
use App\Models\User;
use App\Models\PdsEducation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pds section save returns json for autosave requests and persists draft data', function () {
    $user = User::factory()->create([
        'role' => 'employee',
        'gender' => 'female',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99444',
        'first_name' => 'Jane',
        'last_name' => 'Employee',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'section_completion' => [],
    ]);

    $response = $this->actingAs($user)->putJson(
        route('pds.sections.save', [$employee, 'other-information']),
        [
            'form_section' => 'other-information',
            'special_skills' => ['Spreadsheet modeling'],
            'recognitions' => ['Top performer'],
            'memberships' => ['HR Association'],
        ]
    );

    $response->assertOk()
        ->assertJsonFragment([
            'message' => 'Other information saved as draft.',
        ]);

    $profile = $employee->fresh()->pdsProfile()->with('otherInfos')->first();

    expect($profile)->not->toBeNull();
    expect($profile?->otherInfos)->toHaveCount(3);
    expect($profile?->otherInfos->pluck('description')->all())->toContain('Spreadsheet modeling');
    expect($profile?->otherInfos->pluck('description')->all())->toContain('Top performer');
    expect($profile?->otherInfos->pluck('description')->all())->toContain('HR Association');
});

test('pds submit is blocked when a required section entry is still incomplete', function () {
    $user = User::factory()->create([
        'role' => 'employee',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99445',
        'first_name' => 'Jane',
        'last_name' => 'Employee',
        'sex' => 'female',
        'birth_date' => '1995-05-15',
        'civil_status' => 'Single',
        'citizenship' => 'Filipino',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    $profile = PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'section_completion' => [
            'family-background' => true,
            'education-background' => true,
            'civil-service-eligibility' => true,
            'work-experience' => true,
            'voluntary-work' => true,
            'learning-development' => true,
            'other-information' => true,
        ],
    ]);

    $profile->personalInfo()->create([
        'last_name' => 'Employee',
        'first_name' => 'Jane',
        'birth_date' => '1995-05-15',
        'sex' => 'female',
        'civil_status' => 'Single',
        'citizenship' => 'Filipino',
    ]);

    PdsEducation::create([
        'pds_profile_id' => $profile->id,
        'education_level' => 'college',
        'school_name' => 'Northeastern College',
        'date_from' => '2012-06-01',
        'date_to' => null,
        'degree_course' => 'BSIT',
    ]);

    $response = $this->actingAs($user)
        ->from(route('pds.show', $employee))
        ->post(route('pds.submit', $employee));

    $response->assertRedirect(route('pds.show', $employee));
    $response->assertSessionHas('error');

    $profile->refresh();

    expect($profile->status)->toBe('draft');
});

test('pds submit succeeds after personal information is saved with required fields', function () {
    $user = User::factory()->create([
        'role' => 'employee',
        'gender' => 'female',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99446',
        'first_name' => 'Jane',
        'last_name' => 'Employee',
        'sex' => 'female',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    $profile = PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'section_completion' => [],
    ]);

    $this->actingAs($user)->putJson(
        route('pds.sections.save', [$employee, 'personal-information']),
        [
            'form_section' => 'personal-information',
            'sex' => 'female',
            'birth_date' => '1995-05-15',
            'birth_place' => 'Pasig City',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
        ]
    )->assertOk();

    $profile->familyBackground()->create([
        'father_last_name' => 'Employee',
        'father_first_name' => 'Parent',
    ]);

    \App\Models\PdsEducation::create([
        'pds_profile_id' => $profile->id,
        'education_level' => 'college',
        'school_name' => 'Northeastern College',
        'date_from' => '2012-06-01',
        'date_to' => '2016-03-31',
        'degree_course' => 'BSIT',
    ]);

    \App\Models\PdsCivilServiceEligibility::create([
        'pds_profile_id' => $profile->id,
        'eligibility_type' => 'Career Service Professional',
        'exam_date' => '2017-08-01',
        'exam_place' => 'Manila',
    ]);

    \App\Models\PdsWorkExperience::create([
        'pds_profile_id' => $profile->id,
        'date_from' => '2018-01-01',
        'position_title' => 'Administrative Assistant',
        'department_office' => 'Registrar',
        'appointment_status' => 'Permanent',
    ]);

    \App\Models\PdsVoluntaryWork::create([
        'pds_profile_id' => $profile->id,
        'organization_name' => 'Community Outreach',
        'date_from' => '2020-01-01',
        'date_to' => '2020-12-31',
        'position_nature' => 'Volunteer',
    ]);

    \App\Models\PdsTraining::create([
        'pds_profile_id' => $profile->id,
        'title' => 'Records Management',
        'date_from' => '2021-02-01',
        'date_to' => '2021-02-03',
        'conducted_by' => 'HR Office',
    ]);

    \App\Models\PdsOtherInfo::create([
        'pds_profile_id' => $profile->id,
        'info_type' => 'special_skill',
        'description' => 'Spreadsheet modeling',
    ]);

    $response = $this->actingAs($user)
        ->from(route('pds.show', $employee))
        ->post(route('pds.submit', $employee));

    $response->assertRedirect(route('pds.show', $employee));
    $response->assertSessionHas('success', 'PDS submitted for HR verification.');

    $profile->refresh();

    expect($profile->status)->toBe('submitted');
});

test('pds autosave reports readiness when the record becomes complete', function () {
    $user = User::factory()->create([
        'role' => 'employee',
        'gender' => 'female',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99447',
        'first_name' => 'Jane',
        'last_name' => 'Employee',
        'sex' => 'female',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    $profile = PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'section_completion' => [],
    ]);

    $profile->familyBackground()->create([
        'father_last_name' => 'Employee',
        'father_first_name' => 'Parent',
    ]);

    \App\Models\PdsEducation::create([
        'pds_profile_id' => $profile->id,
        'education_level' => 'college',
        'school_name' => 'Northeastern College',
        'date_from' => '2012-06-01',
        'date_to' => '2016-03-31',
        'degree_course' => 'BSIT',
    ]);

    \App\Models\PdsCivilServiceEligibility::create([
        'pds_profile_id' => $profile->id,
        'eligibility_type' => 'Career Service Professional',
        'exam_date' => '2017-08-01',
        'exam_place' => 'Manila',
    ]);

    \App\Models\PdsWorkExperience::create([
        'pds_profile_id' => $profile->id,
        'date_from' => '2018-01-01',
        'position_title' => 'Administrative Assistant',
        'department_office' => 'Registrar',
        'appointment_status' => 'Permanent',
    ]);

    \App\Models\PdsVoluntaryWork::create([
        'pds_profile_id' => $profile->id,
        'organization_name' => 'Community Outreach',
        'date_from' => '2020-01-01',
        'date_to' => '2020-12-31',
        'position_nature' => 'Volunteer',
    ]);

    \App\Models\PdsTraining::create([
        'pds_profile_id' => $profile->id,
        'title' => 'Records Management',
        'date_from' => '2021-02-01',
        'date_to' => '2021-02-03',
        'conducted_by' => 'HR Office',
    ]);

    \App\Models\PdsOtherInfo::create([
        'pds_profile_id' => $profile->id,
        'info_type' => 'special_skill',
        'description' => 'Spreadsheet modeling',
    ]);

    $response = $this->actingAs($user)->putJson(
        route('pds.sections.save', [$employee, 'personal-information']),
        [
            'form_section' => 'personal-information',
            'sex' => 'female',
            'birth_date' => '1995-05-15',
            'birth_place' => 'Pasig City',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
        ]
    );

    $response->assertOk()
        ->assertJsonFragment([
            'ready_to_submit' => true,
            'status' => 'draft',
        ]);
});
