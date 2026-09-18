<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => PaymentCurrencyCatalog::options(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        [$user, $organization] = DB::transaction(function () use ($data): array {
            $person = Person::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'primary_email' => strtolower($data['email']),
                'timezone' => $data['timezone'],
                'locale' => app()->getLocale(),
            ]);

            $user = User::create([
                'person_id' => $person->getKey(),
                'email' => strtolower($data['email']),
                'password' => $data['password'],
            ]);

            $baseSlug = Str::slug($data['organization_name']) ?: 'organization';
            $slug = $baseSlug;
            $counter = 2;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => $slug,
                'timezone' => $data['organization_timezone'],
                'currency' => strtoupper($data['currency']),
            ]);

            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $person->getKey(),
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            $user->forceFill(['active_organization_id' => $organization->getKey()])->save();

            return [$user, $organization];
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_organization_uuid', $organization->uuid);

        return redirect()->route('verification.notice')
            ->with('success', 'Account created. Please verify your email address to access the backend.');
    }
}
