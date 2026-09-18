<?php
namespace App\Domain\Webhooks;

final class WebhookSignature
{
    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
    public static function verify(string $body, string $header, string $secret, int $now, int $tolerance = 300): bool
    {
        if ($secret === '' || $tolerance < 0 || ! preg_match('/^t=([0-9]{1,12}),v1=([a-f0-9]{64})$/D', $header, $matches)) { return false; }
        if (abs($now - (int) $matches[1]) > $tolerance) { return false; }
        return hash_equals(hash_hmac('sha256', $matches[1].'.'.$body, $secret), $matches[2]);
    }
}
