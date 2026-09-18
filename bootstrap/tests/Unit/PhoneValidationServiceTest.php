<?php
namespace Tests\Unit;
use App\Domain\Questionnaires\PhoneValidationService;
use Tests\TestCase;
class PhoneValidationServiceTest extends TestCase {
 public function test_canadian_number_is_normalized_to_e164(): void { $this->assertSame('+16135551234',app(PhoneValidationService::class)->validateAndNormalize('(613) 555-1234','CA')); }
}
