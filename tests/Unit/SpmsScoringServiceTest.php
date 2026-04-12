<?php

namespace Tests\Unit;

use App\Models\SpmsCriterion;
use App\Services\SpmsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmsScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_weighted_total_score(): void
    {
        $c1 = SpmsCriterion::create([
            'name' => 'Quality',
            'max_score' => 5,
            'category' => 'core',
            'weight' => 1.5,
        ]);
        $c2 = SpmsCriterion::create([
            'name' => 'Timeliness',
            'max_score' => 5,
            'category' => 'core',
            'weight' => 1.0,
        ]);

        $service = app(SpmsScoringService::class);
        $score = $service->computeTotalScore([
            ['criteria_id' => $c1->id, 'score' => 5],
            ['criteria_id' => $c2->id, 'score' => 4],
        ]);

        $this->assertEquals(4.6, $score);
    }
}
