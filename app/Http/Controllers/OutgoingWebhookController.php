<?php
namespace App\Http\Controllers;

use App\Domain\Webhooks\WebhookDestination;
use App\Domain\Webhooks\WebhookPublisher;
use App\Enums\OrganizationPlanTier;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OutgoingWebhookController extends Controller
{
    private function organization(): Organization
    {
        $org = app(OrganizationContext::class)->organization();
        $this->authorize('update', $org);
        return $org;
    }
    private function paid(Organization $org): void
    {
        abort_unless($org->plan_tier === OrganizationPlanTier::Paid, 403, 'Outgoing webhooks require a paid organization plan.');
    }
    private function endpoint(Organization $org, WebhookEndpoint $endpoint): void
    {
        abort_unless(hash_equals($org->getKey(), $endpoint->organization_id), 404);
    }
    private function page(Organization $org, ?string $newSecret = null)
    {
        return response()->view('webhooks.index', ['organization' => $org, 'newSecret' => $newSecret,
            'eventTypes' => WebhookPublisher::EVENTS,
            'endpoints' => WebhookEndpoint::where('organization_id', $org->getKey())->orderBy('created_at')->get(),
            'deliveries' => WebhookDelivery::where('organization_id', $org->getKey())->with('endpoint')->latest()->paginate(25),
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
    public function index() { return $this->page($this->organization()); }

    public function guide()
    {
        $this->organization();
        return view('webhooks.guide');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'url' => ['required', 'string', 'max:2048'],
            'events' => ['required', 'array', 'min:1', 'max:8'], 'events.*' => ['required', 'string', 'distinct', Rule::in(WebhookPublisher::EVENTS)]]);
        try { app(WebhookDestination::class)->host($data['url']); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['url' => $e->getMessage()]); }
        return $data;
    }
    public function store(Request $request)
    {
        $org = $this->organization(); $this->paid($org); $data = $this->validated($request);
        $secret = bin2hex(random_bytes(32));
        DB::transaction(function () use ($org, $data, $secret): void {
            Organization::whereKey($org->getKey())->lockForUpdate()->firstOrFail();
            if (WebhookEndpoint::where('organization_id', $org->getKey())->count() >= 10) {
                throw ValidationException::withMessages(['name' => 'An organization can have up to 10 webhook endpoints.']);
            }
            WebhookEndpoint::create($data + ['organization_id' => $org->getKey(), 'secret' => $secret, 'is_active' => true, 'version' => 1]);
        });
        return $this->page($org, $secret);
    }
    public function update(Request $request, WebhookEndpoint $webhook)
    {
        $org = $this->organization(); $this->endpoint($org, $webhook); $this->paid($org);
        $data = $this->validated($request);
        $this->change($webhook, $data);
        return redirect()->route('webhooks.index')->with('success', 'Webhook updated. Outstanding deliveries for the previous settings were cancelled.');
    }
    private function change(WebhookEndpoint $endpoint, array $data): void
    {
        DB::transaction(function () use ($endpoint, $data): void {
            $locked = WebhookEndpoint::whereKey($endpoint->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($data); $locked->version++; $locked->save();
            $locked->deliveries()->whereIn('status', ['pending', 'failed'])->update(['status' => 'skipped', 'last_error' => 'Endpoint settings changed.']);
        });
    }
    public function toggle(WebhookEndpoint $webhook)
    {
        $org = $this->organization(); $this->endpoint($org, $webhook);
        if (! $webhook->is_active) { $this->paid($org); }
        $this->change($webhook, ['is_active' => ! $webhook->is_active]);
        return redirect()->route('webhooks.index')->with('success', 'Webhook status updated.');
    }
    public function rotate(WebhookEndpoint $webhook)
    {
        $org = $this->organization(); $this->endpoint($org, $webhook); $this->paid($org);
        $secret = bin2hex(random_bytes(32)); $this->change($webhook, ['secret' => $secret]);
        return $this->page($org, $secret);
    }
    public function destroy(WebhookEndpoint $webhook)
    {
        $org = $this->organization(); $this->endpoint($org, $webhook);
        $webhook->delete();
        return redirect()->route('webhooks.index')->with('success', 'Webhook and its delivery history deleted.');
    }
    public function test(WebhookEndpoint $webhook, WebhookPublisher $publisher)
    {
        $org = $this->organization(); $this->endpoint($org, $webhook); $this->paid($org);
        abort_unless($webhook->is_active, 409, 'Enable this endpoint before sending a test.');
        $publisher->publish($org, 'webhook.test', ['message' => 'Appointment.to webhook test'], $webhook);
        return redirect()->route('webhooks.index')->with('success', 'Test queued for the next scheduled delivery run.');
    }
    public function show(WebhookDelivery $delivery)
    {
        $org = $this->organization(); abort_unless(hash_equals($org->getKey(), $delivery->organization_id), 404);
        return response()->view('webhooks.delivery', ['delivery' => $delivery, 'attempts' => $delivery->attemptsLog()->paginate(20)])
            ->header('Cache-Control', 'no-store, private');
    }
    public function retry(WebhookDelivery $delivery)
    {
        $org = $this->organization(); $this->paid($org);
        abort_unless(hash_equals($org->getKey(), $delivery->organization_id), 404);
        DB::transaction(function () use ($delivery): void {
            $locked = WebhookDelivery::whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
            $endpoint = $locked->endpoint;
            abort_unless($locked->status === 'failed' && $endpoint && $endpoint->is_active && $endpoint->version === $locked->endpoint_version, 409, 'Only failed deliveries for unchanged, enabled endpoints can be retried.');
            $locked->update(['status' => 'pending', 'cycle_attempts' => 0, 'available_at' => now('UTC'), 'last_error' => null]);
        });
        return redirect()->route('webhooks.index')->with('success', 'Delivery queued again with the same event ID and payload.');
    }
}
