<?php

namespace App\Http\Middleware;

use App\Support\RequestTimings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordRequestTimings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.enabled')) {
            return $next($request);
        }

        $sampleRate = min(1.0, max(0.0, (float) config('performance.sample_rate', 0.1)));
        if ($sampleRate === 0.0 || ($sampleRate < 1.0 && random_int(1, 1000000) > $sampleRate * 1000000)) {
            return $next($request);
        }

        $timings = new RequestTimings();
        $request->attributes->set(RequestTimings::ATTRIBUTE, $timings);
        $started = hrtime(true);
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            $duration = (hrtime(true) - $started) / 1000000;
            $request->attributes->remove(RequestTimings::ATTRIBUTE);
            if ($response !== null) {
                try {
                    Log::channel('performance')->info('HTTP request timing', [
                        'route' => $request->route()?->getName() ?? 'unnamed',
                        'method' => $request->method(),
                        'status' => $response->getStatusCode(),
                        'duration_ms' => round($duration, 3),
                        'db_ms' => round($timings->databaseMs, 3),
                        'query_count' => $timings->queryCount,
                    ]);
                } catch (Throwable) {
                    // Optional diagnostics must not interrupt bookings if logging fails.
                }
            }
        }
    }
}
