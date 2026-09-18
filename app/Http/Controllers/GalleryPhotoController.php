<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\GalleryPhotoService;
use App\Enums\GalleryPlacement;
use App\Http\Requests\StoreGalleryPhotosRequest;
use App\Models\AppointmentType;
use App\Models\GalleryPhoto;
use App\Models\Organization;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GalleryPhotoController extends Controller
{
    public function storeForOrganization(
        StoreGalleryPhotosRequest $request,
        Organization $organization,
        GalleryPhotoService $photos,
    ): RedirectResponse|JsonResponse {
        $this->authorize('update', $organization);
        $count = $photos->uploadForOrganization(
            $organization,
            $request->file('photos', []),
            GalleryPlacement::from($request->validated('placement')),
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->uploadedMessage($count), 'count' => $count]);
        }

        return back()->with('success', $this->uploadedMessage($count));
    }

    public function storeForAppointmentType(
        StoreGalleryPhotosRequest $request,
        AppointmentType $appointmentType,
        OrganizationContext $context,
        GalleryPhotoService $photos,
    ): RedirectResponse|JsonResponse {
        abort_unless($appointmentType->organization_id === $context->organization()->getKey(), 404);
        $this->authorize('manage', $appointmentType);
        $count = $photos->uploadForAppointmentType(
            $appointmentType,
            $request->file('photos', []),
            GalleryPlacement::from($request->validated('placement')),
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->uploadedMessage($count), 'count' => $count]);
        }

        return back()->with('success', $this->uploadedMessage($count));
    }

    public function update(Request $request, GalleryPhoto $galleryPhoto, GalleryPhotoService $photos): RedirectResponse
    {
        $galleryPhoto->loadMissing(['organization', 'appointmentType']);
        if ($galleryPhoto->appointmentType) {
            $this->authorize('manage', $galleryPhoto->appointmentType);
        } else {
            $this->authorize('update', $galleryPhoto->organization);
        }

        $data = $request->validate([
            'placement' => ['required', Rule::enum(GalleryPlacement::class)],
            'position' => ['required', 'integer', 'min:1'],
            'move' => ['sometimes', Rule::in(['earlier', 'later', 'first', 'last'])],
        ]);
        $photos->reposition($galleryPhoto, GalleryPlacement::from($data['placement']), (int) $data['position'], $data['move'] ?? null);

        return back()->with('success', 'Gallery photo position updated.');
    }

    public function destroy(
        GalleryPhoto $galleryPhoto,
        GalleryPhotoService $photos,
    ): RedirectResponse {
        $galleryPhoto->loadMissing(['organization', 'appointmentType']);

        if ($galleryPhoto->appointmentType) {
            $this->authorize('manage', $galleryPhoto->appointmentType);
        } else {
            $this->authorize('update', $galleryPhoto->organization);
        }

        $photos->delete($galleryPhoto);

        return back()->with('success', 'Gallery photo deleted.');
    }

    private function uploadedMessage(int $count): string
    {
        return $count.' gallery photo'.($count === 1 ? '' : 's').' uploaded and converted to WebP.';
    }
}
