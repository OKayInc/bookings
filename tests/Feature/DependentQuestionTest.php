<?php

namespace Tests\Feature;

use App\Domain\Questionnaires\QuestionVisibilityService;
use App\Domain\Questionnaires\QuestionnaireSubmissionService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DependentQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_build_a_mixed_and_or_visibility_expression(): void
    {
        [$user, $organization, $type] = $this->context();
        $first = $this->choiceQuestion($type, 'Primary choice', 1, ['Answer 1A', 'Answer 1C']);
        $second = $this->choiceQuestion($type, 'Secondary choice', 2, ['Answer 2B'], 'checkboxes');

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                'type' => 'text',
                'label' => 'Question 9',
                'position' => 3,
                'is_required' => '1',
                'is_active' => '1',
                'visibility_conditions' => [
                    ['boolean_operator' => 'and', 'source_question_uuid' => $first->uuid, 'question_option_uuid' => $first->options[0]->uuid],
                    ['boolean_operator' => 'and', 'source_question_uuid' => $second->uuid, 'question_option_uuid' => $second->options[0]->uuid],
                    ['boolean_operator' => 'or', 'source_question_uuid' => $first->uuid, 'question_option_uuid' => $first->options[1]->uuid],
                ],
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.questionnaire.index', $type));

        $target = $type->questions()->where('label', 'Question 9')->with('visibilityConditions')->firstOrFail();
        $this->assertCount(3, $target->visibilityConditions);
        $this->assertSame(['and', 'and', 'or'], $target->visibilityConditions->pluck('boolean_operator')->all());

        $visibility = app(QuestionVisibilityService::class);
        $this->assertFalse($visibility->visibleQuestions($type->fresh(), [
            $first->uuid => $first->options[0]->uuid,
        ])->contains('uuid', $target->uuid));
        $this->assertTrue($visibility->visibleQuestions($type->fresh(), [
            $first->uuid => $first->options[0]->uuid,
            $second->uuid => [$second->options[0]->uuid],
        ])->contains('uuid', $target->uuid));
        $this->assertTrue($visibility->visibleQuestions($type->fresh(), [
            $first->uuid => $first->options[1]->uuid,
        ])->contains('uuid', $target->uuid));
    }

    public function test_hidden_required_answer_is_ignored_by_validation_pricing_and_persistence_payload(): void
    {
        [, , $type] = $this->context();
        $source = $this->choiceQuestion($type, 'Needs extra work?', 1, ['No', 'Yes']);
        $target = $type->questions()->create([
            'type' => 'number',
            'label' => 'Extra work units',
            'is_required' => true,
            'is_active' => true,
            'position' => 2,
            'pricing_adjustment_type' => 'fixed',
            'pricing_application_mode' => 'per_unit',
            'pricing_amount_minor' => 1000,
            'pricing_percentage_basis' => 'base_price',
            'pricing_included_units' => 0,
        ]);
        $target->visibilityConditions()->create([
            'source_question_id' => $source->getKey(),
            'question_option_id' => $source->options[1]->getKey(),
            'boolean_operator' => 'and',
            'position' => 1,
        ]);

        $service = app(QuestionnaireSubmissionService::class);
        $hidden = $service->validateForBooking(Request::create('/', 'POST', [
            'answers' => [
                $source->uuid => $source->options[0]->uuid,
                $target->uuid => 99,
            ],
        ]), $type->fresh(), 60);

        $this->assertCount(1, $hidden->answers);
        $this->assertSame($source->uuid, $hidden->answers[0]['question']->uuid);
        $this->assertSame(10000, $hidden->quote->totalMinor);

        try {
            $service->validateForBooking(Request::create('/', 'POST', [
                'answers' => [$source->uuid => $source->options[1]->uuid],
            ]), $type->fresh(), 60);
            $this->fail('A visible required dependent question was accepted without an answer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.'.$target->uuid, $exception->errors());
        }

        $visible = $service->validateForBooking(Request::create('/', 'POST', [
            'answers' => [
                $source->uuid => $source->options[1]->uuid,
                $target->uuid => 3,
            ],
        ]), $type->fresh(), 60);
        $this->assertCount(2, $visible->answers);
        $this->assertSame(13000, $visible->quote->totalMinor);
    }

    public function test_dependency_must_reference_an_earlier_choice_question_in_the_same_appointment_type(): void
    {
        [$user, $organization, $type] = $this->context();
        $later = $this->choiceQuestion($type, 'Later question', 3, ['Yes']);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                'type' => 'text',
                'label' => 'Invalid dependent question',
                'position' => 2,
                'is_active' => '1',
                'visibility_conditions' => [[
                    'boolean_operator' => 'and',
                    'source_question_uuid' => $later->uuid,
                    'question_option_uuid' => $later->options[0]->uuid,
                ]],
            ]);

        $response->assertSessionHasErrors('question');
        $this->assertDatabaseMissing('appointment_questions', ['label' => 'Invalid dependent question']);
    }

    public function test_editing_a_source_option_keeps_its_identity_and_used_option_cannot_be_removed(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->choiceQuestion($type, 'Source', 1, ['Original']);
        $optionUuid = $source->options[0]->uuid;
        $target = $type->questions()->create([
            'type' => 'text',
            'label' => 'Dependent',
            'is_required' => false,
            'is_active' => true,
            'position' => 2,
        ]);
        $target->visibilityConditions()->create([
            'source_question_id' => $source->getKey(),
            'question_option_id' => $source->options[0]->getKey(),
            'boolean_operator' => 'and',
            'position' => 1,
        ]);

        $route = route('appointment-types.questions.update', [$type, $source]);
        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put($route, [
                'type' => 'radio',
                'label' => 'Source',
                'position' => 1,
                'is_active' => '1',
                'options' => [[
                    'uuid' => $optionUuid,
                    'label' => 'Renamed',
                    'value' => 'renamed',
                    'pricing_adjustment_type' => 'none',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($optionUuid, $source->fresh('options')->options[0]->uuid);
        $this->assertSame('Renamed', $source->fresh('options')->options[0]->label);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put($route, [
                'type' => 'radio',
                'label' => 'Source',
                'position' => 1,
                'is_active' => '1',
                'options' => [[
                    'label' => 'Replacement',
                    'value' => 'replacement',
                    'pricing_adjustment_type' => 'none',
                ]],
            ])
            ->assertSessionHasErrors('question');

        $this->assertSame($optionUuid, $source->fresh('options')->options[0]->uuid);
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

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Dependent Questionnaire',
            'slug' => 'dependent-questionnaire',
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

        return [$user, $organization, $type];
    }

    private function choiceQuestion(
        AppointmentType $type,
        string $label,
        int $position,
        array $optionLabels,
        string $questionType = 'radio',
    ): AppointmentQuestion {
        $question = $type->questions()->create([
            'type' => $questionType,
            'label' => $label,
            'is_required' => false,
            'is_active' => true,
            'position' => $position,
        ]);

        foreach ($optionLabels as $index => $optionLabel) {
            $question->options()->create([
                'label' => $optionLabel,
                'value' => 'option_'.($index + 1),
                'position' => $index + 1,
                'is_active' => true,
                'pricing_adjustment_type' => 'none',
                'pricing_percentage_basis' => 'base_price',
            ]);
        }

        return $question->load('options');
    }
}
