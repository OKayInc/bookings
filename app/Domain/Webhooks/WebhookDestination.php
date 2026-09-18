<?php
namespace App\Domain\Webhooks;

class WebhookDestination
{
    public function host(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Use a public HTTPS URL on port 443 without credentials or a fragment.');
        }
        $host = strtolower($parts['host']);
        if (strlen($host) > 253 || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)) {
            throw new \InvalidArgumentException('Use a public DNS hostname, not an IP address or local hostname.');
        }
        return $host;
    }

    public function resolve(string $url): array
    {
        $host = $this->host($url);
        // Only IPv4 is used for delivery. Resolve again for each attempt and pin the validated address.
        $addresses = gethostbynamel($host) ?: [];
        if (! $addresses) { throw new \RuntimeException('Destination has no usable public IPv4 address.'); }
        foreach ($addresses as $ip) { $this->assertPublicAddress($ip); }
        return [$host, $addresses[0]];
    }

    public function assertPublicAddress(string $ip): void
    {
        $denied = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
            '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
            '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '168.63.129.16/32'];
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $denied)) {
            throw new \RuntimeException('Destination resolves to a prohibited network address.');
        }
    }
}
