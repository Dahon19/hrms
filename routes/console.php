<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Attendance;
use App\Services\AttendancePolicyService;
use App\Services\DepartmentMetricsService;
use App\Services\RecruitmentActionService;
use App\Services\ReportExportService;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:department-metrics {--date=}', function () {
    $dateInput = $this->option('date');
    $date = $dateInput ? Carbon::parse($dateInput) : now();
    $count = (new DepartmentMetricsService())->generateForDate($date);
    $this->info("Generated {$count} department metrics for {$date->toDateString()}.");
})->purpose('Generate daily department metrics.');

Artisan::command('attendance:anomalies {--date=} {--from=} {--to=}', function () {
    $query = Attendance::query();
    $date = $this->option('date');
    $from = $this->option('from');
    $to = $this->option('to');

    if ($date) {
        $query->whereDate('date', Carbon::parse($date)->toDateString());
    } elseif ($from || $to) {
        $start = $from ? Carbon::parse($from)->toDateString() : now()->toDateString();
        $end = $to ? Carbon::parse($to)->toDateString() : $start;
        $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end);
    } else {
        $query->whereDate('date', now()->toDateString());
    }

    $service = new AttendancePolicyService();
    $count = 0;
    foreach ($query->get() as $attendance) {
        $service->applyPolicy($attendance);
        $count++;
    }

    $this->info("Recomputed anomalies for {$count} attendance records.");
})->purpose('Rebuild attendance anomalies for a date or range.');

Artisan::command('reports:export {type}', function (string $type) {
    $run = (new ReportExportService())->export($type);
    $this->info("Report {$type} completed with status: {$run->status}.");
})->purpose('Export a report to storage.');

Artisan::command('recruitment:close-expired-postings', function () {
    $closed = (new RecruitmentActionService())->closeExpiredOpenPostings();
    $this->info("Closed {$closed} expired job posting(s).");
})->purpose('Close open job postings whose application deadline has passed.');

Schedule::command('reports:department-metrics')->dailyAt('01:00');
Schedule::command('attendance:anomalies --date=yesterday')->dailyAt('02:00');
Schedule::command('documents:send-expiry-reminders')->dailyAt('07:00');

Schedule::command('offboarding:deactivate-due')->dailyAt('00:10');
Schedule::command('recruitment:close-expired-postings')->dailyAt('00:05');
