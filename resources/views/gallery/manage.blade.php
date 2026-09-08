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
        <form method="post" enctype="multipart/form-data" action="{{ $galleryUploadRoute }}" class="row align-items-end mt-3">
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
                <div class="muted">JPEG, PNG, or WebP; up to {{ number_format(config('gallery.max_upload_kilobytes', 20480) / 1024) }} MB each. Select no more than {{ min($galleryRemaining, config('gallery.max_batch_size', 20)) }} now.</div>
            </div>
            <div class="field"><button class="btn btn-primary" type="submit">Upload photos</button></div>
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
                    <form method="post" action="{{ route('gallery-photos.destroy', $photo) }}" onsubmit="return confirm('Delete this gallery photo?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm w-100" type="submit">Delete</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
</div>
