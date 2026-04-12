<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PerformanceAndRewardsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SpmsWorkflowSeeder::class,
            DepartmentMetricSeeder::class,
            EligibilityDashboardSeeder::class,
            RewardsRecognitionSeeder::class,
        ]);
    }
}
