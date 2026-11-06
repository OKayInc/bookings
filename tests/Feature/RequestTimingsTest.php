<?php

namespace Tests\Feature;

use App\Support\RequestTimings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class RequestTimingsTest extends TestCase
{
    public function test_disabled_and_zero_sample_rate_do_not_collect_or_log_requests(): void
    {
        Route::get('/_timing-test', function () {
            $this->assertFalse(request()->attributes->has(RequestTimings::ATTRIBUTE));
            DB::select('select 1');

            return response('ok');
        });
        Log::shouldReceive('channel')->with('performance')->never();
        config(['performance.enabled' => false, 'performance.sample_rate' => 1]);
        $this->get('/_timing-test')->assertOk();
        config(['performance.enabled' => true, 'performance.sample_rate' => 0]);
        $this->get('/_timing-test')->assertOk();
    }

    public function test_sampled_logs_count_each_request_separately_and_exclude_request_and_query_contents(): void
    {
        config(['performance.enabled' => true, 'performance.sample_rate' => 1]);
        $records = [];
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('performance')->twice()->andReturn($logger);
        $logger->shouldReceive('info')->twice()->withArgs(function ($message, $context) use (&$records): bool {
            $this->assertSame('HTTP request timing', $message);
            $records[] = $context;

            return true;
        });
        Route::post('/_timing-test/{secret}', function () {
            DB::select('select ? as private_value', ['customer-secret@example.test']);
            DB::select('select 2');

            return response('ok', 201);
        })->name('timing.test');
        DB::select('select 0');
        foreach (range(1, 2) as $attempt) {
            $this->post('/_timing-test/private-token?email=private@example.test', ['password' => 'secret-password'])
                ->assertStatus(201)->assertHeaderMissing('Server-Timing');
            $this->assertFalse(request()->attributes->has(RequestTimings::ATTRIBUTE));
            DB::select('select 3');
        }
        foreach ($records as $record) {
            $this->assertSame(['route', 'method', 'status', 'duration_ms', 'db_ms', 'query_count'], array_keys($record));
            $this->assertSame('timing.test', $record['route']);
            $this->assertSame('POST', $record['method']);
            $this->assertSame(201, $record['status']);
            $this->assertSame(2, $record['query_count']);
            $this->assertGreaterThanOrEqual(0, $record['duration_ms']);
            $this->assertGreaterThanOrEqual(0, $record['db_ms']);
            $this->assertStringNotContainsString('private', json_encode($record));
            $this->assertStringNotContainsString('secret', json_encode($record));
        }
    }

    public function test_error_responses_are_measured_and_logging_failures_do_not_break_requests(): void
    {
        config(['performance.enabled' => true, 'performance.sample_rate' => 1]);
        Route::get('/_timing-denied', fn () => abort(403))->name('timing.denied');
        Route::get('/_timing-ok', fn () => response('ok'))->name('timing.ok');
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('performance')->twice()->andReturn($logger);
        $logger->shouldReceive('info')->once()->withArgs(fn ($message, $context) => $context['status'] === 403
            && $context['route'] === 'timing.denied' && $context['query_count'] === 0);
        $logger->shouldReceive('info')->once()->withArgs(fn ($message, $context) => $context['status'] === 200)
            ->andThrow(new RuntimeException('Simulated log storage failure'));
        $this->get('/_timing-denied')->assertForbidden();
        $this->assertFalse(request()->attributes->has(RequestTimings::ATTRIBUTE));
        $this->get('/_timing-ok')->assertOk();
        $this->assertFalse(request()->attributes->has(RequestTimings::ATTRIBUTE));
    }

    public function test_unhandled_exception_also_discards_request_counters(): void
    {
        config(['performance.enabled' => true, 'performance.sample_rate' => 1]);
        $this->withoutExceptionHandling();
        Route::get('/_timing-exception', function () {
            DB::select('select 1');
            throw new RuntimeException('Unexpected failure');
        });
        try {
            $this->get('/_timing-exception');
            $this->fail('The exception should propagate with exception handling disabled.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unexpected failure', $exception->getMessage());
        }
        $this->assertFalse(request()->attributes->has(RequestTimings::ATTRIBUTE));
    }
}
