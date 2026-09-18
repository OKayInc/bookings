<?php

namespace Tests\Feature;

use App\Domain\Questionnaires\NumericQuestionConstraintService;
use App\Domain\Questionnaires\QuestionnaireSubmissionService;
use App\Domain\Questionnaires\ReusableQuestionService;
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

class NumericQuestionConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_attendee_count_and_switch_between_all_operand_types(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        $this->get(route('appointment-types.questions.create', $type))->assertOk()->assertSee('Number of attendees');
        $this->post(route('appointment-types.questions.store', $type), [
            ...$this->payload('Meals needed', 2), 'numeric_constraints' => [$this->attendeeRule('<=')],
        ])->assertSessionHasNoErrors();
        $target = $type->questions()->where('label', 'Meals needed')->firstOrFail();
        $service = app(NumericQuestionConstraintService::class);
        $constraint = $target->numericConstraints->sole();
        $this->assertSame('attendee_count', $constraint->operand_type);
        $this->assertNull($constraint->source_question_id);
        $this->assertNull($constraint->comparison_value);
        $this->assertSame('attendee_count', $service->publicRules($target)[0]['operand_type']);
        $this->assertStringContainsString('number of attendees', $service->message($target));
        $this->get(route('appointment-types.questions.edit', [$type, $target]))->assertOk()->assertSee('Number of attendees');

        foreach ([$this->valueRule('<=', '10'), $this->questionRule($source, '>='), $this->attendeeRule('=')] as $row) {
            $this->put(route('appointment-types.questions.update', [$type, $target]), [
                ...$this->payload('Meals needed', 2), 'numeric_constraints' => [$row],
            ])->assertSessionHasNoErrors();
            $saved = $target->fresh()->numericConstraints->sole();
            $this->assertSame($row['operand_type'], $saved->resolvedOperandType());
            $this->assertSame($row['operand_type'] === 'question' ? $source->getKey() : null, $saved->source_question_id);
            $this->assertSame($row['operand_type'] === 'value' ? '10' : null, $saved->comparison_value);
        }
    }

    public function test_attendee_count_cannot_be_combined_with_a_supplied_question_or_fixed_value(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach (['source_question_uuid' => $source->uuid, 'comparison_value' => '3'] as $key => $value) {
            $this->post(route('appointment-types.questions.store', $type), [
                ...$this->payload('Invalid attendee rule', 2),
                'numeric_constraints' => [[...$this->attendeeRule('='), $key => $value]],
            ])->assertSessionHasErrors('numeric_constraints.0.'.$key);
        }
        $this->assertDatabaseMissing('appointment_questions', ['label' => 'Invalid attendee rule']);
    }

    public function test_all_operators_compare_against_attendee_count_without_needing_an_earlier_question(): void
    {
        [, , $type] = $this->context();
        $type->update(['attendance_mode' => 'group', 'capacity' => 10]);
        $target = $this->number($type, 'Meals', 1);
        foreach ([
            ['>', '4', '3'], ['>=', '3.0', '2'], ['=', '3', '4'], ['<=', '3', '4'],
            ['<', '2', '3'], ['!=', '2', '3.0'], ['<>', '2', '3'], ['!', '4', '3'],
        ] as [$operator, $valid, $invalid]) {
            app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->attendeeRule($operator)]);
            $this->submission($type->fresh(), [$target->uuid => $valid], 3);
            $this->assertInvalidAnswer($type->fresh(), [$target->uuid => $invalid], $target, 3);
        }
        // Single attendance uses the normal one-seat count.
        $type->update(['attendance_mode' => 'single', 'capacity' => 1]);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->attendeeRule('=')]);
        $this->submission($type->fresh(), [$target->uuid => '1']);
        $this->assertInvalidAnswer($type->fresh(), [$target->uuid => '2'], $target);
    }

    public function test_attendee_count_combines_with_questions_and_fixed_values_using_and_or(): void
    {
        [, , $type] = $this->context();
        $type->update(['attendance_mode' => 'group', 'capacity' => 10]);
        $source = $this->number($type, 'Minimum meals', 1);
        $target = $this->number($type, 'Meals needed', 2);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [
            $this->questionRule($source, '>='), $this->attendeeRule('<='), $this->valueRule('=', '0', 'or'),
        ]);
        foreach (['0', '2', '3'] as $value) $this->submission($type->fresh(), [$source->uuid => '2', $target->uuid => $value], 3);
        foreach (['1', '4'] as $value) $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '2', $target->uuid => $value], $target, 3);
        $this->submission($type->fresh(), [$source->uuid => '2'], 3); // Optional target.
    }

    public function test_missing_or_invalid_attendee_context_never_satisfies_different_from(): void
    {
        [, , $type] = $this->context();
        $target = $this->number($type, 'Meals', 1);
        $service = app(NumericQuestionConstraintService::class);
        $service->sync($type, $target, [$this->attendeeRule('!=')]);
        foreach ([null, 0, -1] as $count) {
            $errors = $service->errors($type->fresh(), collect([$target]), [$target->uuid => '1'], $count);
            $this->assertArrayHasKey('answers.'.$target->uuid, $errors);
        }
    }

    public function test_legacy_constraints_with_no_operand_type_keep_their_question_and_value_meanings(): void
    {
        [, , $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $target = $this->number($type, 'Q2', 2);
        $target->numericConstraints()->create(['source_question_id' => $source->getKey(), 'comparison_operator' => '>=', 'boolean_operator' => 'and', 'position' => 1]);
        $target->numericConstraints()->create(['comparison_value' => '10', 'comparison_operator' => '<', 'boolean_operator' => 'and', 'position' => 2]);
        $this->assertNull($target->fresh()->numericConstraints[0]->operand_type);
        $this->assertNull($target->fresh()->numericConstraints[1]->operand_type);
        $service = app(NumericQuestionConstraintService::class);
        $service->assertValidConfiguration($type->fresh());
        $this->assertSame(['question', 'value'], array_column($service->publicRules($target), 'operand_type'));
        $this->submission($type->fresh(), [$source->uuid => '5', $target->uuid => '6']);
        $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '5', $target->uuid => '10'], $target);
    }

    public function test_owner_can_configure_edit_and_remove_mixed_numeric_constraints(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        $this->get(route('appointment-types.questions.create', $type))->assertOk()->assertSee('Numeric answer constraints')->assertSee('Q1');
        $rows = [$this->questionRule($source, '>='), $this->valueRule('<', '20.5'), $this->valueRule('=', '0', 'or')];
        $this->post(route('appointment-types.questions.store', $type), [
            ...$this->payload('Q2', 2), 'numeric_constraints' => $rows,
        ])->assertSessionHasNoErrors();

        $target = $type->questions()->where('label', 'Q2')->firstOrFail();
        $this->assertSame(['>=', '<', '='], $target->numericConstraints->pluck('comparison_operator')->all());
        $this->assertSame(['and', 'and', 'or'], $target->numericConstraints->pluck('boolean_operator')->all());
        $this->assertSame('20.5', $target->numericConstraints[1]->comparison_value);
        $this->assertSame($source->getKey(), $target->numericConstraints[0]->source_question_id);
        $this->get(route('appointment-types.questions.edit', [$type, $target]))->assertOk()->assertSee('20.5');
        $this->get(route('appointment-types.questionnaire.index', $type))->assertOk()->assertSee('3 numeric constraint(s)');

        $this->put(route('appointment-types.questions.update', [$type, $target]), [
            ...$this->payload('Q2', 2), 'numeric_constraints' => [$this->valueRule('!', '5.00')],
        ])->assertSessionHasNoErrors();
        $this->assertSame('!=', $target->fresh()->numericConstraints->sole()->comparison_operator);
        $this->put(route('appointment-types.questions.update', [$type, $target]), $this->payload('Q2', 2))->assertSessionHasNoErrors();
        $this->assertCount(0, $target->fresh()->numericConstraints);
    }

    public function test_all_different_spellings_are_saved_as_not_equal(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach (['<>', '!=', '!'] as $index => $operator) {
            $this->post(route('appointment-types.questions.store', $type), [
                ...$this->payload('Different '.$index, 2 + $index),
                'numeric_constraints' => [$this->questionRule($source, $operator)],
            ])->assertSessionHasNoErrors();
            $target = $type->questions()->where('label', 'Different '.$index)->firstOrFail();
            $this->assertSame('!=', $target->numericConstraints->sole()->comparison_operator);
            $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '5', $target->uuid => '5.0'], $target);
            $this->submission($type->fresh(), [$source->uuid => '5', $target->uuid => '6']);
        }
    }

    public function test_booking_validation_applies_and_before_or_with_question_and_fixed_operands(): void
    {
        [, , $type] = $this->context();
        $low = $this->number($type, 'Lower limit', 1);
        $high = $this->number($type, 'Upper limit', 2);
        $target = $this->number($type, 'Q2', 3);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [
            $this->questionRule($low, '>='), $this->questionRule($high, '<'),
            $this->valueRule('=', '0', 'or'), $this->valueRule('!=', '-1'),
        ]);
        foreach (['10', '15.5', '19.999', '0'] as $value) {
            $submission = $this->submission($type->fresh(), [$low->uuid => '10', $high->uuid => '20', $target->uuid => $value]);
            $this->assertCount(3, $submission->answers);
        }
        foreach (['9.99', '20', '21', '-1'] as $value) {
            $this->assertInvalidAnswer($type->fresh(), [$low->uuid => '10', $high->uuid => '20', $target->uuid => $value], $target);
        }
        // Earlier true OR group must not be affected by a later false AND.
        app(NumericQuestionConstraintService::class)->sync($type, $target, [
            $this->valueRule('=', '15'), $this->valueRule('=', '0', 'or'), $this->valueRule('>', '100'),
        ]);
        $this->submission($type->fresh(), [$target->uuid => '15']);
    }

    public function test_blank_optional_target_is_allowed_but_missing_source_never_matches_not_equal(): void
    {
        [, , $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $target = $this->number($type, 'Q2', 2);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->questionRule($source, '!=')]);
        $this->submission($type->fresh(), []);
        $this->assertInvalidAnswer($type->fresh(), [$target->uuid => '0'], $target);
        $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '', $target->uuid => '0'], $target);
        $target->update(['is_required' => true]);
        $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '1'], $target);
    }

    public function test_hidden_targets_are_ignored_and_hidden_source_answers_cannot_be_forged(): void
    {
        [, , $type] = $this->context();
        $choice = $type->questions()->create(['type' => 'radio', 'label' => 'Enable Q1?', 'position' => 1, 'is_active' => true]);
        $yes = $choice->options()->create(['label' => 'Yes', 'value' => 'yes', 'position' => 1, 'is_active' => true]);
        $source = $this->number($type, 'Q1', 2);
        $target = $this->number($type, 'Q2', 3);
        $source->visibilityConditions()->create(['source_question_id' => $choice->getKey(), 'question_option_id' => $yes->getKey(), 'boolean_operator' => 'and', 'position' => 1]);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->questionRule($source, '>=')]);
        $this->assertInvalidAnswer($type->fresh(), [$source->uuid => '1', $target->uuid => '5'], $target);
        $this->submission($type->fresh(), [$choice->uuid => $yes->uuid, $source->uuid => '1', $target->uuid => '5']);

        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->questionRule($source, '>='), $this->valueRule('=', '0', 'or')]);
        $this->submission($type->fresh(), [$target->uuid => '0']);

        $target->update(['is_required' => true]);
        $target->visibilityConditions()->create(['source_question_id' => $choice->getKey(), 'question_option_id' => $yes->getKey(), 'boolean_operator' => 'and', 'position' => 1]);
        $result = $this->submission($type->fresh(), [$target->uuid => 'invalid hidden value']);
        $this->assertCount(1, $result->answers);
        $this->assertSame($choice->uuid, $result->answers[0]['question']->uuid);
    }

    public function test_minimum_and_required_rules_remain_in_force_even_when_or_constraint_matches(): void
    {
        [, , $type] = $this->context();
        $target = $this->number($type, 'Q2', 1);
        $target->update(['is_required' => true, 'configuration' => ['min' => 1, 'max' => 10]]);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->valueRule('>', '5'), $this->valueRule('=', '0', 'or')]);
        foreach (['0', '11', ''] as $value) $this->assertInvalidAnswer($type->fresh(), [$target->uuid => $value], $target);
        $this->submission($type->fresh(), [$target->uuid => '6']);
    }

    public function test_foreign_later_self_disabled_and_non_numeric_sources_are_rejected_atomically(): void
    {
        [$user, $organization, $type] = $this->context();
        [, , $foreignType] = $this->context();
        $valid = $this->number($type, 'Q1', 1);
        $foreign = $this->number($foreignType, 'Foreign', 1);
        $later = $this->number($type, 'Later', 5);
        $disabled = $this->number($type, 'Disabled', 1);
        $disabled->update(['is_active' => false]);
        $text = $this->number($type, 'Text', 1);
        $text->update(['type' => 'text']);
        $target = $this->number($type, 'Q2', 2);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->questionRule($valid, '>=')]);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach ([$foreign, $later, $disabled, $text, $target] as $invalid) {
            $this->put(route('appointment-types.questions.update', [$type, $target]), [
                ...$this->payload('Q2', 2), 'numeric_constraints' => [$this->valueRule('>', '0'), $this->questionRule($invalid, '=')],
            ])->assertSessionHasErrors('question');
            $this->assertSame($valid->getKey(), $target->fresh()->numericConstraints->sole()->source_question_id);
        }
        $this->post(route('appointment-types.questions.store', $type), [
            ...$this->payload('Not numeric', 3), 'type' => 'text', 'numeric_constraints' => [$this->valueRule('>', '1')],
        ])->assertSessionHasErrors('numeric_constraints');
        $this->assertDatabaseMissing('appointment_questions', ['label' => 'Not numeric']);
    }

    public function test_malformed_operator_connector_and_operand_are_rejected(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach ([
            ['comparison_operator' => '=='], ['boolean_operator' => 'xor'], ['operand_type' => 'expression'],
            ['comparison_value' => 'abc'], ['comparison_value' => null], ['comparison_value' => []],
            ['source_question_uuid' => $source->uuid],
        ] as $override) {
            $this->post(route('appointment-types.questions.store', $type), [
                ...$this->payload('Invalid', 2), 'numeric_constraints' => [array_replace($this->valueRule('=', '1'), $override)],
            ])->assertSessionHasErrors();
        }
        $this->assertDatabaseMissing('appointment_questions', ['label' => 'Invalid']);
    }

    public function test_source_cannot_be_disabled_deleted_retyped_or_moved_after_its_target(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $target = $this->number($type, 'Q2', 2);
        app(NumericQuestionConstraintService::class)->sync($type, $target, [$this->questionRule($source, '>=')]);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach ([['is_active' => '0'], ['type' => 'text'], ['position' => 3]] as $override) {
            $this->put(route('appointment-types.questions.update', [$type, $source]), array_replace($this->payload('Q1', 1), $override))->assertSessionHasErrors('question');
            $this->assertTrue($source->fresh()->is_active);
            $this->assertSame('number', $source->fresh()->type->value);
            $this->assertSame(1, $source->fresh()->position);
        }
        $this->delete(route('appointment-types.questions.destroy', [$type, $source]))->assertSessionHasErrors('question');
        app(NumericQuestionConstraintService::class)->sync($type, $target, []);
        $this->delete(route('appointment-types.questions.destroy', [$type, $source]))->assertSessionHasNoErrors();
        $this->assertNull($source->fresh());
    }

    public function test_reusable_attachment_does_not_copy_cross_question_constraints_and_type_deletion_cascades(): void
    {
        [$user, $organization, $type] = $this->context();
        $source = $this->number($type, 'Q1', 1);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                ...$this->payload('Reusable Q2', 2), 'number_min' => '0', 'numeric_constraints' => [$this->questionRule($source, '>=')],
            ])->assertSessionHasNoErrors();
        $target = $type->questions()->where('label', 'Reusable Q2')->firstOrFail();
        $otherType = $this->type($organization, 'other');
        $copy = app(ReusableQuestionService::class)->attach($otherType, $target->reusableQuestion);
        $this->assertCount(0, $copy->numericConstraints);
        $this->assertSame($target->configuration, $copy->configuration);
        $type->delete();
        $this->assertDatabaseCount('appointment_question_numeric_constraints', 0);
        $this->assertNotNull($copy->fresh());
    }

    public function test_other_organization_cannot_edit_constraints(): void
    {
        [$user, $organization] = $this->context();
        [, , $foreignType] = $this->context();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $foreignType), [
                ...$this->payload('Forbidden', 1), 'numeric_constraints' => [$this->valueRule('>', '0')],
            ])->assertNotFound();
        $this->assertDatabaseMissing('appointment_questions', ['label' => 'Forbidden']);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id, 'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active]);

        return [$user, $organization, $this->type($organization)];
    }

    private function type(Organization $organization, string $slug = 'numeric-constraints'): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => $slug, 'slug' => $slug,
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed', 'fixed_price_minor' => 10000,
            'email_verification_mode' => 'none', 'is_active' => true,
        ]);
    }

    private function number(AppointmentType $type, string $label, int $position): AppointmentQuestion
    {
        return $type->questions()->create($this->payload($label, $position));
    }

    private function payload(string $label, int $position): array
    {
        return ['type' => 'number', 'label' => $label, 'position' => $position, 'is_required' => '0', 'is_active' => '1'];
    }

    private function questionRule(AppointmentQuestion $source, string $operator, string $join = 'and'): array
    {
        return ['operand_type' => 'question', 'source_question_uuid' => $source->uuid, 'comparison_operator' => $operator, 'boolean_operator' => $join];
    }

    private function valueRule(string $operator, string $value, string $join = 'and'): array
    {
        return ['operand_type' => 'value', 'comparison_value' => $value, 'comparison_operator' => $operator, 'boolean_operator' => $join];
    }

    private function attendeeRule(string $operator, string $join = 'and'): array
    {
        return ['operand_type' => 'attendee_count', 'comparison_operator' => $operator, 'boolean_operator' => $join];
    }

    private function submission(AppointmentType $type, array $answers, int $attendeeCount = 1): \App\Domain\Questionnaires\QuestionnaireSubmission
    {
        return app(QuestionnaireSubmissionService::class)->validateForBooking(Request::create('/', 'POST', ['answers' => $answers]), $type, 60, attendeeCount: $attendeeCount);
    }

    private function assertInvalidAnswer(AppointmentType $type, array $answers, AppointmentQuestion $target, int $attendeeCount = 1): void
    {
        try {
            $this->submission($type, $answers, $attendeeCount);
            $this->fail('A numeric answer violating a constraint was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.'.$target->uuid, $exception->errors());
        }
    }
}
