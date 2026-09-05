<?php

namespace App\Domain\Resources;

use App\Enums\ConditionalResourceFulfillmentMode;
use App\Models\AppointmentQuestionResourceRule;
use App\Models\AppointmentType;
use App\Models\Resource;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResourceDepositService
{
    public function __construct(private readonly ResourceRequirementService $requirements)
    {
    }

    /**
     * @param array<string, mixed> $answers
     * @param null|array<int|string, string|int> $selectedResourceQuantities
     * @return list<ResourceDepositCharge>
     */
    public function charges(
        AppointmentType $type,
        array $answers,
        ?array $selectedResourceQuantities = null,
    ): array {
        $type->loadMissing([
            'resources',
            'questions.resourceRequirementRule.triggerOption',
            'questions.resourceRequirementRule.resources',
        ]);

        $quantities = $this->quantities($type, $selectedResourceQuantities);
        $conditionalResourceKeys = $type->questions
            ->map(fn ($question) => $question->resourceRequirementRule?->resources ?? collect())
            ->flatten()
            ->map(fn (Resource $resource): string => $resource->getKey())
            ->all();
        $conditionalResourceKeys = array_fill_keys($conditionalResourceKeys, true);
        $charges = [];

        foreach ($this->requirements->requiredResources($type) as $resource) {
            if (isset($conditionalResourceKeys[$resource->getKey()]) || ! isset($quantities[$resource->getKey()])) {
                continue;
            }
            $charge = $this->resourceCharge($resource, $quantities[$resource->getKey()]);
            if ($charge !== null) {
                $charges[] = $charge;
            }
        }

        foreach ($this->requirements->replacementGroups($type) as $group) {
            $available = $group->filter(fn (Resource $resource): bool => isset($quantities[$resource->getKey()]));
            $charge = $this->oneOfCharge($available, $this->requirements->replacementGroup($group->first()) ?? 'Replacement resource');
            if ($charge !== null) {
                $charges[] = $charge;
            }
        }

        foreach ($type->questions as $question) {
            $rule = $question->resourceRequirementRule;
            if ($rule === null || ! $this->isTriggered($rule, $answers[$question->uuid] ?? null)) {
                continue;
            }

            $available = $rule->resources->filter(fn (Resource $resource): bool => isset($quantities[$resource->getKey()]));
            if ($rule->fulfillment_mode === ConditionalResourceFulfillmentMode::OneOf) {
                $charge = $this->oneOfCharge($available, $rule->group_name, $rule, $question->uuid, $question->label);
                if ($charge !== null) {
                    $charges[] = $charge;
                }
                continue;
            }

            foreach ($available as $resource) {
                $charge = $this->resourceCharge(
                    $resource,
                    $quantities[$resource->getKey()],
                    $rule,
                    $question->uuid,
                    $question->label,
                );
                if ($charge !== null) {
                    $charges[] = $charge;
                }
            }
        }

        return $charges;
    }

    /** @param null|array<int|string, string|int> $selectedResourceQuantities */
    public function total(
        AppointmentType $type,
        array $answers = [],
        ?array $selectedResourceQuantities = null,
    ): int {
        $total = 0;
        foreach ($this->charges($type, $answers, $selectedResourceQuantities) as $charge) {
            if ($charge->amountMinor > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('The refundable resource deposit is too large.');
            }
            $total += $charge->amountMinor;
        }

        return $total;
    }

    /** @param Collection<int, Resource> $resources */
    private function oneOfCharge(
        Collection $resources,
        string $groupName,
        ?AppointmentQuestionResourceRule $rule = null,
        ?string $questionUuid = null,
        ?string $questionLabel = null,
    ): ?ResourceDepositCharge {
        $candidate = $resources
            ->map(fn (Resource $resource): ?ResourceDepositCharge => $this->resourceCharge(
                $resource,
                1,
                $rule,
                $questionUuid,
                $questionLabel,
            ))
            ->filter()
            ->sortByDesc(fn (ResourceDepositCharge $charge): int => $charge->amountMinor)
            ->first();

        if (! $candidate instanceof ResourceDepositCharge) {
            return null;
        }

        return new ResourceDepositCharge(
            null,
            $groupName.' (one available resource)',
            1,
            $candidate->amountMinor,
            $candidate->amountMinor,
            $candidate->configurationSource,
            $questionUuid,
            $questionLabel,
        );
    }

    private function resourceCharge(
        Resource $resource,
        int $quantity,
        ?AppointmentQuestionResourceRule $rule = null,
        ?string $questionUuid = null,
        ?string $questionLabel = null,
    ): ?ResourceDepositCharge {
        $override = $rule?->resources->first(
            fn (Resource $candidate): bool => hash_equals($candidate->getKey(), $resource->getKey()),
        )?->pivot?->deposit_amount_minor;
        $configured = $override !== null;
        $unitAmount = (int) ($configured ? $override : ($resource->deposit_amount_minor ?? 0));
        if ($unitAmount <= 0) {
            return null;
        }

        $quantity = $resource->usesQuantityInventory() ? max(1, $quantity) : 1;
        if ($quantity > intdiv(PHP_INT_MAX, $unitAmount)) {
            throw new InvalidArgumentException('The refundable resource deposit is too large.');
        }

        return new ResourceDepositCharge(
            $resource->uuid,
            $resource->name,
            $quantity,
            $unitAmount,
            $unitAmount * $quantity,
            $configured ? 'question_override' : 'resource_default',
            $questionUuid,
            $questionLabel,
        );
    }

    /** @return array<string, int> */
    private function quantities(AppointmentType $type, ?array $selected): array
    {
        if ($selected === null) {
            return $type->resources->mapWithKeys(fn (Resource $resource): array => [
                $resource->getKey() => max(1, (int) ($resource->pivot?->quantity_required ?? 1)),
            ])->all();
        }

        $quantities = [];
        foreach ($selected as $key => $value) {
            if (is_int($key)) {
                $quantities[(string) $value] = 1;
            } else {
                $quantities[$key] = max(1, (int) $value);
            }
        }

        return $quantities;
    }

    private function isTriggered(AppointmentQuestionResourceRule $rule, mixed $answer): bool
    {
        $triggerUuid = $rule->triggerOption?->uuid;
        if ($triggerUuid === null) {
            return false;
        }

        $selected = is_array($answer) ? $answer : [$answer];

        return in_array($triggerUuid, $selected, true);
    }
}
