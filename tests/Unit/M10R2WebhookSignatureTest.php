<?php
namespace Tests\Unit;

use App\Domain\Webhooks\WebhookSignature;
use PHPUnit\Framework\TestCase;

class M10R2WebhookSignatureTest extends TestCase
{
    public function test_signature_validates_exact_bytes_and_rejects_wrong_secrets_tampering_and_expiry(): void
    {
        $body = '{"id":"example"}'; $secret = str_repeat('a', 64); $time = 1800000000;
        $signature = WebhookSignature::sign($body, $secret, $time);
        $this->assertSame('t='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $secret), $signature);
        $this->assertTrue(WebhookSignature::verify($body, $signature, $secret, $time + 299));
        $this->assertFalse(WebhookSignature::verify($body.' ', $signature, $secret, $time));
        $this->assertFalse(WebhookSignature::verify($body, $signature, 'wrong', $time));
        $this->assertFalse(WebhookSignature::verify($body, $signature, $secret, $time + 301));
        $this->assertFalse(WebhookSignature::verify($body, $signature, $secret, $time - 301));
        $this->assertFalse(WebhookSignature::verify($body, 'malformed', $secret, $time));
    }
}
