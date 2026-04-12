<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function index(Request $request)
    {
        if (Gate::denies('view-dashboard')) {
            return redirect()->route('attendance.history', [
                'period' => 'weekly',
                'date' => now()->toDateString(),
            ]);
        }

        return view('dashboard', [
            'dashboard' => $this->dashboardService->buildFor($request->user()),
        ]);
    }
}
