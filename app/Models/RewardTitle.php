<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'award_type',
        'title',
    ];

    public static function groupedForForm(): array
    {
        return static::query()
            ->orderBy('award_type')
            ->orderBy('title')
            ->get()
            ->groupBy('award_type')
            ->map(fn ($rows) => $rows->pluck('title')->values()->all())
            ->all();
    }

    public static function groupedOptionsForForm(): array
    {
        return static::query()
            ->orderBy('award_type')
            ->orderBy('title')
            ->get()
            ->groupBy('award_type')
            ->map(fn ($rows) => $rows
                ->map(fn (self $rewardTitle) => [
                    'id' => $rewardTitle->id,
                    'title' => $rewardTitle->title,
                ])
                ->values()
                ->all())
            ->all();
    }
}
