@php
    $socialLinks = [
        'facebook' => ['label' => 'Facebook', 'url' => $organization->facebook_url],
        'instagram' => ['label' => 'Instagram', 'url' => $organization->instagram_url],
        'x' => ['label' => 'X', 'url' => $organization->x_url],
        'linkedin' => ['label' => 'LinkedIn', 'url' => $organization->linkedin_url],
        'tiktok' => ['label' => 'TikTok', 'url' => $organization->tiktok_url],
    ];
    $hasSocialLinks = collect($socialLinks)->contains(
        fn (array $social): bool => filled($social['url'])
    );
@endphp
@if($hasSocialLinks)
<nav class="organization-social-links" aria-label="{{ $organization->name }} social media">
    @foreach($socialLinks as $network => $social)
        @if(filled($social['url']))
            <a class="organization-social-link" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}">
                @include('public.partials.social-icon', ['network' => $network])
            </a>
        @endif
    @endforeach
</nav>
@endif
