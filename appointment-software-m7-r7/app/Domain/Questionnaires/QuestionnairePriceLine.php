<?php
namespace App\Domain\Questionnaires;
readonly class QuestionnairePriceLine {
 public function __construct(public string $sourceType, public ?string $sourceUuid, public string $label, public string $lineType, public string $quantity, public int $amountMinor, public array $metadata=[]) {}
}
