<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M9R6PageLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_layout_includes_the_global_page_loader(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="page-loader"', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee(asset('js/page-loader.js').'?v=m9-r6', false)
            ->assertSee('rel="preconnect" href="https://cdn.jsdelivr.net"', false);
    }

    public function test_public_layout_includes_the_global_page_loader(): void
    {
        $organization = Organization::factory()->create(['slug' => 'm9-r6']);

        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()
            ->assertSee('id="page-loader"', false)
            ->assertSee(asset('js/page-loader.js').'?v=m9-r6', false);
    }
}
