<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DepartmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('department_types') || !Schema::hasTable('departments')) {
            return;
        }

        $types = Department::query()
            ->whereNotNull('department_type')
            ->pluck('department_type')
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->values();

        foreach ($types as $index => $type) {
            DepartmentType::query()->updateOrCreate(
                ['name' => $type],
                ['sort_order' => $index + 1]
            );
        }
    }
}
