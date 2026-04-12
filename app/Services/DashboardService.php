<?php

namespace App\Services;

use App\Models\User;
use App\ViewModels\DashboardViewModel;

class DashboardService
{
    public function __construct(
        private readonly DashboardMetricsService $metricsService,
        private readonly DashboardActivityService $activityService,
        private readonly DashboardViewModel $viewModel
    ) {
    }

    public function buildFor(User $user): array
    {
        return $this->viewModel->present($user, array_merge(
            $this->metricsService->build($user),
            $this->activityService->build($user)
        ));
    }
}
