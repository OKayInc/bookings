<?php

namespace App\Http\Controllers;

use App\Support\Organizations\OrganizationContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(OrganizationContext $context): View
    {
        $organization = $context->organization();

        return view('dashboard', [
            'organization' => $organization,
            'resourceCount' => $organization->resources()->count(),
            'appointmentTypeCount' => $organization->appointmentTypes()->count(),
            'memberCount' => $organization->memberships()->count(),
        ]);
    }
}
