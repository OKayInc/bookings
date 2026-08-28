<?php
namespace App\Domain\Questionnaires;
readonly class QuestionnaireQuote {
 /** @param list<QuestionnairePriceLine> $lines */
 public function __construct(public int $basePriceMinor, public int $totalMinor, public array $lines) {}
}
