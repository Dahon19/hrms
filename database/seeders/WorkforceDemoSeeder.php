<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WorkforceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TestEmployeeSeeder::class,
            AttendanceSeeder::class,
            LeaveSeeder::class,
            TravelOrderSeeder::class,
        ]);
    }
}
