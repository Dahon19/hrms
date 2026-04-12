<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestEmployeeSeeder extends Seeder
{
    private const FEMALE_FIRST_NAMES = [
        'Andrea', 'Bianca', 'Candice', 'Daphne', 'Elaine', 'Frances', 'Giselle', 'Hazel',
        'Isabel', 'Jasmine', 'Kara', 'Lara', 'Mika', 'Nadine', 'Paula', 'Queenie',
        'Regine', 'Samantha', 'Therese', 'Vanessa',
    ];

    private const MALE_FIRST_NAMES = [
        'Adrian', 'Brent', 'Cedric', 'Dominic', 'Enzo', 'Felix', 'Gabriel', 'Harvey',
        'Ivan', 'Jericho', 'Kevin', 'Lester', 'Miguel', 'Noah', 'Paolo', 'Ramon',
        'Stephen', 'Tristan', 'Vincent', 'Wesley',
    ];

    private const LAST_NAMES = [
        'Abad', 'Alvarez', 'Benitez', 'Cabrera', 'Domingo', 'Estrada', 'Fausto', 'Gallardo',
        'Herrera', 'Ignacio', 'Jimenez', 'Luna', 'Marquez', 'Natividad', 'Ocampo', 'Perez',
        'Quintos', 'Ramirez', 'Soriano', 'Valencia', 'Yap', 'Zamora',
    ];

    private const MIN_HIRE_MONTHS_AGO = 5;

    private const MAX_HIRE_MONTHS_AGO = 18;

    public function run(): void
    {
        $departments = Department::query()
            ->orderBy('department')
            ->get();

        $departmentMap = $departments
            ->keyBy('department')
            ->map(fn (Department $dept) => $dept->id);

        $people = [
            [
                'name' => 'Hannah Reyes',
                'email' => 'hannah.reyes@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Hannah',
                'last_name' => 'Reyes',
                'department' => 'HR Department',
                'position' => 'head',
            ],
            [
                'name' => 'Paulo Cruz',
                'email' => 'paulo.cruz@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Paulo',
                'last_name' => 'Cruz',
                'department' => 'HR Department',
                'position' => 'staff',
            ],
            [
                'name' => 'Elena Santos',
                'email' => 'elena.santos@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Elena',
                'last_name' => 'Santos',
                'department' => 'Presidents Office',
                'position' => 'head',
            ],
            [
                'name' => 'Marc Jenkins',
                'email' => 'marc.jenkins@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Marc',
                'last_name' => 'Jenkins',
                'department' => 'College of Information Technology',
                'position' => 'head',
            ],
            [
                'name' => 'Rhea Torres',
                'email' => 'rhea.torres@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Rhea',
                'last_name' => 'Torres',
                'department' => 'College of Information Technology',
                'position' => 'coordinator',
            ],
            [
                'name' => 'Kyle Navarro',
                'email' => 'kyle.navarro@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Kyle',
                'last_name' => 'Navarro',
                'department' => 'College of Information Technology',
                'position' => 'secretary',
            ],
            [
                'name' => 'Ivy Ramos',
                'email' => 'ivy.ramos@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Ivy',
                'last_name' => 'Ramos',
                'department' => 'College of Information Technology',
                'position' => 'instructor',
            ],
            [
                'name' => 'Leo Mendoza',
                'email' => 'leo.mendoza@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Leo',
                'last_name' => 'Mendoza',
                'department' => 'College of Information Technology',
                'position' => 'instructor',
            ],
            [
                'name' => 'Alyssa Lim',
                'email' => 'alyssa.lim@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Alyssa',
                'last_name' => 'Lim',
                'department' => 'Registrar',
                'position' => 'head',
            ],
            [
                'name' => 'Nina Yu',
                'email' => 'nina.yu@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Nina',
                'last_name' => 'Yu',
                'department' => 'Registrar',
                'position' => 'staff',
            ],
            [
                'name' => 'Samuel Diaz',
                'email' => 'samuel.diaz@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Samuel',
                'last_name' => 'Diaz',
                'department' => 'Facilities',
                'position' => 'head',
            ],
            [
                'name' => 'Tess Molina',
                'email' => 'tess.molina@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Tess',
                'last_name' => 'Molina',
                'department' => 'Facilities',
                'position' => 'staff',
            ],
            [
                'name' => 'Mia Fernandez',
                'email' => 'mia.fernandez@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Mia',
                'last_name' => 'Fernandez',
                'department' => 'Admissions Office',
                'position' => 'head',
            ],
            [
                'name' => 'Carlo Bautista',
                'email' => 'carlo.bautista@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Carlo',
                'last_name' => 'Bautista',
                'department' => 'Admissions Office',
                'position' => 'staff',
            ],
            [
                'name' => 'Joanne Villareal',
                'email' => 'joanne.villareal@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Joanne',
                'last_name' => 'Villareal',
                'department' => 'Accounting Office',
                'position' => 'head',
            ],
            [
                'name' => 'Victor Salazar',
                'email' => 'victor.salazar@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Victor',
                'last_name' => 'Salazar',
                'department' => 'Accounting Office',
                'position' => 'staff',
            ],
            [
                'name' => 'Bea Gutierrez',
                'email' => 'bea.gutierrez@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Bea',
                'last_name' => 'Gutierrez',
                'department' => 'Management Information Systems (MIS)',
                'position' => 'head',
            ],
            [
                'name' => 'Owen Castillo',
                'email' => 'owen.castillo@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Owen',
                'last_name' => 'Castillo',
                'department' => 'Management Information Systems (MIS)',
                'position' => 'staff',
            ],
            [
                'name' => 'Sofia Pineda',
                'email' => 'sofia.pineda@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Sofia',
                'last_name' => 'Pineda',
                'department' => 'Guidance & Counseling Center',
                'position' => 'head',
            ],
            [
                'name' => 'Liam Ordonez',
                'email' => 'liam.ordonez@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Liam',
                'last_name' => 'Ordonez',
                'department' => 'Library Services',
                'position' => 'head',
            ],
            [
                'name' => 'Nora Velasco',
                'email' => 'nora.velasco@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Nora',
                'last_name' => 'Velasco',
                'department' => 'Library Services',
                'position' => 'staff',
            ],
            [
                'name' => 'Ethan Robles',
                'email' => 'ethan.robles@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Ethan',
                'last_name' => 'Robles',
                'department' => 'College of Engineering',
                'position' => 'head',
            ],
            [
                'name' => 'Patricia Concepcion',
                'email' => 'patricia.concepcion@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Patricia',
                'last_name' => 'Concepcion',
                'department' => 'College of Engineering',
                'position' => 'instructor',
            ],
            [
                'name' => 'Darren Aquino',
                'email' => 'darren.aquino@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Darren',
                'last_name' => 'Aquino',
                'department' => 'College of Education',
                'position' => 'head',
            ],
            [
                'name' => 'Faith Evangelista',
                'email' => 'faith.evangelista@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Faith',
                'last_name' => 'Evangelista',
                'department' => 'College of Education',
                'position' => 'instructor',
            ],
            [
                'name' => 'Rico Del Rosario',
                'email' => 'rico.delrosario@example.com',
                'role' => 'employee',
                'gender' => 'male',
                'first_name' => 'Rico',
                'last_name' => 'Del Rosario',
                'department' => 'Procurement Office',
                'position' => 'staff',
            ],
            [
                'name' => 'Janelle Mercado',
                'email' => 'janelle.mercado@example.com',
                'role' => 'employee',
                'gender' => 'female',
                'first_name' => 'Janelle',
                'last_name' => 'Mercado',
                'department' => 'College of Business Administration & Accountancy',
                'position' => 'instructor',
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'role' => 'admin',
                'gender' => 'male',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'department' => '',
                'position' => 'admin',
            ]
        ];

        $people = array_merge($people, $this->buildDepartmentCoveragePeople($departments, $people));

        foreach ($people as $person) {
            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'role' => $person['role'] ?? 'employee',
                    'gender' => $person['gender'] ?? null,
                    'archived_at' => null,
                    'password' => Hash::make('password'),
                ]
            );

            $departmentId = $departmentMap->get($person['department']);
            $positionId = null;
            if ($person['position'] === 'admin') {
                $positionId = Position::query()
                    ->whereNull('department_id')
                    ->where('position', 'admin')
                    ->value('id');
            } elseif ($departmentId) {
                $positionId = Position::query()
                    ->where('department_id', $departmentId)
                    ->where('position', $person['position'])
                    ->value('id');
            }
            $employee = Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_id' => Employee::query()
                        ->where('user_id', $user->id)
                        ->value('employee_id') ?: Employee::nextEmployeeId(),
                    'first_name' => $person['first_name'],
                    'last_name' => $person['last_name'],
                    'department_id' => $departmentId,
                    'hire_date' => $this->seededHireDateFor($user->email, $person['role'] ?? 'employee'),
                    'status' => 'active',
                ]
            );

            if ($positionId) {
                EmployeePosition::query()->where('employee_id', $employee->id)->delete();
                EmployeePosition::query()->create([
                    'employee_id' => $employee->id,
                    'position_id' => $positionId,
                ]);
            } else {
                EmployeePosition::query()->where('employee_id', $employee->id)->delete();
            }
        }

        $this->normalizeLegacyGeneratedEmployees();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $departments
     * @param  array<int, array<string, mixed>>  $existingPeople
     * @return array<int, array<string, mixed>>
     */
    private function buildDepartmentCoveragePeople($departments, array $existingPeople): array
    {
        $existingByDepartment = collect($existingPeople)
            ->filter(fn (array $person) => filled($person['department'] ?? null))
            ->groupBy('department')
            ->map(fn ($group) => collect($group)->pluck('position')->filter()->values()->all());

        $generated = [];

        foreach ($departments as $department) {
            $departmentName = (string) $department->department;
            $coveredPositions = collect($existingByDepartment->get($departmentName, []));
            $isAcademic = (string) $department->department_type === 'Academic';
            $secondaryPosition = $isAcademic ? 'instructor' : 'staff';

            if (!$coveredPositions->contains('head')) {
                $generated[] = $this->makeGeneratedPerson($department, 'head');
            }

            if (!$coveredPositions->contains($secondaryPosition)) {
                $generated[] = $this->makeGeneratedPerson($department, $secondaryPosition);
            }
        }

        return $generated;
    }

    private function makeGeneratedPerson(Department $department, string $position): array
    {
        $nameParts = $this->pickGeneratedNameParts($department, $position);
        $emailLocal = strtolower($nameParts['first_name'] . '.' . $nameParts['last_name']);
        $emailLocal .= '.' . $department->id . '.' . strtolower($position);

        return [
            'name' => trim($nameParts['first_name'] . ' ' . $nameParts['last_name']),
            'email' => $emailLocal . '@example.com',
            'role' => 'employee',
            'gender' => $nameParts['gender'],
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'department' => (string) $department->department,
            'position' => $position,
        ];
    }

    /**
     * @return array{first_name:string,last_name:string,gender:string}
     */
    private function pickGeneratedNameParts(Department $department, string $position): array
    {
        $seed = abs(crc32($department->department . '|' . $position . '|' . $department->id));
        $useFemale = ($seed % 2) === 0;
        $firstNames = $useFemale ? self::FEMALE_FIRST_NAMES : self::MALE_FIRST_NAMES;

        $firstName = $firstNames[$seed % count($firstNames)];
        $lastName = self::LAST_NAMES[intdiv($seed, max(count($firstNames), 1)) % count(self::LAST_NAMES)];

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $useFemale ? 'female' : 'male',
        ];
    }

    private function normalizeLegacyGeneratedEmployees(): void
    {
        Employee::query()
            ->with(['user', 'department', 'positions.position'])
            ->whereIn('last_name', ['Lead', 'Member'])
            ->get()
            ->each(function (Employee $employee): void {
                $department = $employee->department;
                $user = $employee->user;
                $positionName = optional($employee->positions->first()?->position)->position;

                if (!$department || !$user || !is_string($positionName) || $positionName === '') {
                    return;
                }

                $nameParts = $this->pickGeneratedNameParts($department, $positionName);
                $emailLocal = strtolower($nameParts['first_name'] . '.' . $nameParts['last_name']);
                $email = $emailLocal . '.' . $department->id . '.' . strtolower($positionName) . '@example.com';

                if (
                    User::query()
                        ->where('email', $email)
                        ->whereKeyNot($user->id)
                        ->exists()
                ) {
                    $email = $emailLocal . '.' . $employee->id . '@example.com';
                }

                $user->update([
                    'name' => $nameParts['first_name'] . ' ' . $nameParts['last_name'],
                    'email' => $email,
                    'gender' => $nameParts['gender'],
                ]);

                $employee->update([
                    'first_name' => $nameParts['first_name'],
                    'last_name' => $nameParts['last_name'],
                ]);
            });
    }

    private function seededHireDateFor(string $email, string $role = 'employee'): string
    {
        if ($role === 'admin') {
            return now()->copy()->subMonths(self::MAX_HIRE_MONTHS_AGO)->startOfMonth()->toDateString();
        }

        $range = max(self::MAX_HIRE_MONTHS_AGO - self::MIN_HIRE_MONTHS_AGO, 0);
        $offset = $range === 0 ? 0 : abs(crc32($email)) % ($range + 1);
        $monthsAgo = self::MIN_HIRE_MONTHS_AGO + $offset;

        return Carbon::now()
            ->subMonths($monthsAgo)
            ->startOfMonth()
            ->toDateString();
    }
}
