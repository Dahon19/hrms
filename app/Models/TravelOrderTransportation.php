<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TravelOrderTransportation extends Model
{
    protected static ?bool $tableAvailableCache = null;
    private const DEFAULT_NAMES = [
        'Service Vehicle',
        'Private Vehicle',
        'Bus',
        'Van',
        'Plane',
        'Boat',
    ];

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('LOWER(name)');
    }

    public static function tableAvailable(): bool
    {
        if (static::$tableAvailableCache !== null) {
            return static::$tableAvailableCache;
        }

        static::$tableAvailableCache = Schema::hasTable('travel_order_transportations');

        return static::$tableAvailableCache;
    }

    public static function activeNames(): array
    {
        if (!static::tableAvailable()) {
            return static::defaultNames();
        }

        $names = static::query()
            ->active()
            ->ordered()
            ->pluck('name')
            ->all();

        if ($names === []) {
            return static::defaultNames();
        }

        return $names;
    }

    public static function defaultNames(): array
    {
        return self::DEFAULT_NAMES;
    }
}
