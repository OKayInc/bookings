<?php

namespace App\Http\Controllers;

use App\Domain\Customers\CustomerReputationService;
use App\Models\CustomerAccessEntry;
use App\Models\OrganizationContact;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $search = trim((string) $request->query('q'));

        $customers = $organization->contacts()
            ->when($search !== '', function ($query) use ($search): void {
                $email = OrganizationContact::normalizeEmail($search);
                $digits = preg_replace('/\D+/', '', $search);
                $query->where(function ($inner) use ($search, $email, $digits): void {
                    $inner->where('email_normalized', 'like', '%'.$email.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                    if ($digits !== '') {
                        $inner->orWhere('phone_normalized', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->withCount([
                'bookings',
                'bookings as successful_count' => fn ($q) => $q->whereHas('outcome', fn ($o) => $o->where('outcome', 'successful')),
                'bookings as no_show_count' => fn ($q) => $q->whereHas('outcome', fn ($o) => $o->where('outcome', 'no_show')),
            ])
            ->with(['accessEntries' => fn ($q) => $q->whereIn('status', ['active', 'suggested'])->latest()])
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function show(OrganizationContact $customer, OrganizationContext $context): View
    {
        $this->sameOrganization($customer, $context);
        $this->authorize('manageScheduling', $context->organization());

        $customer->load([
            'bookings.appointmentType', 'bookings.appointment', 'bookings.outcome.recordedBy',
            'bookings.couponRedemption.coupon', 'accessEntries.createdBy', 'accessEvents.actor',
        ]);

        $bookings = $customer->bookings->sortByDesc(fn ($booking) => $booking->appointment?->starts_at_utc);
        $summary = [
            'appointments' => $bookings->count(),
            'successful' => $bookings->where('outcome.outcome', 'successful')->count(),
            'no_shows' => $bookings->where('outcome.outcome', 'no_show')->count(),
            'cancelled' => $bookings->filter(fn ($b) => in_array($b->status->value, ['cancelled', 'declined'], true))->count(),
            'revenue_minor' => $bookings->sum(fn ($b) => $b->netPaidMinor()),
        ];

        return view('customers.show', compact('customer', 'bookings', 'summary'));
    }

    public function outcome(Request $request, OrganizationContact $customer, \App\Models\Booking $booking, OrganizationContext $context, CustomerReputationService $service): RedirectResponse
    {
        $this->sameOrganization($customer, $context);
        $this->authorize('manageScheduling', $context->organization());
        abort_unless(hash_equals((string) $customer->getKey(), (string) $booking->organization_contact_id), 404);
        $data = $request->validate(['outcome' => ['required', 'in:successful,no_show']]);
        $service->recordOutcome($booking, $data['outcome'], $request->user()->person, 'dashboard');

        return back()->with('success', 'Appointment outcome updated.');
    }

    public function access(Request $request, OrganizationContact $customer, OrganizationContext $context, CustomerReputationService $service): RedirectResponse
    {
        $this->sameOrganization($customer, $context);
        $this->authorize('manageScheduling', $context->organization());
        $data = $request->validate([
            'list_type' => ['required', 'in:whitelist,blacklist'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->addManual($customer, $data['list_type'], $request->user()->person, $data['reason'] ?? null);

        return back()->with('success', ucfirst($data['list_type']).' entry added.');
    }

    public function resolveAccess(Request $request, OrganizationContact $customer, CustomerAccessEntry $entry, OrganizationContext $context, CustomerReputationService $service): RedirectResponse
    {
        $this->sameOrganization($customer, $context);
        $this->authorize('manageScheduling', $context->organization());
        abort_unless(hash_equals((string) $customer->getKey(), (string) $entry->organization_contact_id), 404);
        $service->resolve($entry, $request->user()->person);

        return back()->with('success', 'Customer list entry removed.');
    }

    private function sameOrganization(OrganizationContact $customer, OrganizationContext $context): void
    {
        abort_unless(hash_equals((string) $customer->organization_id, (string) $context->organization()->getKey()), 404);
    }
}
