<?php

namespace App\ViewModels;

use App\Models\User;

class DashboardViewModel
{
    public function present(User $user, array $payload): array
    {
        return [
            'header' => $payload['header'] ?? [],
            'actions' => $payload['actions'] ?? [],
            'action_center' => collect($payload['action_center'] ?? [])
                ->map(function (array $item) {
                    $item['count_label'] = number_format((int) ($item['count'] ?? 0));
                    return $item;
                })
                ->values()
                ->all(),
            'kpis' => collect($payload['kpis'] ?? [])
                ->map(function (array $metric) {
                    if (is_numeric($metric['value'] ?? null)) {
                        $numericValue = (float) $metric['value'];
                        $isWholeNumber = abs($numericValue - round($numericValue)) < 0.00001;
                        $metric['display_value'] = number_format($numericValue, $isWholeNumber ? 0 : 1);
                    } else {
                        $metric['display_value'] = (string) ($metric['value'] ?? '');
                    }

                    return $metric;
                })
                ->values()
                ->all(),
            'progress_groups' => $payload['progress_groups'] ?? [],
            'charts' => collect($payload['charts'] ?? [])
                ->filter(function (array $chart): bool {
                    if (empty($chart['labels'])) {
                        return false;
                    }

                    if (!empty($chart['values'])) {
                        return true;
                    }

                    $datasets = $chart['datasets'] ?? [];
                    if (!is_array($datasets) || $datasets === []) {
                        return false;
                    }

                    foreach ($datasets as $dataset) {
                        if (is_array($dataset) && !empty($dataset['data'])) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values()
                ->all(),
            'activities' => $payload['activities'] ?? [],
            'recruitment' => $payload['recruitment'] ?? [],
            'calendar' => $payload['calendar'] ?? [],
            'notifications' => $payload['notifications'] ?? [],
            'offboarding' => $payload['offboarding'] ?? [],
            'empty_states' => [
                'action_center' => $user->canViewData()
                    ? 'No immediate action items are waiting.'
                    : 'No personal tasks need action right now.',
                'activities' => 'No recent activity to show.',
                'notifications' => 'No notifications available.',
            ],
        ];
    }
}
