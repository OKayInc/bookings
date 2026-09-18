<?php
namespace App\Domain\Webhooks;

class WebhookTransport
{
    public function send(string $url, string $body, array $headers): int
    {
        if (! extension_loaded('curl')) { throw new \RuntimeException('PHP cURL is required for outgoing webhooks.'); }
        [$host, $address] = app(WebhookDestination::class)->resolve($url);
        $handle = curl_init($url);
        $received = 0;
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => ["{$host}:443:{$address}"],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$received): int {
                $received += strlen($chunk);
                return $received > 65536 ? 0 : strlen($chunk);
            },
        ]);
        try {
            if (curl_exec($handle) === false) { throw new \RuntimeException('Connection, TLS, timeout or response-size failure.'); }
            return (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally { curl_close($handle); }
    }
}
