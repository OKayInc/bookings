<?php

namespace App\Http\Requests;

use App\Support\Organizations\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(
            'update',
            app(OrganizationContext::class)->organization(),
        );
    }

    public function rules(): array
    {
        $secret = ['nullable', 'string', 'max:10000'];

        return [
            'google_maps_api_key' => $secret,
            'google_routes_api_key' => $secret,
            'clear_google_maps_api_key' => ['nullable', 'boolean'],
            'clear_google_routes_api_key' => ['nullable', 'boolean'],

            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => $secret,
            'google_refresh_token' => $secret,
            'clear_google_client_secret' => ['nullable', 'boolean'],
            'clear_google_refresh_token' => ['nullable', 'boolean'],

            'microsoft_tenant_id' => ['nullable', 'string', 'max:255'],
            'microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'microsoft_client_secret' => $secret,
            'microsoft_organizer_user_id' => ['nullable', 'string', 'max:255'],
            'clear_microsoft_client_secret' => ['nullable', 'boolean'],

            'zoom_account_id' => ['nullable', 'string', 'max:255'],
            'zoom_client_id' => ['nullable', 'string', 'max:255'],
            'zoom_client_secret' => $secret,
            'zoom_host_user_id' => ['nullable', 'string', 'max:255'],
            'clear_zoom_client_secret' => ['nullable', 'boolean'],

            'webex_client_id' => ['nullable', 'string', 'max:255'],
            'webex_client_secret' => $secret,
            'webex_refresh_token' => $secret,
            'webex_host_email' => ['nullable', 'email:rfc', 'max:255'],
            'clear_webex_client_secret' => ['nullable', 'boolean'],
            'clear_webex_refresh_token' => ['nullable', 'boolean'],

            'custom_meeting_url' => ['nullable', 'url', 'max:2048'],
            'clear_custom_meeting_url' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('custom_meeting_url');
            if (is_string($url) && $url !== '') {
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true)) {
                    $validator->errors()->add('custom_meeting_url', 'The custom meeting URL must use HTTP or HTTPS.');
                }
            }
        });
    }
}
