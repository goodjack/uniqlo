<?php

namespace App\Services\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

trait AntiBlockingCrawler
{
    /** @var int */
    private $detailCounter = 0;

    /**
     * Get a random User-Agent from the configured pool.
     */
    private function getRandomUserAgent(): string
    {
        $userAgents = config('app.user_agents');

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Build complete browser headers for the request.
     */
    private function buildHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Referer' => 'https://www.uniqlo.com/',
            'Origin' => 'https://www.uniqlo.com',
        ];
    }

    /**
     * Check if the exception is a 403 Forbidden error.
     */
    private function is403Error(Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            return $e->response?->status() === 403;
        }

        return false;
    }

    /**
     * Apply random delay in microseconds.
     */
    private function randomDelay(): void
    {
        $delayConfig = config('app.crawler.delay');
        $delayMs = rand($delayConfig['min'], $delayConfig['max']);

        usleep($delayMs);
    }

    /**
     * Apply random sleep in seconds.
     */
    private function randomSleep(): void
    {
        $retryConfig = config('app.crawler.retry');
        $sleepSec = rand($retryConfig['sleep_min'], $retryConfig['sleep_max']);

        sleep($sleepSec);
    }

    /**
     * Check if offset batch rest should be triggered.
     */
    private function shouldOffsetBatchRest(int $offset, int $limit): bool
    {
        $interval = config('app.crawler.batch_rest.offset.interval');

        return ($offset / $limit) % $interval === 0 && $offset > 0;
    }

    /**
     * Perform offset batch rest.
     */
    private function doOffsetBatchRest(): void
    {
        $offsetConfig = config('app.crawler.batch_rest.offset');
        $sleepSec = rand($offsetConfig['sleep_min'], $offsetConfig['sleep_max']);

        Log::info("Offset batch rest: sleeping {$sleepSec}s");
        sleep($sleepSec);
    }

    /**
     * Reset detail counter.
     */
    private function resetDetailCounter(): void
    {
        $this->detailCounter = 0;
    }

    /**
     * Check if detail batch rest should be triggered.
     */
    private function shouldDetailBatchRest(): bool
    {
        return $this->detailCounter % config('app.crawler.batch_rest.detail.interval') === 0 && $this->detailCounter > 0;
    }

    /**
     * Perform detail batch rest.
     */
    private function doDetailBatchRest(): void
    {
        $detailConfig = config('app.crawler.batch_rest.detail');
        $sleepSec = rand($detailConfig['sleep_min'], $detailConfig['sleep_max']);

        Log::info("Detail batch rest: sleeping {$sleepSec}s");
        sleep($sleepSec);
    }
}
