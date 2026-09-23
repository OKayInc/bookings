<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlanEntitlementService;
use App\Domain\Plans\PlanPromotionService;
use App\Models\Organization;
use App\Models\OrganizationPlanGrant;
use App\Models\PlanAuditEvent;
use App\Models\PlanPromotionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformPlanController extends Controller
{
    public function index(Request $request, PlanEntitlementService $entitlements): View
    {
        $query = Organization::query()
            ->with(['planSubscription', 'planGrants' => fn ($builder) => $builder->latest('starts_at_utc')])
            ->orderBy('name');
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$escaped.'%')
                ->orWhere('slug', 'like', '%'.$escaped.'%'));
        }

        $organizations = $query->paginate(30)->withQueryString();
        foreach ($organizations as $organization) {
            $organization->setRelation('effectivePlan', $entitlements->for($organization));
        }

        return view('platform.plans.index', [
            'organizations' => $organizations,
            'search' => $search,
            'promotionCodes' => PlanPromotionCode::query()->latest()->limit(30)->get(),
            'auditEvents' => PlanAuditEvent::query()->with(['organization', 'actor'])->latest('created_at')->limit(50)->get(),
            'newPromotionCode' => $request->session()->pull('new_plan_promotion_code'),
        ]);
    }

    public function grant(Request $request, Organization $organization, PlanPromotionService $promotions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $promotions->grant($organization, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Complimentary Unlimited granted to '.$organization->name.'.');
    }

    public function revoke(Request $request, OrganizationPlanGrant $grant, PlanPromotionService $promotions): RedirectResponse
    {
        $promotions->revoke($grant, $request->user());

        return back()->with('success', 'Complimentary grant revoked. Environment allowlists, if present, still take precedence.');
    }

    public function createPromotion(Request $request, PlanPromotionService $promotions): RedirectResponse
    {
        $data = $request->validate([
            'max_redemptions' => ['nullable', 'integer', 'between:1,100000'],
            'expires_at_utc' => ['nullable', 'date', 'after:now'],
        ]);
        $created = $promotions->create(
            $request->user(),
            isset($data['max_redemptions']) ? (int) $data['max_redemptions'] : null,
            isset($data['expires_at_utc']) ? new \DateTimeImmutable($data['expires_at_utc']) : null,
        );

        return back()
            ->with('new_plan_promotion_code', $created['plaintext'])
            ->with('success', 'Promotion code created. Copy it now; only its hash is stored.');
    }

    public function disablePromotion(Request $request, PlanPromotionCode $promotionCode, PlanPromotionService $promotions): RedirectResponse
    {
        $promotions->disableCode($promotionCode, $request->user());

        return back()->with('success', 'Promotion code disabled. Existing grants remain active until individually revoked.');
    }
}
