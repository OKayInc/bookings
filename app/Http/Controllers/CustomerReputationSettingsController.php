<?php

namespace App\Http\Controllers;

use App\Models\CustomerReputationSetting;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReputationSettingsController extends Controller
{
    public function edit(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $settings = $organization->customerReputationSetting
            ?? CustomerReputationSetting::defaultsFor($organization);

        return view('customers.settings', compact('organization', 'settings'));
    }

    public function update(Request $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);

        $data = $request->validate([
            'post_appointment_review_enabled' => ['nullable', 'boolean'],
            'review_roles' => ['required', 'array', 'min:1'],
            'review_roles.*' => ['in:owner,administrator,manager'],
            'blacklist_mode' => ['required', 'in:disabled,suggest,automatic'],
            'blacklist_no_show_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'blacklist_window_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'whitelist_mode' => ['required', 'in:disabled,suggest,automatic'],
            'whitelist_success_threshold' => ['required', 'integer', 'min:1', 'max:1000'],
            'whitelist_min_revenue' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'whitelist_window_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'whitelist_max_no_shows' => ['required', 'integer', 'min:0', 'max:100'],
            'minimum_reviewed_appointments' => ['required', 'integer', 'min:1', 'max:1000'],
            'policy_entry_expiration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $organization->customerReputationSetting()->updateOrCreate([], [
            'post_appointment_review_enabled' => $request->boolean('post_appointment_review_enabled'),
            'review_roles' => array_values($data['review_roles']),
            'blacklist_mode' => $data['blacklist_mode'],
            'blacklist_no_show_threshold' => $data['blacklist_no_show_threshold'],
            'blacklist_window_days' => $data['blacklist_window_days'] ?? null,
            'whitelist_mode' => $data['whitelist_mode'],
            'whitelist_success_threshold' => $data['whitelist_success_threshold'],
            'whitelist_min_revenue_minor' => (int) round(((float) $data['whitelist_min_revenue']) * 100),
            'whitelist_window_days' => $data['whitelist_window_days'] ?? null,
            'whitelist_max_no_shows' => $data['whitelist_max_no_shows'],
            'minimum_reviewed_appointments' => $data['minimum_reviewed_appointments'],
            'policy_entry_expiration_days' => $data['policy_entry_expiration_days'] ?? null,
        ]);

        return back()->with('success', 'Customer reputation policies saved.');
    }
}
