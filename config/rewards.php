<?php

return [
    'tenure_milestones_years' => [5, 10, 15, 20],

    // Attendance-based recognition window and threshold.
    'attendance' => [
        'period' => 'year',
        'max_absences' => 0,
        'title' => 'Perfect Attendance',
    ],

    // Performance-based recognition thresholds.
    'performance' => [
        'minimum_score' => 4.50,
        'qualifying_ratings' => ['outstanding', 'very_satisfactory'],
        'title' => 'Performance Excellence',
    ],
];

