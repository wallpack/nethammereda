<?php

namespace Tests\Unit\Telegram;

use App\Services\Telegram\TelegramHttpClientFactory;
use Illuminate\Http\Client\PendingRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramHttpClientFactoryTest extends TestCase
{
    #[Test]
    public function it_applies_ipv6_resolution_when_configured(): void
    {
        config()->set('services.telegram.verify_ssl', true);
        config()->set('services.telegram.ip_resolve', 'v6');
        config()->set('services.telegram.connect_timeout', 4);
        config()->set('services.telegram.timeout', 9);

        $factory = new TelegramHttpClientFactory();
        $request = $factory->make();
        $options = $this->optionsOf($request);

        $this->assertTrue(isset($options['verify']) && $options['verify'] === true);
        $this->assertEquals(4, $options['connect_timeout'] ?? null);
        $this->assertEquals(9, $options['timeout'] ?? null);
        $this->assertArrayHasKey('curl', $options);
        $this->assertSame(CURL_IPRESOLVE_V6, $options['curl'][CURLOPT_IPRESOLVE] ?? null);
    }

    #[Test]
    public function it_skips_ip_resolve_when_not_configured(): void
    {
        config()->set('services.telegram.verify_ssl', true);
        config()->set('services.telegram.ip_resolve', '');
        config()->set('services.telegram.connect_timeout', 10);
        config()->set('services.telegram.timeout', 10);

        $factory = new TelegramHttpClientFactory();
        $request = $factory->make();
        $options = $this->optionsOf($request);

        $this->assertArrayNotHasKey('curl', $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsOf(PendingRequest $request): array
    {
        return (function () {
            return $this->options;
        })->call($request);
    }
}
