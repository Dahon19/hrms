<?php

use App\Models\Employee;
use App\Models\PdsPersonalInfo;
use App\Models\PdsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('pds content is encrypted at rest and pds audit metadata is redacted', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99001',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    $profile = PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'hr_remarks' => 'Keep this correction note private.',
        'section_completion' => [],
    ]);

    $personalInfo = $profile->personalInfo()->create([
        'last_name' => 'Doe',
        'first_name' => 'Jane',
        'birth_date' => '1990-05-11',
        'tin_no' => '123-456-789',
        'email_address' => 'jane@example.com',
    ]);

    $experience = $profile->workExperiences()->create([
        'position_title' => 'HR Officer',
        'department_office' => 'Human Resources',
        'date_from' => '2021-02-01',
        'date_to' => '2024-02-01',
        'salary_grade' => 'SG-11',
        'appointment_status' => 'Permanent',
        'sector' => 'government',
    ]);

    $rawProfileRemark = DB::table('pds_profiles')->where('id', $profile->id)->value('hr_remarks');
    $rawPersonalLastName = DB::table('pds_personal_infos')->where('id', $personalInfo->id)->value('last_name');
    $rawExperienceTitle = DB::table('pds_work_experiences')->where('id', $experience->id)->value('position_title');

    expect($rawProfileRemark)->not->toBe('Keep this correction note private.');
    expect($rawPersonalLastName)->not->toBe('Doe');
    expect($rawExperienceTitle)->not->toBe('HR Officer');

    expect(Crypt::decryptString($rawProfileRemark))->toBe('Keep this correction note private.');
    expect(Crypt::decryptString($rawPersonalLastName))->toBe('Doe');
    expect(Crypt::decryptString($rawExperienceTitle))->toBe('HR Officer');

    $profile = $profile->fresh(['personalInfo', 'workExperiences']);

    expect($profile->hr_remarks)->toBe('Keep this correction note private.');
    expect($profile->personalInfo?->last_name)->toBe('Doe');
    expect($profile->personalInfo?->birth_date?->toDateString())->toBe('1990-05-11');
    expect($profile->personalInfo?->tin_no)->toBe('123-456-789');
    expect($profile->workExperiences->first()?->position_title)->toBe('HR Officer');
    expect($profile->workExperiences->first()?->date_from?->toDateString())->toBe('2021-02-01');

    $auditLog = DB::table('audit_logs')
        ->where('auditable_type', PdsPersonalInfo::class)
        ->where('auditable_id', $personalInfo->id)
        ->where('action', 'created')
        ->first();

    expect($auditLog)->not->toBeNull();

    $metadata = json_decode($auditLog->metadata, true);

    expect($metadata['attributes']['last_name'] ?? null)->toBe('[protected]');
    expect($metadata['attributes']['birth_date'] ?? null)->toBe('[protected]');
    expect($metadata['attributes']['tin_no'] ?? null)->toBe('[protected]');
});
