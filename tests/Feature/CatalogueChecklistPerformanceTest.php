<?php

namespace Tests\Feature;

use App\Domain\Appointments\AppointmentTypeSummaryService;
use App\Domain\Configuration\ConfigurationCache;
use App\Domain\Organizations\OrganizationStartupChecklist;
use App\Domain\Resources\ResourceDepositService;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueChecklistPerformanceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_public_catalogue_and_checklist_queries_do_not_grow_per_type_or_resource(): void
    {
        $organization = Organization::factory()->create();
        $this->type($organization, 'first');
        $url = route('public.appointment-types.index', $organization->slug);
        [$response, $oneCatalogue] = $this->queries(fn () => $this->get($url));
        $response->assertOk()->assertSee('refundable deposit');
        [, $oneChecklist] = $this->queries(fn () => app(OrganizationStartupChecklist::class)->forOrganization($organization->fresh(), true));

        foreach (range(2, 8) as $number) {
            $this->type($organization, 'type-'.$number);
        }
        [$response, $manyCatalogue] = $this->queries(fn () => $this->get($url));
        $response->assertOk()->assertSee('type-8');
        [$checklist, $manyChecklist] = $this->queries(fn () => app(OrganizationStartupChecklist::class)->forOrganization($organization->fresh(), true));

        $this->assertLessThanOrEqual($oneCatalogue + 1, $manyCatalogue, 'Catalogue queries must be batched across types.');
        $this->assertLessThanOrEqual($oneChecklist + 1, $manyChecklist, 'Checklist queries must be batched across types/resources.');
        $this->assertTrue(collect($checklist['steps'])->firstWhere('id', 'payments')['required']);
    }

    public function test_warm_catalogue_needs_no_pricing_queries_and_resource_edits_refresh_prices(): void
    {
        $organization = Organization::factory()->create();
        [, $room] = $this->type($organization, 'rental');
        $cache = app(ConfigurationCache::class);
        $cache->publicTypes($organization);
        [$price, $queries] = $this->queries(function () use ($cache, $organization) {
            return app(AppointmentTypeSummaryService::class)->pricing($cache->publicTypes($organization)->first());
        });
        $this->assertSame(0, $queries);
        $this->assertStringContainsString('refundable deposit', $price);
        $room->update(['deposit_amount_minor' => 900]);
        $this->assertSame(900, app(ResourceDepositService::class)->total($cache->publicTypes($organization)->first()));
    }

    public function test_shared_resource_defaults_remain_tenant_specific_and_transactions_read_fresh_values(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        [$firstType, $room] = $this->type($first, 'first');
        $room->organizations()->attach($second, ['is_required_by_default' => false]);
        $secondType = $second->appointmentTypes()->create(['name' => 'Second rental', 'slug' => 'second',
            'visibility' => 'public', 'pricing_mode' => 'free', 'is_active' => true]);
        $secondType->resources()->attach($room, ['requirement_mode' => 'inherit']);
        $cache = app(ConfigurationCache::class);
        $firstSnapshot = $cache->publicTypes($first)->first();
        $this->assertSame(500, app(ResourceDepositService::class)->total($firstSnapshot));
        $this->assertSame(0, app(ResourceDepositService::class)->total($cache->publicTypes($second)->first()));

        DB::beginTransaction();
        try {
            DB::table('organization_resources')->where('organization_id', $first->getKey())
                ->where('resource_id', $room->getKey())->update(['is_required_by_default' => false]);
            $this->assertSame(0, app(ResourceDepositService::class)->total($firstSnapshot));
        } finally {
            DB::rollBack();
        }
        $this->assertSame(500, app(ResourceDepositService::class)->total($cache->publicTypes($first)->first()));
    }

    public function test_checklist_reflects_pivot_changes_and_expired_openings_on_the_next_read(): void
    {
        $organization = Organization::factory()->create();
        [$type, $room] = $this->type($organization, 'rental');
        $schedule = $organization->availabilitySchedules()->create(['scope_type' => 'organization',
            'scope_id' => $organization->getKey(), 'timezone' => 'UTC', 'is_active' => true]);
        $end = now()->addMinutes(2);
        $schedule->exceptions()->create(['mode' => 'available', 'starts_at_utc' => now()->subMinute(),
            'ends_at_utc' => $end, 'timezone' => 'UTC']);
        $check = fn () => collect(app(OrganizationStartupChecklist::class)->forOrganization($organization->fresh(), true)['steps'])->keyBy('id');
        $this->assertTrue($check()['assignments']['complete']);
        $room->update(['is_active' => false]);
        $this->assertFalse($check()['assignments']['complete']);
        $type->resources()->updateExistingPivot($room, ['requirement_mode' => 'optional']);
        $this->assertTrue($check()['assignments']['complete']);
        $this->travelTo($end->copy()->addSecond());
        $this->assertFalse($check()['availability']['complete']);
        $this->travelBack();
    }

    private function type(Organization $organization, string $name): array
    {
        $type = $organization->appointmentTypes()->create(['name' => $name, 'slug' => $name,
            'visibility' => 'public', 'pricing_mode' => 'free', 'is_active' => true]);
        $room = Resource::create(['organization_id' => $organization->getKey(), 'name' => $name.' room',
            'type' => 'room', 'is_required_by_default' => true, 'deposit_amount_minor' => 500, 'is_active' => true]);
        $extra = Resource::create(['organization_id' => $organization->getKey(), 'name' => $name.' extra',
            'type' => 'equipment', 'is_required_by_default' => false, 'deposit_amount_minor' => 700, 'is_active' => true]);
        $type->resources()->attach($room, ['requirement_mode' => 'inherit']);
        $type->resources()->attach($extra, ['requirement_mode' => 'optional']);
        $question = $type->questions()->create(['type' => 'radio', 'label' => 'Extra equipment?', 'is_active' => true]);
        $yes = $question->options()->create(['label' => 'Yes', 'value' => 'yes', 'position' => 1]);
        $no = $question->options()->create(['label' => 'No', 'value' => 'no', 'position' => 2]);
        $rule = $question->resourceRequirementRule()->create(['trigger_option_id' => $yes->getKey(),
            'unavailable_default_option_id' => $no->getKey(), 'group_name' => 'Extras', 'fulfillment_mode' => 'all']);
        $rule->resources()->attach($extra);

        return [$type, $room];
    }

    private function queries(callable $read): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = $read();

            return [$result, count(DB::getQueryLog())];
        } finally {
            DB::disableQueryLog();
        }
    }
}
