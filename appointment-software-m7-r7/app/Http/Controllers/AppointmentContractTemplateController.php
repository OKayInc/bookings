<?php

namespace App\Http\Controllers;

use App\Models\AppointmentType;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentContractTemplateController extends Controller
{
    public function download(AppointmentType $appointmentType, OrganizationContext $context): StreamedResponse
    {
        abort_unless(hash_equals($appointmentType->organization_id, $context->organization()->getKey()), 404);
        $this->authorize('manage', $appointmentType);

        $template = $appointmentType->contractTemplate()->firstOrFail();
        abort_unless(Storage::disk($template->disk)->exists($template->path), 404);

        return Storage::disk($template->disk)->download($template->path, $template->original_name);
    }
}
