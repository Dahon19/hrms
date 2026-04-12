<?php

use App\Models\Employee;
use App\Models\PdsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee can update allowed personal fields but not restricted identity fields or email', function () {
    $user = User::factory()->create([
        'role' => 'employee',
        'email' => 'employee@example.com',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-99321',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'status' => 'active',
        'hire_date' => '2024-01-15',
    ]);

    $profile = PdsProfile::create([
        'employee_id' => $employee->id,
        'status' => 'draft',
        'section_completion' => [],
    ]);

    $profile->personalInfo()->create([
        'last_name' => 'Doe',
        'first_name' => 'Jane',
        'birth_place' => 'Old City',
        'civil_status' => 'Single',
        'tin_no' => '111-222-333',
        'mobile_no' => '09170000000',
        'email_address' => 'employee@example.com',
    ]);

    $response = $this->actingAs($user)->from(route('pds.show', $employee))->put(
        route('pds.sections.save', [$employee, 'personal-information']),
        [
            'last_name' => 'Changed',
            'first_name' => 'Changed',
            'sex' => 'male',
            'birth_place' => 'New City',
            'civil_status' => 'Married',
            'tin_no' => '999-888-777',
            'mobile_no' => '09999999999',
            'email_address' => 'new-email@example.com',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $personalInfo = $profile->fresh('personalInfo')->personalInfo;

    expect($personalInfo)->not->toBeNull();
    expect($personalInfo?->last_name)->toBe('Doe');
    expect($personalInfo?->first_name)->toBe('Jane');
    expect($personalInfo?->sex)->toBeNull();
    expect($personalInfo?->birth_place)->toBe('New City');
    expect($personalInfo?->civil_status)->toBe('Married');
    expect($personalInfo?->tin_no)->toBe('999-888-777');
    expect($personalInfo?->mobile_no)->toBe('09999999999');
    expect($personalInfo?->email_address)->toBe('employee@example.com');
});
