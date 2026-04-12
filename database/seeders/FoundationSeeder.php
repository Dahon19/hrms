<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentTypeSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,
            LeaveTypeSeeder::class,
            DocumentSystemSeeder::class,
        ]);
    }
}
