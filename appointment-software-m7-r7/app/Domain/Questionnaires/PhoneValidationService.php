<?php
namespace App\Domain\Questionnaires;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;
class PhoneValidationService {
    public function validateAndNormalize(string $value, ?string $region=null): string {
        $util=PhoneNumberUtil::getInstance();
        try { $number=$util->parse(trim($value), strtoupper($region ?: config('questionnaire.default_phone_region','CA'))); }
        catch (\Throwable) { throw new RuntimeException('Enter a valid telephone number.'); }
        if (!$util->isValidNumber($number)) throw new RuntimeException('Enter a valid telephone number.');
        return $util->format($number, PhoneNumberFormat::E164);
    }
    public function supportedRegions(): array { $r=PhoneNumberUtil::getInstance()->getSupportedRegions(); sort($r); return $r; }
}
