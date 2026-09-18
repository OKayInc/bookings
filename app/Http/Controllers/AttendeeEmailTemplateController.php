<?php
namespace App\Http\Controllers;

use App\Domain\Email\AttendeeEmailTemplates;
use App\Models\AttendeeEmailTemplate;
use App\Support\Html\RichTextSanitizer;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendeeEmailTemplateController extends Controller
{
    public function edit(Request $request, OrganizationContext $context)
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $kind = $request->query('kind', 'booking_access');
        abort_unless(is_string($kind) && isset(AttendeeEmailTemplates::KINDS[$kind]), 404);
        $template = AttendeeEmailTemplate::query()->where('organization_id', $organization->getKey())->where('kind', $kind)->first();
        // Keep literal template delimiters out of Blade echo expressions.
        $defaultSubject = '{{subject}}';
        $defaultBody = "{{greeting}}\n\n{{message}}";
        $placeholders = array_map(static fn (string $token): string => '{{'.$token.'}}', AttendeeEmailTemplates::TOKENS);
        return view('email-templates.edit', compact('organization', 'kind', 'template', 'defaultSubject', 'defaultBody', 'placeholders'));
    }

    public function update(Request $request, OrganizationContext $context)
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AttendeeEmailTemplates::KINDS))],
            'format' => ['required', Rule::in(['text', 'html'])],
            'subject' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:50000'],
        ]);
        if ($data['format'] === 'html') {
            $data['body'] = app(RichTextSanitizer::class)->sanitize($data['body']) ?? '';
        }
        foreach (['subject', 'body'] as $field) {
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $data[$field], $matches);
            if (array_diff($matches[1], AttendeeEmailTemplates::TOKENS)) {
                throw ValidationException::withMessages([$field => 'Use only the placeholders listed below.']);
            }
        }
        if (! preg_match('/\{\{\s*message\s*\}\}/', $data['body'])) {
            throw ValidationException::withMessages(['body' => 'Include {{message}} to preserve essential booking details, warnings and disclosure rules.']);
        }
        AttendeeEmailTemplate::query()->updateOrCreate([
            'organization_id' => $organization->getKey(), 'kind' => $data['kind'],
        ], $data);
        return redirect()->route('email-templates.edit', ['kind' => $data['kind']])->with('success', 'Email template saved.');
    }

    public function destroy(Request $request, OrganizationContext $context)
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate(['kind' => ['required', Rule::in(array_keys(AttendeeEmailTemplates::KINDS))]]);
        AttendeeEmailTemplate::query()->where('organization_id', $organization->getKey())->where('kind', $data['kind'])->delete();
        return redirect()->route('email-templates.edit', $data)->with('success', 'Default email restored.');
    }
}
