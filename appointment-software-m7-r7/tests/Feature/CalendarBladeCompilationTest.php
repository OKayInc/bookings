<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalendarBladeCompilationTest extends TestCase
{
    public function test_m7_calendar_blade_views_compile_to_valid_php(): void
    {
        $compiler = app('blade.compiler');
        foreach ([
            resource_path('views/calendars/index.blade.php'),
            resource_path('views/appointment-types/calendars/edit.blade.php'),
        ] as $bladePath) {
            $compiled = $compiler->compileString(file_get_contents($bladePath));
            $temp = tempnam(sys_get_temp_dir(), 'calendar-view-lint-');
            file_put_contents($temp, $compiled);
            $output = []; $exitCode = 0;
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $exitCode);
            @unlink($temp);
            $this->assertSame(0, $exitCode, "Invalid compiled PHP for {$bladePath}:\n".implode("\n", $output));
        }
    }
}
