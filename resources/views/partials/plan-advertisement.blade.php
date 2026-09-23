<aside class="card my-4 text-center" aria-label="Advertisement">
    <div class="small text-body-secondary mb-2">Advertisement</div>
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ config('plans.adsense.client') }}"
         data-ad-slot="{{ config('plans.adsense.slot') }}"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
</aside>
@push('scripts')
<script>(window.adsbygoogle = window.adsbygoogle || []).push({});</script>
@endpush
