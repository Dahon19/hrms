<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\AttendanceCalendarService;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceCalendarController extends Controller
{
    public function __construct(
        private readonly AttendanceCalendarService $calendarService
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('view-attendance-calendar');

        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $monthInput)
            ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : now()->startOfMonth();

        $payload = $this->calendarService->buildCalendarPayload($request->user(), $month);

        return view('attendance.calendar', $payload + [
            'month' => $month,
            'canManage' => Gate::allows('manage-attendance'),
            'holidayTableAvailable' => Holiday::tableAvailable(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        Gate::authorize('view-attendance-calendar');

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $payload = $this->calendarService->buildRangePayload(
            $request->user(),
            Carbon::parse($validated['start'])->startOfDay(),
            Carbon::parse($validated['end'])->endOfDay()
        );

        return response()->json($payload);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-attendance');

        if (!Holiday::tableAvailable()) {
            return back()->with('error', 'Holiday table is not available yet. Run migrations first.');
        }

        $validated = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $holiday = Holiday::query()->create($validated + [
            'created_by' => $request->user()?->id,
        ]);

        AuditLogger::log('holiday_created', $holiday, [
            'holiday_date' => $holiday->holiday_date?->toDateString(),
            'name' => $holiday->name,
            'type' => $holiday->type,
        ]);

        return back()->with('success', 'Holiday saved to the DTR calendar.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        Gate::authorize('manage-attendance');

        $validated = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date,' . $holiday->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $holiday->update($validated);

        AuditLogger::log('holiday_updated', $holiday, [
            'holiday_date' => $holiday->holiday_date?->toDateString(),
            'name' => $holiday->name,
            'type' => $holiday->type,
        ]);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('manage-attendance');

        AuditLogger::log('holiday_deleted', $holiday, [
            'holiday_date' => $holiday->holiday_date?->toDateString(),
            'name' => $holiday->name,
        ]);

        $holiday->delete();

        return back()->with('success', 'Holiday removed from the DTR calendar.');
    }
}
