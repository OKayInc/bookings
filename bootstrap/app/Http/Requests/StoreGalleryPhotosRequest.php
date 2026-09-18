<?php

namespace App\Http\Requests;

use App\Enums\GalleryPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', Rule::enum(GalleryPlacement::class)],
            'photos' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('gallery.max_batch_size', 20))],
            'photos.*' => [
                'required',
                'file',
                'mimes:'.implode(',', config('gallery.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp'])),
                'max:'.max(1, (int) config('gallery.max_upload_kilobytes', 20480)),
            ],
        ];
    }
}
