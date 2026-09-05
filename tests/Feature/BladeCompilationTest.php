<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeCompilationTest extends TestCase
{
    public function test_numeric_constraint_editor_and_checkout_views_compile(): void
    {
        foreach ([
            'layouts/app.blade.php',
            'layouts/public.blade.php',
            'layouts/partials/page-loader.blade.php',
            'dashboard.blade.php',
            'dashboard/upcoming-bookings.blade.php',
            'questionnaire/partials/numeric-constraints.blade.php',
            'questionnaire/partials/form.blade.php',
            'questionnaire/index.blade.php',
            'public/bookings/partials/questionnaire.blade.php',
            'public/bookings/details.blade.php',
            'tickets/index.blade.php',
            'tickets/show.blade.php',
            'appointment-types/partials/ticket-seat-block.blade.php',
            'appointment-types/partials/form.blade.php',
            'payments/settings.blade.php',
            'bookings/show.blade.php',
            'public/bookings/received.blade.php',
            'public/payments/return.blade.php',
            'coupons/index.blade.php',
            'coupons/show.blade.php',
            'coupons/partials/applicability.blade.php',
            'public/coupons/index.blade.php',
            'public/coupons/show.blade.php',
            'public/coupons/password.blade.php',
            'public/coupons/view.blade.php',
            'public/coupons/return.blade.php',
            'resources/partials/form.blade.php',
            'resources/index.blade.php',
            'public/appointment-types/show.blade.php',
        ] as $view) {
            $path = resource_path('views/'.$view);
            $compiled = app('blade.compiler')->compileString(file_get_contents($path));
            $this->assertPhpSyntaxValid($compiled, $path.' (compiled)');
        }
    }

    public function test_public_booking_manage_wrapper_compiles_and_php_views_are_valid(): void
    {
        $compiler = app('blade.compiler');
        $bladePath = resource_path('views/public/bookings/manage.blade.php');
        $compiled = $compiler->compileString(file_get_contents($bladePath));
        $this->assertPhpSyntaxValid($compiled, $bladePath.' (compiled)');

        foreach ([
            resource_path('views/public/bookings/manage-content.php'),
            resource_path('views/public/bookings/partials/schedule-proposals-content.php'),
            resource_path('views/public/bookings/partials/questionnaire-answers-content.php'),
        ] as $phpViewPath) {
            $this->assertPhpSyntaxValid(file_get_contents($phpViewPath), $phpViewPath);
        }
    }

    private function assertPhpSyntaxValid(string $php, string $label): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'view-lint-');
        file_put_contents($temp, $php);

        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $exitCode);
        @unlink($temp);

        $this->assertSame(
            0,
            $exitCode,
            "Invalid PHP for {$label}:\n".implode("\n", $output),
        );
    }
}
