<?php
namespace Tests\Unit;

use App\Domain\Webhooks\WebhookDestination;
use PHPUnit\Framework\TestCase;

class M10R2WebhookDestinationTest extends TestCase
{
    public function test_public_https_host_is_accepted(): void
    {
        $guard = new WebhookDestination();
        $this->assertSame('receiver.example.com', $guard->host('https://receiver.example.com/hooks'));
        $guard->assertPublicAddress('8.8.8.8');
    }
    public function test_unsafe_url_forms_are_rejected(): void
    {
        $guard = new WebhookDestination();
        foreach (['http://example.com', 'https://localhost/', 'https://127.0.0.1/', 'https://[::1]/',
            'https://user:password@example.com', 'https://example.com:8443/', 'https://example.com/#fragment'] as $url) {
            try { $guard->host($url); $this->fail('Accepted '.$url); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }
    public function test_private_reserved_metadata_and_multicast_addresses_are_rejected(): void
    {
        $guard = new WebhookDestination();
        foreach (['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1',
            '0.0.0.0', '224.0.0.1', '240.0.0.1', '192.0.2.1', '198.18.0.1', '168.63.129.16', '::1'] as $ip) {
            try { $guard->assertPublicAddress($ip); $this->fail('Accepted '.$ip); }
            catch (\RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }
}
