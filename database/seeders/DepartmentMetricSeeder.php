<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentMetric;
use App\Models\Employee;
use App\Services\DepartmentMetricsService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DepartmentMetricSeeder extends Seeder
{
    public function run(): void
    {
        if (!Department::query()->exists() || !Employee::query()->exists()) {
            return;
        }

        $service = app(DepartmentMetricsService::class);
        $windowStart = Carbon::today()->subDays(30);
        $availableDates = collect();

        for ($date = $windowStart->copy(); $date->lte(Carbon::today()); $date->addDay()) {
            if (!$date->isWeekend()) {
                $availableDates->push($date->copy());
            }
        }

        if ($availableDates->isEmpty()) {
            return;
        }

        $datesToSeed = $availableDates
            ->shuffle()
            ->take(min(18, $availableDates->count()))
            ->sortBy(fn (Carbon $metricDate) => $metricDate->timestamp)
            ->values();

        DepartmentMetric::query()->delete();

        foreach ($datesToSeed as $metricDate) {
            $service->generateForDate($metricDate);
        }
    }
}
