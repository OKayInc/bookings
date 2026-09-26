@php
    $googleAnalyticsIds = app(\App\Support\Analytics\GoogleAnalytics::class)
        ->measurementIds(request(), $organization ?? null, $type ?? null);
@endphp
@if($googleAnalyticsIds !== [])
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsIds[0] }}" referrerpolicy="origin"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        // Query strings and fragments can contain contact information or tokens.
        gtag('set', 'page_location', {{ Illuminate\Support\Js::from(request()->url()) }});
        (() => {
            let referrer = '';
            try {
                if (document.referrer) referrer = new URL(document.referrer).origin + '/';
            } catch (_) {}
            gtag('set', 'page_referrer', referrer);
        })();
        @foreach($googleAnalyticsIds as $googleAnalyticsId)
        gtag('config', {{ Illuminate\Support\Js::from($googleAnalyticsId) }});
        @endforeach
    </script>
@endif
