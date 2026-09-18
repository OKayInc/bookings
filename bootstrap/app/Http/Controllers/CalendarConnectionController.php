<?php

namespace App\Http\Controllers;

use App\Domain\Calendars\CalendarManager;
use App\Domain\Calendars\CalendarSyncService;
use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Models\CalendarConnection;
use App\Models\CalendarOauthState;
use App\Models\Resource;
use App\Support\Organizations\ActiveOrganizationResolver;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CalendarConnectionController extends Controller
{
    public function index(OrganizationContext $context, Request $request): View
    {
        $organization = $context->organization();
        $resources = $organization->resources()->with([
            'person',
            'calendarConnections' => fn ($query) => $query->where('organization_id', $organization->getKey()),
            'calendarConnections.calendars',
        ])->orderBy('name');
        if (! $request->user()->can('manageScheduling', $organization)) {
            $resources->where('person_id', $request->user()->person_id);
        }

        return view('calendars.index', [
            'resources' => $resources->get(),
            'providers' => CalendarProvider::cases(),
            'googleConfigured' => filled(config('calendars.google.client_id')) && filled(config('calendars.google.client_secret')),
            'microsoftConfigured' => filled(config('calendars.microsoft.client_id')) && filled(config('calendars.microsoft.client_secret')),
        ]);
    }

    public function connect(Resource $resource, string $provider, OrganizationContext $context, CalendarManager $manager, Request $request): RedirectResponse
    {
        $this->ensureSameOrganization($resource, $context);
        $this->authorize('calendar', $resource);
        $providerEnum = CalendarProvider::tryFrom($provider);
        abort_if($providerEnum === null, 404);

        $state = Str::random(80);
        $ttlMinutes = max(5, (int) config('calendars.oauth_state_ttl_minutes', 15));

        CalendarOauthState::query()
            ->where('user_id', $request->user()->getKey())
            ->where('resource_id', $resource->getKey())
            ->where('provider', $providerEnum->value)
            ->whereNull('consumed_at_utc')
            ->delete();

        CalendarOauthState::create([
            'user_id' => $request->user()->getKey(),
            'organization_id' => $context->organization()->getKey(),
            'resource_id' => $resource->getKey(),
            'provider' => $providerEnum->value,
            'state_hash' => hash('sha256', $state),
            'expires_at_utc' => now('UTC')->addMinutes($ttlMinutes),
        ]);

        // Opportunistic pruning keeps this tiny transaction table bounded even if cron is unavailable.
        CalendarOauthState::query()
            ->where('expires_at_utc', '<', now('UTC')->subDay())
            ->delete();

        return redirect()->away($manager->provider($providerEnum)->authorizationUrl($state));
    }

    public function callback(string $provider, Request $request, CalendarManager $manager): RedirectResponse
    {
        $providerEnum = CalendarProvider::tryFrom($provider);
        abort_if($providerEnum === null, 404);

        $rawState = (string) $request->query('state', '');
        abort_if($rawState === '', 419, 'Missing calendar OAuth state.');

        $oauth = DB::transaction(function () use ($rawState, $providerEnum): CalendarOauthState {
            $state = CalendarOauthState::query()
                ->where('state_hash', hash('sha256', $rawState))
                ->lockForUpdate()
                ->first();

            abort_unless($state !== null, 419, 'Calendar OAuth state is invalid or expired.');
            abort_unless($state->provider === $providerEnum, 419, 'Calendar OAuth provider mismatch.');
            abort_unless($state->consumed_at_utc === null, 419, 'Calendar OAuth state has already been used.');
            abort_unless($state->expires_at_utc->isFuture(), 419, 'Calendar OAuth state has expired.');

            $state->forceFill(['consumed_at_utc' => now('UTC')])->save();

            return $state->load(['user', 'organization', 'resource']);
        });

        if ($request->user() && ! $request->user()->is($oauth->user)) {
            abort(403, 'This calendar authorization belongs to a different backend user.');
        }

        Gate::forUser($oauth->user)->authorize('calendar', $oauth->resource);

        if ($request->filled('error')) {
            return $this->callbackRedirect(
                $request,
                $oauth,
                'error',
                'Calendar authorization was not completed: '.$request->query('error'),
            );
        }

        abort_unless($request->filled('code'), 422, 'Calendar provider did not return an authorization code.');

        try {
            $tokens = $manager->provider($providerEnum)->exchangeAuthorizationCode((string) $request->query('code'));
            $existing = CalendarConnection::query()
                ->where('organization_id', $oauth->organization->getKey())
                ->where('resource_id', $oauth->resource->getKey())
                ->where('provider', $providerEnum->value)
                ->first();

            $connection = CalendarConnection::query()->updateOrCreate(
                ['organization_id' => $oauth->organization->getKey(), 'resource_id' => $oauth->resource->getKey(), 'provider' => $providerEnum->value],
                [
                    'organization_id' => $oauth->organization->getKey(),
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $existing?->refresh_token,
                    'token_expires_at_utc' => isset($tokens['expires_in']) ? now('UTC')->addSeconds((int) $tokens['expires_in']) : null,
                    'scopes' => $tokens['scope'] ?? implode(' ', (array) config('calendars.'.$providerEnum->value.'.scopes', [])),
                    'status' => CalendarConnectionStatus::Active->value,
                    'last_error' => null,
                ],
            );
            $manager->refreshCalendars($connection);
        } catch (Throwable $e) {
            report($e);

            return $this->callbackRedirect(
                $request,
                $oauth,
                'error',
                'Calendar connection failed: '.$e->getMessage(),
            );
        }

        return $this->callbackRedirect(
            $request,
            $oauth,
            'success',
            $providerEnum->label().' connected to '.$oauth->resource->name.'.',
        );
    }

    public function refresh(CalendarConnection $connection, OrganizationContext $context, CalendarManager $manager): RedirectResponse
    {
        $this->ensureConnectionOrganization($connection, $context);
        $this->authorize('calendar', $connection->resource);
        try {
            $manager->refreshCalendars($connection);
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Calendar refresh failed: '.$e->getMessage());
        }
        return back()->with('success', 'Calendar list refreshed.');
    }

    public function destroy(CalendarConnection $connection, OrganizationContext $context, CalendarSyncService $sync): RedirectResponse
    {
        $this->ensureConnectionOrganization($connection, $context);
        $this->authorize('calendar', $connection->resource);
        $sync->deleteConnectionEvents($connection);
        $connection->delete();
        return back()->with('success', 'Calendar connection removed. Synchronized events were removed when the provider connection allowed it.');
    }

    private function callbackRedirect(Request $request, CalendarOauthState $oauth, string $flashKey, string $message): RedirectResponse
    {
        if ($request->user()?->is($oauth->user)) {
            app(ActiveOrganizationResolver::class)->select($request->user(), $oauth->organization, $request);

            return redirect()->route('calendar-connections.index')->with($flashKey, $message);
        }

        return redirect()->route('login')->with($flashKey, $message.' Sign in to continue.');
    }

    private function ensureSameOrganization(Resource $resource, OrganizationContext $context): void
    {
        abort_unless(
            $context->organization()->resources()->where('resources.id', $resource->getKey())->exists(),
            404,
        );
    }

    private function ensureConnectionOrganization(CalendarConnection $connection, OrganizationContext $context): void
    {
        abort_unless(hash_equals($connection->organization_id, $context->organization()->getKey()), 404);
    }
}
