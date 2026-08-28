<?php

namespace App\Http\Controllers;

use App\Support\Organizations\OrganizationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(OrganizationContext $context): View
    {
        $this->authorize('update', $context->organization());
        $database = false;
        $timezone = false;
        $cache = false;
        $details = [];

        try {
            DB::selectOne('SELECT 1 AS healthy');
            $database = true;
        } catch (Throwable $e) {
            $details['database'] = $e->getMessage();
        }

        try {
            $driver = DB::connection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne("SELECT CONVERT_TZ('2026-01-15 12:00:00', 'UTC', 'America/Toronto') AS converted");
                $timezone = ! empty($row?->converted);
                if (! $timezone) {
                    $details['timezone'] = 'CONVERT_TZ returned NULL. Load MariaDB timezone tables with mariadb-tzinfo-to-sql.';
                }
            } else {
                $details['timezone'] = 'Timezone database check applies to the MariaDB/MySQL driver only.';
            }
        } catch (Throwable $e) {
            $details['timezone'] = $e->getMessage();
        }

        try {
            $token = bin2hex(random_bytes(12));
            Cache::put('__m1_health', $token, 10);
            $cache = Cache::get('__m1_health') === $token;
            Cache::forget('__m1_health');
        } catch (Throwable $e) {
            $details['cache'] = $e->getMessage();
        }

        return view('admin.health', compact('database', 'timezone', 'cache', 'details'));
    }
}
