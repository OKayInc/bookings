<?php

namespace App\Http\Controllers;

use App\Domain\Calendars\CalendarManager;
use App\Domain\Calendars\CalendarSyncService;
use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Models\CalendarConnection;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CalendarConnectionController extends Controller
{
    public function index(OrganizationContext $context, Request $request): View
    {
        $organization = $context->organization();
        $resources = $organization->resources()->with(['person', 'calendarConnections.calendars'])->orderBy('name');
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

        $state = Str::random(64);
        $request->session()->put('calendar_oauth', [
            'state' => $state,
            'provider' => $providerEnum->value,
            'resource_uuid' => $resource->uuid,
            'organization_uuid' => $context->organization()->uuid,
        ]);

        return redirect()->away($manager->provider($providerEnum)->authorizationUrl($state));
    }

    public function callback(string $provider, Request $request, OrganizationContext $context, CalendarManager $manager): RedirectResponse
    {
        $providerEnum = CalendarProvider::tryFrom($provider);
        abort_if($providerEnum === null, 404);
        $oauth = (array) $request->session()->pull('calendar_oauth', []);
        abort_unless(isset($oauth['state'], $oauth['provider'], $oauth['resource_uuid'], $oauth['organization_uuid']), 419, 'Calendar OAuth session expired.');
        abort_unless(hash_equals((string) $oauth['state'], (string) $request->query('state')), 419, 'Invalid calendar OAuth state.');
        abort_unless($oauth['provider'] === $providerEnum->value, 419);
        abort_unless($oauth['organization_uuid'] === $context->organization()->uuid, 403);

        if ($request->filled('error')) {
            return redirect()->route('calendar-connections.index')->with('error', 'Calendar authorization was not completed: '.$request->query('error'));
        }

        $resource = Resource::whereUuid((string) $oauth['resource_uuid'])->firstOrFail();
        $this->ensureSameOrganization($resource, $context);
        $this->authorize('calendar', $resource);

        try {
            $tokens = $manager->provider($providerEnum)->exchangeAuthorizationCode((string) $request->query('code'));
            $existing = CalendarConnection::query()->where('resource_id', $resource->getKey())->where('provider', $providerEnum->value)->first();
            $connection = CalendarConnection::query()->updateOrCreate(
                ['resource_id' => $resource->getKey(), 'provider' => $providerEnum->value],
                [
                    'organization_id' => $context->organization()->getKey(),
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
            return redirect()->route('calendar-connections.index')->with('error', 'Calendar connection failed: '.$e->getMessage());
        }

        return redirect()->route('calendar-connections.index')->with('success', $providerEnum->label().' connected to '.$resource->name.'.');
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

    private function ensureSameOrganization(Resource $resource, OrganizationContext $context): void
    {
        abort_unless(hash_equals($resource->organization_id, $context->organization()->getKey()), 404);
    }

    private function ensureConnectionOrganization(CalendarConnection $connection, OrganizationContext $context): void
    {
        abort_unless(hash_equals($connection->organization_id, $context->organization()->getKey()), 404);
    }
}
