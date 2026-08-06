<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryLogMiddleware
{
    private const SLOW_QUERY_THRESHOLD = 500;

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.debug')) {
            return $next($request);
        }

        DB::enableQueryLog();

        $startTime = microtime(true);

        $response = $next($request);

        $totalTime = (microtime(true) - $startTime) * 1000;
        $queries = DB::getQueryLog();

        $this->logQueries($request, $queries, $totalTime);

        return $response;
    }

    /**
     * Log queries that exceed the threshold.
     *
     * @param array<int, array{query: string, bindings: array, time: float}> $queries
     */
    private function logQueries(Request $request, array $queries, float $totalTime): void
    {
        $slowQueries = array_filter($queries, function (array $query) {
            return $query['time'] > self::SLOW_QUERY_THRESHOLD;
        });

        if (empty($slowQueries) && $totalTime < self::SLOW_QUERY_THRESHOLD) {
            return;
        }

        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'total_time_ms' => round($totalTime, 2),
            'query_count' => count($queries),
            'slow_queries' => array_map(function (array $query) {
                return [
                    'query' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time_ms' => $query['time'],
                ];
            }, array_values($slowQueries)),
        ];

        if (!empty($slowQueries)) {
            Log::warning('Slow queries detected', $context);
        } else {
            Log::debug('Request query log', $context);
        }
    }
}
