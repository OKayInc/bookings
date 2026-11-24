<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\ConfigurationCache;
use App\Domain\Plans\PlanLimitService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResourceSharingController extends Controller
{
    private function owner(OrganizationContext $context, Request $request): Organization
    {
        $organization = $context->organization();
        abort_unless($organization->memberships()
            ->where('person_id', $request->user()->person_id)
            ->where('role', MembershipRole::Owner->value)
            ->where('status', MembershipStatus::Active->value)->exists(), 403);

        return $organization;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sharing' => $message]);
    }

    public function index(OrganizationContext $context, Request $request): View
    {
        $organization = $this->owner($context, $request);
        $id = $organization->getKey();
        $shares = DB::table('resource_share_invitations as s')
            ->join('resources as r', 'r.id', '=', 's.resource_id')
            ->join('organizations as o', 'o.id', '=', 'r.organization_id')
            ->leftJoin('organizations as recipient', 'recipient.id', '=', 's.recipient_organization_id')
            ->where(fn ($q) => $q->where('r.organization_id', $id)->orWhere('s.recipient_organization_id', $id))
            ->whereIn('s.status', ['pending', 'accepted'])
            ->select('s.id', 's.status', 's.expires_at', 'r.name as resource_name', 'o.name as owner_name', 'recipient.name as recipient_name')
            ->selectRaw('CASE WHEN r.organization_id = ? THEN s.recipient_email ELSE NULL END as recipient_email', [$id])
            ->orderByDesc('s.id')->paginate(25);

        return view('resources.sharing', [
            'resources' => Resource::query()->where('organization_id', $id)->orderBy('name')->get(),
            'shares' => $shares,
        ]);
    }

    public function invite(OrganizationContext $context, Request $request): RedirectResponse
    {
        $organization = $this->owner($context, $request);
        $data = $request->validate(['resource_uuid' => ['required', 'uuid'], 'email' => ['required', 'email', 'max:254']]);
        $token = bin2hex(random_bytes(32));

        DB::transaction(function () use ($organization, $data, $token): void {
            $resource = Resource::whereUuid($data['resource_uuid'])->where('organization_id', $organization->getKey())
                ->lockForUpdate()->firstOrFail();
            DB::table('resource_share_invitations')->insert([
                'resource_id' => $resource->getKey(),
                'recipient_email' => strtolower(trim($data['email'])),
                'token_hash' => hash('sha256', $token),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('resource-sharing.index')->with('share_code', $token);
    }

    public function accept(OrganizationContext $context, Request $request, PlanLimitService $limits): RedirectResponse
    {
        $organization = $this->owner($context, $request);
        $data = $request->validate(['code' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/']]);
        $hash = hash('sha256', $data['code']);
        DB::transaction(function () use ($organization, $request, $hash, $limits): void {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $candidate = DB::table('resource_share_invitations')->where('token_hash', $hash)->first();
            if (! $candidate) {
                $this->fail('Invalid or expired invitation.');
            }
            $resource = Resource::query()->whereKey($candidate->resource_id)->lockForUpdate()->firstOrFail();
            $share = DB::table('resource_share_invitations')->where('id', $candidate->id)->lockForUpdate()->first();
            if (! $share || $share->status !== 'pending' || now()->gte(\Illuminate\Support\Carbon::parse($share->expires_at))
                || ! hash_equals($share->recipient_email, strtolower(trim($request->user()->email)))) {
                $this->fail('Invalid or expired invitation.');
            }
            if (hash_equals($resource->organization_id, $organization->getKey()) || $resource->isAvailableToOrganization($organization)) {
                $this->fail('This organization already has access to the resource.');
            }
            if ($resource->organization->currency !== $organization->currency) {
                $this->fail('External resource sharing currently requires matching organization currencies.');
            }
            if ($resource->is_active) {
                $limits->assertCanActivateResource($organization, $resource->type === 'person');
            }
            $resource->organizations()->attach($organization->getKey(), [
                'is_required_by_default' => $resource->is_required_by_default,
            ]);
            DB::table('resource_share_invitations')->where('id', $share->id)->update([
                'status' => 'accepted', 'recipient_organization_id' => $organization->getKey(), 'updated_at' => now(),
            ]);
            $this->invalidate($resource, $organization->getKey());
        }, 3);

        return redirect()->route('resource-sharing.index')->with('success', 'Resource sharing accepted. Configure its availability before using it.');
    }

    public function revoke(OrganizationContext $context, Request $request, string $invitation): RedirectResponse
    {
        $organization = $this->owner($context, $request);
        DB::transaction(function () use ($organization, $invitation): void {
            $candidate = DB::table('resource_share_invitations')->where('id', $invitation)->first();
            abort_unless($candidate, 404);
            if ($candidate->recipient_organization_id !== null) {
                Organization::query()->whereKey($candidate->recipient_organization_id)->lockForUpdate()->firstOrFail();
            }
            $resource = Resource::query()->whereKey($candidate->resource_id)->lockForUpdate()->firstOrFail();
            $share = DB::table('resource_share_invitations')->where('id', $invitation)->lockForUpdate()->first();
            abort_unless($share && (hash_equals($resource->organization_id, $organization->getKey())
                || ($share->recipient_organization_id !== null && hash_equals($share->recipient_organization_id, $organization->getKey()))), 404);
            abort_unless(in_array($share->status, ['pending', 'accepted'], true), 404);
            if ($share->status === 'accepted') {
                $recipient = $share->recipient_organization_id;
                // Never silently change existing appointment definitions or reservations.
                if ($resource->appointmentTypes()->where('appointment_types.organization_id', $recipient)->exists()
                    || $resource->conditionalRequirementRules()->whereHas('question.appointmentType', fn ($q) => $q->where('organization_id', $recipient))->exists()
                    || $resource->appointments()->where('appointments.organization_id', $recipient)->where('appointments.status', 'scheduled')->where('appointments.blocked_ends_at_utc', '>', now())->exists()
                    || $resource->bookingHolds()->where('booking_holds.organization_id', $recipient)->where('booking_holds.status', 'active')->where('booking_holds.expires_at_utc', '>', now())->exists()
                    || $resource->calendarConnections()->where('organization_id', $recipient)->exists()
                    || \App\Models\CalendarOauthState::query()->where('organization_id', $recipient)->where('resource_id', $resource->getKey())->exists()) {
                    $this->fail('Remove the receiving organization’s appointment assignments, active reservations and calendar connections before ending this share.');
                }
                $resource->availabilitySchedules()->where('organization_id', $recipient)->delete();
                $resource->organizations()->detach($recipient);
                $this->invalidate($resource, $recipient);
            }
            DB::table('resource_share_invitations')->where('id', $share->id)->update(['status' => 'revoked', 'updated_at' => now()]);
        }, 3);

        return redirect()->route('resource-sharing.index')->with('success', 'Resource sharing ended.');
    }

    private function invalidate(Resource $resource, string $recipient): void
    {
        app(ConfigurationCache::class)->invalidateAfterCommit($recipient);
        app(ConfigurationCache::class)->invalidateAfterCommit($resource->organization_id);
        app(\App\Domain\Availability\ResourceAvailabilityCache::class)->invalidate([$recipient, $resource->organization_id], [$resource->getKey()]);
    }
}
