@php
    $visibleGalleryPhotos = $photos
        ->filter(fn ($photo) => $photo->placement->value === $placement)
        ->values();
@endphp
@if($visibleGalleryPhotos->isNotEmpty())
<section class="photo-gallery-section" aria-label="{{ $ownerName }} photo gallery" data-photo-gallery data-gallery-placement="{{ $placement }}">
    <div class="photo-gallery-grid">
        @foreach($visibleGalleryPhotos as $index => $photo)
            <button
                class="photo-gallery-tile"
                type="button"
                data-gallery-photo
                data-full-src="{{ $photo->url }}"
                data-alt="{{ $photo->alt_text ?: $ownerName.' gallery photo '.($index + 1) }}"
                aria-label="Enlarge gallery photo {{ $index + 1 }} of {{ $visibleGalleryPhotos->count() }}"
            >
                <img src="{{ $photo->url }}" alt="{{ $photo->alt_text ?: $ownerName.' gallery photo '.($index + 1) }}" loading="lazy" decoding="async">
            </button>
        @endforeach
    </div>
    <dialog class="photo-gallery-lightbox" aria-label="Enlarged gallery photo">
        <div class="photo-gallery-lightbox-frame">
            <button class="photo-gallery-close" type="button" data-gallery-close aria-label="Close enlarged photo">&times;</button>
            @if($visibleGalleryPhotos->count() > 1)
                <button class="photo-gallery-previous" type="button" data-gallery-previous aria-label="Previous photo">&#8249;</button>
            @endif
            <img src="" alt="" data-gallery-enlarged>
            @if($visibleGalleryPhotos->count() > 1)
                <button class="photo-gallery-next" type="button" data-gallery-next aria-label="Next photo">&#8250;</button>
            @endif
            <div class="photo-gallery-counter" data-gallery-counter aria-live="polite"></div>
        </div>
    </dialog>
</section>
@endif
