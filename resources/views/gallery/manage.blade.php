@php
    $galleryCount = $galleryPhotos->count();
    $galleryRemaining = max(0, $galleryLimit - $galleryCount);
    $tierLabel = $organization->plan_tier->label();
@endphp
<div class="section-card gallery-manager" id="gallery-photos">
    <div class="page-heading actions" style="justify-content:space-between">
        <div>
            <h2>{{ $galleryOwnerLabel }} gallery</h2>
            <p class="muted mb-0">{{ $galleryCount }} of {{ $galleryLimit }} photos used on the {{ $tierLabel }} tier. Uploads are resized when necessary and saved as WebP.</p>
        </div>
        <span class="badge text-bg-secondary">{{ $galleryRemaining }} remaining</span>
    </div>

    @if($galleryRemaining > 0)
        <form method="post" enctype="multipart/form-data" action="{{ $galleryUploadRoute }}" class="row align-items-end mt-3" data-gallery-upload data-max-files="{{ min($galleryRemaining, config('gallery.max_batch_size', 20)) }}" data-max-bytes="{{ \App\Support\GalleryUploadLimits::maxFileBytes() }}">
            @csrf
            <div class="field">
                <label for="{{ $galleryInputPrefix }}-placement">Display position</label>
                <select id="{{ $galleryInputPrefix }}-placement" name="placement" required>
                    @foreach(\App\Enums\GalleryPlacement::cases() as $placement)
                        <option value="{{ $placement->value }}" @selected(old('placement', 'above') === $placement->value)>{{ $placement->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="{{ $galleryInputPrefix }}-files">Photos</label>
                <input id="{{ $galleryInputPrefix }}-files" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                <div class="muted">JPEG, PNG, or WebP; up to {{ number_format(\App\Support\GalleryUploadLimits::maxFileBytes() / 1048576, 1) }} MB each. Select no more than {{ min($galleryRemaining, config('gallery.max_batch_size', 20)) }} now.</div>
            </div>
            <div class="field"><button class="btn btn-primary" type="submit">Upload photos</button></div>
            <p class="muted" data-upload-status role="status" aria-live="polite">Selected photos upload one at a time.</p>
            <a href="#gallery-photos" data-upload-refresh hidden>Refresh to see uploaded photos</a>
        </form>
    @else
        <div class="alert alert-info mt-3 mb-0">This gallery has reached its configured {{ strtolower($tierLabel) }}-tier limit.</div>
    @endif

    @if($galleryPhotos->isNotEmpty())
        <div class="gallery-admin-grid mt-4">
            @foreach($galleryPhotos as $photo)
                <article class="gallery-admin-item">
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener">
                        <img src="{{ $photo->url }}" alt="{{ $galleryOwnerLabel }} gallery photo" loading="lazy" decoding="async">
                    </a>
                    <div class="gallery-admin-meta">
                        <span class="badge text-bg-light">{{ $photo->placement->label() }}</span>
                        <span class="muted">{{ $photo->width }}×{{ $photo->height }} · {{ number_format($photo->file_size / 1024) }} KB</span>
                    </div>
                    <form method="post" action="{{ route('gallery-photos.update', $photo) }}">
                        @csrf @method('PATCH')
                        <label for="placement-{{ $photo->uuid }}">Display position</label>
                        <select id="placement-{{ $photo->uuid }}" name="placement">
                            <option value="above" @selected($photo->placement->value === 'above')>Top — above the main content</option>
                            <option value="below" @selected($photo->placement->value === 'below')>Bottom — below the main content</option>
                        </select>
                        <label for="position-{{ $photo->uuid }}">Order within this position</label>
                        <input id="position-{{ $photo->uuid }}" type="number" name="position" min="1" value="{{ $photo->position }}" required>
                        <div class="d-flex flex-wrap gap-1 my-2">
                            <button class="btn btn-primary btn-sm" type="submit">Save position</button>
                            <button class="btn btn-secondary btn-sm" name="move" value="earlier">Earlier</button>
                            <button class="btn btn-secondary btn-sm" name="move" value="later">Later</button>
                            <button class="btn btn-outline-secondary btn-sm" name="move" value="first">First</button>
                            <button class="btn btn-outline-secondary btn-sm" name="move" value="last">Last</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('gallery-photos.destroy', $photo) }}" onsubmit="return confirm('Delete this gallery photo?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm w-100" type="submit">Delete</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
</div>

@once
@push('head')
<script src="{{ asset('js/gallery-upload.js') }}?v=m10-gallery" defer></script>
@endpush
@endonce
