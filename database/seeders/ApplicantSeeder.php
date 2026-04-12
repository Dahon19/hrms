<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('applicants')
            || !Schema::hasTable('job_postings')
            || !Schema::hasTable('departments')
            || !Schema::hasTable('positions')
        ) {
            return;
        }

        $postings = $this->ensureSeededJobPostings();
        if ($postings->isEmpty()) {
            return;
        }

        $profiles = [
            [
                'name' => 'Alicia Mendoza',
                'gender' => 'female',
                'birthday' => '1999-04-14',
                'phone' => '09171230001',
                'address' => 'San Fernando City, La Union',
                'message' => 'Interested in contributing to student-facing and administrative HR operations.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Human Resources Staff',
                'created_days_ago' => 2,
            ],
            [
                'name' => 'Bryan Santos',
                'gender' => 'male',
                'birthday' => '1997-09-08',
                'phone' => '09171230002',
                'address' => 'Bauang, La Union',
                'message' => 'With experience in records handling and applicant coordination.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 3,
            ],
            [
                'name' => 'Camille Rivera',
                'gender' => 'female',
                'birthday' => '2000-01-19',
                'phone' => '09171230003',
                'address' => 'Agoo, La Union',
                'message' => 'Seeking an instructor role focused on modern web development.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Instructor I',
                'created_days_ago' => 1,
            ],
            [
                'name' => 'Daniel Cruz',
                'gender' => 'male',
                'birthday' => '1995-11-25',
                'phone' => '09171230004',
                'address' => 'San Juan, La Union',
                'message' => 'Background in procurement support and campus operations.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 5,
            ],
            [
                'name' => 'Elaine Flores',
                'gender' => 'female',
                'birthday' => '1998-07-03',
                'phone' => '09171230005',
                'address' => 'Rosario, La Union',
                'message' => 'Ready to support faculty and learners through quality classroom delivery.',
                'status' => 'hired',
                'account_status' => 'active',
                'posting_key' => 'Instructor I',
                'created_days_ago' => 11,
            ],
            [
                'name' => 'Francis Gomez',
                'gender' => 'male',
                'birthday' => '1996-12-12',
                'phone' => '09171230006',
                'address' => 'Aringay, La Union',
                'message' => 'Experience in office support, filing systems, and student transactions.',
                'status' => 'hired',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 9,
            ],
            [
                'name' => 'Grace Villanueva',
                'gender' => 'female',
                'birthday' => '2001-02-17',
                'phone' => '09171230007',
                'address' => 'Caba, La Union',
                'message' => 'Focused on employee relations, onboarding, and HR documentation.',
                'status' => 'archived',
                'account_status' => 'inactive',
                'posting_key' => 'Human Resources Staff',
                'created_days_ago' => 14,
            ],
            [
                'name' => 'Harold Navarro',
                'gender' => 'male',
                'birthday' => '1994-06-29',
                'phone' => '09171230008',
                'address' => 'Bangar, La Union',
                'message' => 'Has operations and clerical experience across academic offices.',
                'status' => 'archived',
                'account_status' => 'inactive',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 17,
            ],
            [
                'name' => 'Isabel Manalo',
                'gender' => 'female',
                'birthday' => '1998-10-11',
                'phone' => '09171230009',
                'address' => 'Tubao, La Union',
                'message' => 'Interested in records management, onboarding, and frontline HR support.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Human Resources Staff',
                'created_days_ago' => 4,
            ],
            [
                'name' => 'Julian Serrano',
                'gender' => 'male',
                'birthday' => '1996-05-23',
                'phone' => '09171230010',
                'address' => 'Luna, La Union',
                'message' => 'Looking to contribute to higher education administration and process support.',
                'status' => 'reviewing',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 6,
            ],
            [
                'name' => 'Katrina Dela Cruz',
                'gender' => 'female',
                'birthday' => '1997-03-02',
                'phone' => '09171230011',
                'address' => 'Naguilian, La Union',
                'message' => 'Brings classroom delivery and curriculum documentation experience.',
                'status' => 'interview',
                'account_status' => 'active',
                'posting_key' => 'Instructor I',
                'created_days_ago' => 7,
            ],
            [
                'name' => 'Louis Agustin',
                'gender' => 'male',
                'birthday' => '1993-08-16',
                'phone' => '09171230012',
                'address' => 'Bacnotan, La Union',
                'message' => 'Background in operations support, procurement follow-up, and office coordination.',
                'status' => 'for requirements',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 8,
            ],
            [
                'name' => 'Mariel Tabora',
                'gender' => 'female',
                'birthday' => '2000-12-07',
                'phone' => '09171230013',
                'address' => 'Sto. Tomas, La Union',
                'message' => 'Interested in campus HR operations and employee records support.',
                'status' => 'shortlisted',
                'account_status' => 'active',
                'posting_key' => 'Human Resources Staff',
                'created_days_ago' => 10,
            ],
            [
                'name' => 'Noel Pascual',
                'gender' => 'male',
                'birthday' => '1995-01-28',
                'phone' => '09171230014',
                'address' => 'Santol, La Union',
                'message' => 'Experienced in document processing and registrar-adjacent transactions.',
                'status' => 'submitted',
                'account_status' => 'active',
                'posting_key' => 'Administrative Assistant',
                'created_days_ago' => 12,
            ],
        ];

        foreach ($profiles as $index => $profile) {
            $posting = $postings->get($profile['posting_key']) ?? $postings->first();
            if (!$posting) {
                continue;
            }

            $normalizedName = Str::of($profile['name'])->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.');
            $email = $normalizedName . '@gmail.com';
            $createdAt = Carbon::now()->subDays((int) $profile['created_days_ago'])->setTime(9 + ($index % 6), 15, 0);

            Applicant::query()->updateOrCreate(
                [
                    'job_posting_id' => $posting->id,
                    'email' => $email,
                ],
                [
                    'full_name' => $profile['name'],
                    'gender' => $profile['gender'],
                    'birthday' => $profile['birthday'],
                    'phone' => $profile['phone'],
                    'address' => $profile['address'],
                    'message' => $profile['message'],
                    'application_letter_path' => null,
                    'resume_path' => null,
                    'transcript_path' => null,
                    'status' => $profile['status'],
                    'account_status' => $profile['account_status'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(2),
                ]
            );
        }

        $this->syncPostingStatuses();
    }

    /**
     * @return Collection<string, JobPosting>
     */
    private function ensureSeededJobPostings(): Collection
    {
        $seedDefinitions = [
            [
                'position_name' => 'staff',
                'department_name' => 'HR Department',
                'title' => 'Human Resources Staff',
                'employment_type' => 'Full-time',
                'required_headcount' => 1,
                'description' => 'Support recruitment, records management, and employee documentation.',
                'requirements' => 'Graduate of Psychology, HRDM, or related field.',
                'status' => 'open',
                'closing_date' => now()->addDays(18)->toDateString(),
            ],
            [
                'position_name' => 'staff',
                'department_name' => 'Presidents Office',
                'title' => 'Administrative Assistant',
                'employment_type' => 'Full-time',
                'required_headcount' => 2,
                'description' => 'Provide clerical and coordination support for administrative operations.',
                'requirements' => 'Strong communication, records handling, and office productivity skills.',
                'status' => 'open',
                'closing_date' => now()->addDays(24)->toDateString(),
            ],
            [
                'position_name' => 'instructor',
                'department_name' => 'College of Information Technology',
                'title' => 'Instructor I',
                'employment_type' => 'Full-time',
                'required_headcount' => 1,
                'description' => 'Deliver undergraduate IT courses and support curriculum activities.',
                'requirements' => 'Relevant degree with teaching or industry background in information technology.',
                'status' => 'open',
                'closing_date' => now()->addDays(30)->toDateString(),
            ],
        ];

        $postings = collect();

        foreach ($seedDefinitions as $definition) {
            $department = Department::query()
                ->where('department', $definition['department_name'])
                ->first();
            $position = Position::query()
                ->where('position', $definition['position_name'])
                ->first();

            if (!$department || !$position) {
                continue;
            }

            $posting = JobPosting::query()->updateOrCreate(
                ['title' => $definition['title']],
                [
                    'position_id' => $position->id,
                    'department_id' => $department->id,
                    'description' => $definition['description'],
                    'requirements' => $definition['requirements'],
                    'employment_type' => $definition['employment_type'],
                    'status' => $definition['status'],
                    'required_headcount' => $definition['required_headcount'],
                    'closing_date' => $definition['closing_date'],
                ]
            );

            $postings->put($definition['title'], $posting);
        }

        if ($postings->isEmpty()) {
            return JobPosting::query()->orderBy('id')->get()->keyBy(function (JobPosting $posting) {
                return (string) ($posting->title ?: $posting->id);
            });
        }

        return $postings;
    }

    private function syncPostingStatuses(): void
    {
        JobPosting::query()
            ->withCount([
                'applicants as hired_count' => function ($query) {
                    $query->where('status', 'hired');
                },
            ])
            ->get()
            ->each(function (JobPosting $posting) {
                $requiredHeadcount = max((int) ($posting->required_headcount ?? 1), 1);
                $status = (int) ($posting->hired_count ?? 0) >= $requiredHeadcount ? 'closed' : 'open';

                if ($posting->status !== $status) {
                    $posting->update(['status' => $status]);
                }
            });
    }
}
