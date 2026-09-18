<?php
namespace Tests\Feature;

use App\Domain\Email\AttendeeEmailTemplates;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AttendeeEmailTemplate;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class AttendeeEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function login(MembershipRole $role = MembershipRole::Owner): Organization
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => $role, 'status' => MembershipStatus::Active,
        ]);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        return $organization;
    }

    private function data(): array
    {
        return ['kind' => 'booking_access', 'format' => 'html', 'subject' => 'Welcome {{first_name}}',
            'body' => '<p>Hello {{first_name}}</p><p>{{message}}</p><script>alert(1)</script>'];
    }

    public function test_owner_can_edit_sanitized_template_and_restore_default(): void
    {
        $org = $this->login();
        $this->get(route('email-templates.edit'))
            ->assertOk()->assertSee('Attendee email templates')
            ->assertSee('value="{{subject}}"', false)
            ->assertSee("{{greeting}}\n\n{{message}}", false)
            ->assertSee('<code>{{first_name}}</code>', false);
        $this->put(route('email-templates.update'), $this->data())->assertSessionHasNoErrors();
        $template = AttendeeEmailTemplate::firstOrFail();
        $this->assertSame($org->getKey(), $template->organization_id);
        $this->assertStringNotContainsString('script', $template->body);
        $this->assertStringNotContainsString('alert', $template->body);
        $this->get(route('email-templates.edit'))
            ->assertOk()->assertSee('Welcome {{first_name}}');
        $this->delete(route('email-templates.destroy'), ['kind' => 'booking_access'])->assertRedirect();
        $this->assertSame(0, AttendeeEmailTemplate::count());
    }

    public function test_employee_cannot_read_or_write_templates(): void
    {
        $this->login(MembershipRole::Employee);
        $this->get(route('email-templates.edit'))->assertForbidden();
        $this->put(route('email-templates.update'), $this->data())->assertForbidden();
        $this->delete(route('email-templates.destroy'), ['kind' => 'booking_access'])->assertForbidden();
    }

    public function test_details_token_unknown_tokens_and_header_injection_are_validated(): void
    {
        $this->login();
        $this->put(route('email-templates.update'), array_replace($this->data(), ['body' => 'Hello']))->assertSessionHasErrors('body');
        $this->put(route('email-templates.update'), array_replace($this->data(), ['body' => '{{message}} {{location}}']))->assertSessionHasErrors('body');
        $this->put(route('email-templates.update'), array_replace($this->data(), ['subject' => "Hello\r\nBcc: other@example.test"]))->assertSessionHasErrors('subject');
    }

    public function test_html_interpolation_escapes_customer_input_without_executing_template_code(): void
    {
        $result = app(AttendeeEmailTemplates::class)->render('Hello {{first_name}}', '<p>{{first_name}}</p><p>{{message}}</p>', 'html', [
            'first_name' => '<img src=x onerror=alert(1)>', 'message' => '{{first_name}} @php echo "bad"; @endphp',
        ]);
        $this->assertStringNotContainsString('<img', $result['body']);
        $this->assertStringContainsString('&lt;img', $result['body']);
        $this->assertStringContainsString('{{first_name}}', $result['body']);
    }

    public function test_template_delivery_is_scoped_and_keeps_actions_attachments_and_plain_text(): void
    {
        $org = $this->login();
        AttendeeEmailTemplate::create([
            'organization_id' => $org->getKey(), 'kind' => 'coupon_delivery', 'format' => 'text',
            'subject' => 'Hello {{first_name}}', 'body' => "{{greeting}}\n\n{{message}}",
        ]);
        $coupon = new Coupon(['organization_id' => $org->getKey(), 'recipient_name' => 'Alex']);
        $coupon->setRelation('organization', $org);
        $mail = (new MailMessage)->subject('Default')->greeting('Hi')->line('Protected details')
            ->action('Open', 'https://example.test/secret')->attachData('data', 'test.txt');
        $rendered = app(AttendeeEmailTemplates::class)->apply('coupon_delivery', $coupon, $mail);
        $this->assertSame(['text' => 'emails.attendee-text'], $rendered->view);
        $this->assertSame('Hello Alex', $rendered->subject);
        $this->assertSame('https://example.test/secret', $rendered->actionUrl);
        $this->assertCount(1, $rendered->rawAttachments);
        $output = view('emails.attendee-text', $rendered->viewData)->render();
        $this->assertStringContainsString('Protected details', $output);
        $this->assertStringContainsString('https://example.test/secret', $output);
        $other = Organization::factory()->create();
        $coupon->organization_id = $other->getKey();
        $default = (new MailMessage)->subject('Untouched');
        $this->assertSame($default, app(AttendeeEmailTemplates::class)->apply('coupon_delivery', $coupon, $default));
        $this->assertNull($default->view);
    }
}
