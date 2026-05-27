<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TelegramHttpClientFactory
{
    public function make(): PendingRequest
    {
        $request = Http::withOptions($this->baseOptions());

        $connectTimeout = (float) config('services.telegram.connect_timeout', 10);
        if ($connectTimeout > 0) {
            $request = $request->connectTimeout($connectTimeout);
        }

        $timeout = (float) config('services.telegram.timeout', 10);
        if ($timeout > 0) {
            $request = $request->timeout($timeout);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseOptions(): array
    {
        $options = [
            'verify' => (bool) config('services.telegram.verify_ssl', true),
        ];

        $curl = $this->curlOptions();
        if ($curl !== []) {
            $options['curl'] = $curl;
        }

        return $options;
    }

    /**
     * @return array<int, int>
     */
    private function curlOptions(): array
    {
        $resolve = strtolower((string) config('services.telegram.ip_resolve', ''));

        if ($resolve === 'v4' && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            return [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ];
        }

        if ($resolve === 'v6' && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V6')) {
            return [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6,
            ];
        }

        return [];
    }
}
