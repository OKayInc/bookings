<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\ReusableQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReusableQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_question_becomes_a_reusable_organization_question(): void
    {
        [$user, $organization, $type] = $this->context();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                'type' => 'select',
                'label' => 'What is your age range?',
                'is_required' => '1',
                'is_active' => '1',
                'options' => [
                    ['label' => '18–29', 'value' => '18_29', 'pricing_adjustment_type' => 'none'],
                    ['label' => '30–49', 'value' => '30_49', 'pricing_adjustment_type' => 'fixed', 'pricing_amount' => '10.00'],
                ],
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.questionnaire.index', $type));

        $question = AppointmentQuestion::query()->firstOrFail();
        $reusable = ReusableQuestion::query()->with('options')->firstOrFail();

        $this->assertTrue(hash_equals($organization->getKey(), $reusable->organization_id));
        $this->assertTrue(hash_equals($reusable->getKey(), $question->reusable_question_id));
        $this->assertSame('What is your age range?', $reusable->label);
        $this->assertTrue($reusable->default_is_required);
        $this->assertCount(2, $reusable->options);
        $this->assertSame(1000, $reusable->options[1]->pricing_amount_minor);
    }

    public function test_create_page_lists_only_the_active_organizations_reusable_questions(): void
    {
        [$user, $organization, $type] = $this->context();
        $available = $this->reusable($organization, 'What is your age?');
        $attached = $this->reusable($organization, 'What is your preferred language?');
        $otherOrganization = Organization::factory()->create();
        $this->reusable($otherOrganization, 'Private question from another organization');
        $type->questions()->create($this->attachmentData($attached));

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('appointment-types.questions.create', $type));

        $response->assertOk()
            ->assertSee('Reuse an existing question')
            ->assertSee($available->label)
            ->assertSee($attached->label)
            ->assertSee('Already attached')
            ->assertDontSee('Private question from another organization');
    }

    public function test_reusable_question_can_be_attached_once_with_all_options_and_pricing(): void
    {
        [$user, $organization, $type] = $this->context();
        $reusable = $this->reusable($organization, 'Choose a delivery method', 'select', true);
        $reusable->options()->create([
            'label' => 'Courier',
            'value' => 'courier',
            'position' => 1,
            'is_active' => true,
            'pricing_adjustment_type' => 'fixed',
            'pricing_amount_minor' => 2500,
            'pricing_percentage_basis' => 'base_price',
        ]);

        $route = route('appointment-types.questions.attach', [$type, $reusable]);
        $first = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post($route);
        $first->assertRedirect(route('appointment-types.questionnaire.index', $type))
            ->assertSessionHas('success', 'Reusable question attached.');

        $second = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post($route);
        $second->assertSessionHas('success', 'That reusable question is already attached.');

        $this->assertSame(1, $type->questions()->count());
        $question = $type->questions()->with('options')->firstOrFail();
        $this->assertSame('Choose a delivery method', $question->label);
        $this->assertTrue($question->is_required);
        $this->assertCount(1, $question->options);
        $this->assertSame(2500, $question->options->first()->pricing_amount_minor);
    }

    public function test_attached_copies_are_independent_and_template_updates_only_affect_future_attachments(): void
    {
        [$user, $organization, $firstType] = $this->context();
        $secondType = $this->appointmentType($organization, 'Second type', 'second-type');
        $thirdType = $this->appointmentType($organization, 'Third type', 'third-type');
        $reusable = $this->reusable($organization, 'What is your age?');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.attach', [$firstType, $reusable]));
        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.attach', [$secondType, $reusable]));

        $firstQuestion = $firstType->questions()->firstOrFail();
        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.questions.update', [$firstType, $firstQuestion]), [
                'type' => 'text',
                'label' => 'How old are you?',
                'is_required' => '1',
                'is_active' => '1',
                'update_reusable_question' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('How old are you?', $reusable->refresh()->label);
        $this->assertSame('What is your age?', $secondType->questions()->firstOrFail()->label);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.attach', [$thirdType, $reusable]));
        $this->assertSame('How old are you?', $thirdType->questions()->firstOrFail()->label);
    }

    public function test_question_from_another_organization_cannot_be_attached(): void
    {
        [$user, $organization, $type] = $this->context();
        $otherOrganization = Organization::factory()->create();
        $foreign = $this->reusable($otherOrganization, 'Foreign question');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.attach', [$type, $foreign]))
            ->assertNotFound();

        $this->assertSame(0, $type->questions()->count());
    }

    public function test_removing_an_unanswered_attachment_keeps_the_reusable_question(): void
    {
        [$user, $organization, $type] = $this->context();
        $reusable = $this->reusable($organization, 'Reusable after removal');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.attach', [$type, $reusable]));
        $question = $type->questions()->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('appointment-types.questions.destroy', [$type, $question]))
            ->assertSessionHas('success', 'Question removed. Its reusable template remains available.');

        $this->assertSame(0, $type->questions()->count());
        $this->assertTrue(ReusableQuestion::query()->whereKey($reusable->getKey())->exists());
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization, $this->appointmentType($organization, 'Questionnaire Test', 'questionnaire-test')];
    }

    private function appointmentType(Organization $organization, string $name, string $slug): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => $slug,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => 10000,
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
    }

    private function reusable(
        Organization $organization,
        string $label,
        string $type = 'text',
        bool $required = false,
    ): ReusableQuestion {
        return $organization->reusableQuestions()->create([
            'type' => $type,
            'label' => $label,
            'default_is_required' => $required,
            'is_active' => true,
            'pricing_adjustment_type' => 'none',
            'pricing_application_mode' => 'once',
            'pricing_percentage_basis' => 'base_price',
            'pricing_included_units' => 0,
        ]);
    }

    private function attachmentData(ReusableQuestion $reusable): array
    {
        return [
            'reusable_question_id' => $reusable->getKey(),
            'type' => $reusable->type->value,
            'label' => $reusable->label,
            'is_required' => $reusable->default_is_required,
            'is_active' => true,
            'position' => 1,
            'pricing_adjustment_type' => 'none',
            'pricing_application_mode' => 'once',
            'pricing_percentage_basis' => 'base_price',
            'pricing_included_units' => 0,
        ];
    }
}
