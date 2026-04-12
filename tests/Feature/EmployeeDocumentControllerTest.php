<?php

use App\Models\User;
use App\Models\Employee;
use App\Models\Document;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createTestEmployee($gender, $role = 'employee') {
    $user = User::factory()->create(['gender' => $gender, 'role' => $role]);
    
    $department = Department::firstOrCreate(['department' => 'Test Dept'], ['department_type' => 'Administrative']);
    $position = Position::firstOrCreate(['position' => 'Test Pos']);

    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => 'EMP-' . uniqid(),
        'first_name' => 'Test',
        'last_name' => 'User',
        'department_id' => $department->id,
        'hire_date' => now(),
        'status' => 'active'
    ]);

    return [$user, $employee];
}

test('factory works', function () {
    $user = User::factory()->create();
    expect($user)->toBeInstanceOf(User::class);
});

test('male employee can upload male-only document', function () {
    Storage::fake('local');

    [$user, $employee] = createTestEmployee('male');
    
    $document = Document::create([
        'document' => 'Prostate Exam',
        'gender' => 'male'
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $employee->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    
    $this->assertDatabaseHas('employee_documents', [
        'employee_id' => $employee->id,
        'document_id' => $document->id,
    ]);
});

test('female employee cannot upload male-only document', function () {
    Storage::fake('local');

    [$user, $employee] = createTestEmployee('female');
    
    $document = Document::create([
        'document' => 'Prostate Exam',
        'gender' => 'male'
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $employee->id,
        ]);

    $response->assertSessionHasErrors(['document_id']);
    
    $this->assertDatabaseMissing('employee_documents', [
        'employee_id' => $employee->id,
        'document_id' => $document->id,
    ]);
});

test('female employee can upload female-only document', function () {
    Storage::fake('local');

    [$user, $employee] = createTestEmployee('female');
    
    $document = Document::create([
        'document' => 'Maternity Leave App',
        'gender' => 'female'
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $employee->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('male employee cannot upload female-only document', function () {
    Storage::fake('local');

    [$user, $employee] = createTestEmployee('male');
    
    $document = Document::create([
        'document' => 'Maternity Leave App',
        'gender' => 'female'
    ]);

    $response = $this->actingAs($user)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $employee->id,
        ]);

    $response->assertSessionHasErrors(['document_id']);
});

test('any employee can upload gender-neutral document', function () {
    Storage::fake('local');

    [$maleUser, $maleEmployee] = createTestEmployee('male');
    [$femaleUser, $femaleEmployee] = createTestEmployee('female');
    
    $document = Document::create([
        'document' => 'General Policy',
        'gender' => null
    ]);

    // Male upload
    $this->actingAs($maleUser)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $maleEmployee->id,
        ])->assertSessionHas('success');

    // Female upload
    $this->actingAs($femaleUser)
        ->post(route('employee-documents.store'), [
            'document_id' => $document->id,
            'file' => UploadedFile::fake()->create('test.pdf', 100),
            'employee_id' => $femaleEmployee->id,
        ])->assertSessionHas('success');
});
