<?php

namespace Tests\Feature;

use App\Domain\Plans\PlanEntitlementService;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\PageReadCache;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageReadOptimizationTest extends TestCase
{
    // Exercise the real HTTP read cache; it deliberately bypasses transactions.
    use DatabaseMigrations;

    public function test_dashboard_reuses_membership_for_resolution_permissions_and_navigation(): void
    {
        [$user, $organization] = $this->owner();
        [$response, $queries] = $this->queries(fn () => $this->actingAs($user)->get(route('dashboard')));
        $response->assertOk()->assertSee('Startup checklist')->assertSee($organization->name);
        $membershipReads = array_filter($queries, fn ($sql) => str_contains($sql, 'from `organization_memberships`')
            && str_contains($sql, '`person_id`'));
        $this->assertCount(1, $membershipReads);
        $this->assertFalse(app(PageReadCache::class)->enabled());
    }

    public function test_roles_are_isolated_by_user_organization_and_request(): void
    {
        [$owner, $first] = $this->owner();
        [$other, $second] = $this->owner();
        OrganizationMembership::create(['organization_id' => $first->getKey(), 'person_id' => $other->person_id,
            'role' => 'employee', 'status' => 'active']);
        Route::middleware('web')->get('/_test/page-roles', function (Request $request) use ($first, $second) {
            return response()->json([
                'first' => $request->user()->can('update', $first),
                'second' => $request->user()->can('update', $second),
                'check_in' => $request->user()->can('checkInTickets', $first),
            ]);
        });
        $this->actingAs($owner)->get('/_test/page-roles')->assertExactJson(['first' => true, 'second' => false, 'check_in' => true]);
        $this->actingAs($other)->get('/_test/page-roles')->assertExactJson(['first' => false, 'second' => true, 'check_in' => true]);
        OrganizationMembership::query()->where('person_id', $other->person_id)->where('organization_id', $first->getKey())
            ->update(['status' => 'suspended']);
        $this->get('/_test/page-roles')->assertExactJson(['first' => false, 'second' => true, 'check_in' => false]);
    }

    public function test_bulk_writes_and_rollback_cannot_leave_stale_permissions_in_a_page(): void
    {
        [$user, $organization] = $this->owner();
        Route::middleware('web')->get('/_test/page-role-mutations', function () use ($user, $organization) {
            $before = $user->can('update', $organization);
            $membership = fn () => DB::table('organization_memberships')->where('person_id', $user->person_id);
            $membership()->update(['role' => 'employee']);
            $afterWrite = $user->can('update', $organization);
            DB::beginTransaction();
            try {
                $membership()->update(['role' => 'owner']);
                $duringTransaction = $user->can('update', $organization);
                $membership()->update(['role' => 'employee']);
                $afterSecondWrite = $user->can('update', $organization);
            } finally {
                DB::rollBack();
            }

            return response()->json([$before, $afterWrite, $duringTransaction, $afterSecondWrite, $user->can('update', $organization)]);
        });
        $this->actingAs($user)->get('/_test/page-role-mutations')->assertExactJson([true, false, true, false, false]);
    }

    public function test_plan_reads_are_reused_but_grant_and_trial_boundaries_are_evaluated_live(): void
    {
        $start = CarbonImmutable::parse('2026-11-05 12:00 UTC');
        $this->travelTo($start);
        $organization = Organization::factory()->create(['plan_tier' => 'free']);
        $organization->planSubscription()->create(['provider' => 'stripe', 'status' => 'trialing',
            'billing_interval' => 'monthly', 'trial_ends_at_utc' => $start->addMinute()]);
        $organization->planGrants()->create(['source' => 'owner', 'reason' => 'Scheduled grant',
            'starts_at_utc' => $start->addMinutes(2), 'ends_at_utc' => $start->addMinutes(3)]);
        Route::middleware('web')->get('/_test/page-plans', function () use ($organization, $start) {
            $plans = app(PlanEntitlementService::class);
            $levels = [];
            foreach ([0, 0, 1, 2, 3] as $minutes) {
                $this->travelTo($start->addMinutes($minutes));
                $levels[] = $plans->for($organization)->level->value;
            }

            return response()->json($levels);
        });
        [$response, $queries] = $this->queries(fn () => $this->get('/_test/page-plans'));
        $response->assertExactJson(['business', 'business', 'free', 'complimentary', 'free']);
        $this->assertCount(2, array_filter($queries, fn ($sql) => str_contains($sql, 'from `organization_plan_')));
        $this->travelBack();
    }

    public function test_plan_bulk_changes_and_missing_subscriptions_are_handled_without_stale_results(): void
    {
        $organization = Organization::factory()->create(['plan_tier' => 'paid']);
        $second = Organization::factory()->create(['plan_tier' => 'free']);
        Route::middleware('web')->get('/_test/page-plan-mutations', function () use ($organization, $second) {
            $plans = app(PlanEntitlementService::class);
            $levels = [$plans->for($organization)->level->value, $plans->for($organization)->level->value,
                $plans->for($second)->level->value];
            $organization->planSubscription()->create(['provider' => 'stripe', 'status' => 'cancelled', 'billing_interval' => 'monthly']);
            $levels[] = $plans->for($organization)->level->value;
            DB::table('organization_plan_subscriptions')->where('organization_id', $organization->getKey())->update(['status' => 'active']);
            $levels[] = $plans->for($organization)->level->value;

            return response()->json($levels);
        });
        $this->get('/_test/page-plan-mutations')->assertExactJson(['business', 'business', 'free', 'free', 'business']);
    }

    public function test_post_requests_and_code_outside_a_response_do_not_reuse_reads(): void
    {
        $count = 0;
        $read = function () use (&$count) { return ++$count; };
        Route::middleware('web')->post('/_test/page-post', function () use ($read) {
            $cache = app(PageReadCache::class);

            return response()->json([$cache->remember('probe', $read), $cache->remember('probe', $read)]);
        });
        $this->post('/_test/page-post')->assertExactJson([1, 2]);
        $this->assertSame(3, app(PageReadCache::class)->remember('probe', $read));
        $this->assertSame(4, app(PageReadCache::class)->remember('probe', $read));
    }

    private function owner(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => 'owner', 'status' => 'active']);
        $user->forceFill(['active_organization_id' => $organization->getKey()])->save();

        return [$user, $organization];
    }

    private function queries(callable $read): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = $read();

            return [$result, array_column(DB::getQueryLog(), 'query')];
        } finally {
            DB::disableQueryLog();
        }
    }
}
