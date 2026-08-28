<?php
namespace App\Domain\Questionnaires;
readonly class QuestionnaireSubmission {
 /** @param list<array<string,mixed>> $answers */
 public function __construct(public array $answers, public QuestionnaireQuote $quote) {}
}
