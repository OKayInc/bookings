<?php

namespace App\Http\Controllers;

use App\Domain\Organizations\GuidedOrganizationSetup;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Support\Organizations\ActiveOrganizationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request, ActiveOrganizationResolver $resolver): View|RedirectResponse
    {
        $organization = $this->organization($request, $resolver);

        if ($organization->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $this->authorize('update', $organization);

        return view('onboarding.show', compact('organization'));
    }

    public function store(
        CompleteOnboardingRequest $request,
        ActiveOrganizationResolver $resolver,
        GuidedOrganizationSetup $guidedSetup,
    ): RedirectResponse {
        $organization = $this->organization($request, $resolver);
        $this->authorize('update', $organization);

        $starter = DB::transaction(function () use ($request, $organization, $guidedSetup): ?AppointmentType {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();

            if ($organization->onboarding_completed_at !== null) {
                return $organization->appointmentTypes()->oldest()->first();
            }

            $starter = $organization->appointmentTypes()->oldest()->first();

            if (! $starter) {
                $starter = $guidedSetup->create(
                    $organization,
                    $request->user()->person,
                    $request->validated(),
                );
            }

            $organization->forceFill(['onboarding_completed_at' => now()])->save();

            return $starter;
        });

        if ($starter) {
            return redirect()
                ->route('appointment-types.edit', $starter)
                ->with('success', 'Your starter appointment is ready. Review anything you want to fine-tune.');
        }

        return redirect()->route('dashboard')->with('success', 'Setup completed.');
    }

    public function skip(Request $request, ActiveOrganizationResolver $resolver): RedirectResponse
    {
        $organization = $this->organization($request, $resolver);
        $this->authorize('update', $organization);

        $organization->forceFill(['onboarding_completed_at' => now()])->save();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Guided setup skipped. You can configure your organization manually.');
    }

    private function organization(Request $request, ActiveOrganizationResolver $resolver): Organization
    {
        $organization = $resolver->resolve($request->user(), $request);

        abort_unless($organization, 404, 'No active organization was found.');

        return $organization;
    }
}
