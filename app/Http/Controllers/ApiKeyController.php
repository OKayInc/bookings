<?php

namespace App\Http\Controllers;

use App\Domain\Api\ApiKeyService;
use App\Domain\Plans\PlanEntitlementService;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request, OrganizationContext $context)
    {
        return response()->view('api-keys.index', ['organization' => $context->organization(), 'newKey' => null, 'keyKind' => null])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request, OrganizationContext $context, ApiKeyService $keys)
    {
        $data = $request->validate(['kind' => ['required', 'in:client,organization'], 'action' => ['required', 'in:regenerate,revoke']]);
        $organization = $context->organization();
        $subject = $request->user();
        if ($data['kind'] === 'organization') {
            $this->authorize('update', $organization);
            $subject = $organization;
        }
        if ($data['action'] === 'revoke') {
            $keys->revoke($subject);
            return redirect()->route('api-keys.index')->with('success', 'API key revoked.');
        }
        abort_unless(app(PlanEntitlementService::class)->hasBusinessFeatures($organization), 403, 'API access requires Business or Complimentary Unlimited.');
        $newKey = $keys->regenerate($subject);
        // Render directly: do not persist plaintext in sessions, logs or URLs.
        return response()->view('api-keys.index', ['organization' => $organization, 'newKey' => $newKey, 'keyKind' => $data['kind']])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
