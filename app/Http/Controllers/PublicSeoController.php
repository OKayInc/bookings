<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentVisibility;
use App\Models\Organization;
use Illuminate\Http\Response;

class PublicSeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $organizations = Organization::query()
            ->whereHas('appointmentTypes', fn ($query) => $query
                ->where('is_active', true)
                ->where('visibility', AppointmentVisibility::Public->value))
            ->with(['appointmentTypes' => fn ($query) => $query
                ->where('is_active', true)
                ->where('visibility', AppointmentVisibility::Public->value)
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $urls = collect([
            [
                'loc' => route('home'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
                'lastmod' => null,
            ],
            [
                'loc' => route('pricing'),
                'changefreq' => 'monthly',
                'priority' => '0.8',
                'lastmod' => null,
            ],
        ]);

        foreach ($organizations as $organization) {
            $urls->push([
                'loc' => route('public.appointment-types.index', $organization->slug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod' => $organization->updated_at?->toAtomString(),
            ]);

            foreach ($organization->appointmentTypes as $type) {
                $urls->push([
                    'loc' => route('public.appointment-types.show', [
                        'organizationSlug' => $organization->slug,
                        'appointmentSlug' => $type->slug,
                    ]),
                    'changefreq' => $type->ticketing_enabled ? 'daily' : 'weekly',
                    'priority' => $type->ticketing_enabled ? '0.8' : '0.7',
                    'lastmod' => $type->updated_at?->toAtomString(),
                ]);
            }
        }

        return response()
            ->view('seo.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
