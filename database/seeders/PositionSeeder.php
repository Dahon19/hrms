<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmployeePosition;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\TravelOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->consolidateExistingPositions();

            Department::query()->orderBy('id')->get()->each(function (Department $department) {
                $defaults = $department->department_type === 'Academic'
                    ? ['head', 'coordinator', 'secretary', 'instructor']
                    : ['head', 'coordinator', 'secretary', 'staff'];

                foreach ($defaults as $name) {
                    Position::query()->updateOrCreate(
                        [
                            'department_id' => $department->id,
                            'position' => $name,
                        ],
                        []
                    );
                }
            });

            Position::query()->updateOrCreate(
                [
                    'department_id' => null,
                    'position' => 'admin',
                ],
                []
            );
        });
    }

    protected function consolidateExistingPositions(): void
    {
        $positions = Position::query()
            ->orderBy('id')
            ->get()
            ->groupBy(function (Position $position): string {
                $departmentKey = $position->department_id ?? 'null';

                return $departmentKey . '|' . strtolower(trim((string) $position->position));
            });

        foreach ($positions as $group) {
            if ($group->count() < 2) {
                continue;
            }

            /** @var Position $keeper */
            $keeper = $group->first();
            $duplicateIds = $group
                ->skip(1)
                ->pluck('id')
                ->filter()
                ->values();

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            EmployeePosition::query()
                ->whereIn('position_id', $duplicateIds)
                ->update(['position_id' => $keeper->id]);

            JobPosting::query()
                ->whereIn('position_id', $duplicateIds)
                ->update(['position_id' => $keeper->id]);

            TravelOrder::query()
                ->whereIn('position_id', $duplicateIds)
                ->update(['position_id' => $keeper->id]);

            Position::query()
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }
}
