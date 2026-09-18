<?php

namespace Tests\Feature;

use Tests\TestCase;

class MailgunConfigurationTest extends TestCase
{
    public function test_mailgun_api_transport_is_configured(): void
    {
        $this->assertSame('mailgun', config('mail.mailers.mailgun.transport'));
        $this->assertArrayHasKey('mailgun', config('services'));
        $this->assertArrayHasKey('domain', config('services.mailgun'));
        $this->assertArrayHasKey('secret', config('services.mailgun'));
        $this->assertArrayHasKey('endpoint', config('services.mailgun'));
        $this->assertSame('https', config('services.mailgun.scheme'));
    }
}
