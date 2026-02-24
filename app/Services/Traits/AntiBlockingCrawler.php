<?php

namespace App\Services\Traits;

use Exception;
use Illuminate\Http\Client\RequestException;
use Throwable;

trait AntiBlockingCrawler
{
    /**
     * Counter for detail fetch operations.
     * Used to determine when to trigger detail batch rests.
     * Managed by resetDetailCounter() / shouldDetailBatchRest() / doDetailBatchRest().
     */
    private int $detailCounter = 0;

    /**
     * Get a random User-Agent from the configured pool.
     */
    private function getRandomUserAgent(): string
    {
        $userAgents = config('app.user_agents');

        // Fallback to a default User-Agent if configuration is missing or empty
        if (!is_array($userAgents) || empty($userAgents)) {
            logger()->warning('CRAWLER_USER_AGENTS not configured, using fallback User-Agent');
            return 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        }

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
        if ($e instanceof RequestException) {
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
        $delayMicroseconds = rand($delayConfig['min'], $delayConfig['max']);

        usleep($delayMicroseconds);
    }

    /**
     * Get random sleep duration in milliseconds for retry callback.
     */
    private function getRetrySleepMilliseconds(int $attempts, Exception $exception): int
    {
        $retryConfig = config('app.crawler.retry');

        return rand($retryConfig['sleep_min'] * 1000, $retryConfig['sleep_max'] * 1000);
    }

    /**
     * Determine if the exception is retryable (not a 403 error).
     */
    private function shouldRetry(Exception $exception): bool
    {
        return !$this->is403Error($exception);
    }

    /**
     * Check if offset batch rest should be triggered.
     */
    private function shouldOffsetBatchRest(int $offset, int $limit): bool
    {
        $interval = config('app.crawler.batch_rest.offset.interval');

        return $limit > 0 && $interval > 0 && ($offset % ($limit * $interval)) === 0 && $offset > 0;
    }

    /**
     * Perform offset batch rest.
     */
    private function doOffsetBatchRest(): void
    {
        $offsetConfig = config('app.crawler.batch_rest.offset');
        $sleepSec = rand($offsetConfig['sleep_min'], $offsetConfig['sleep_max']);

        logger()->info("Offset batch rest: sleeping {$sleepSec}s");
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
        $interval = config('app.crawler.batch_rest.detail.interval');

        return $interval > 0 && $this->detailCounter % $interval === 0 && $this->detailCounter > 0;
    }

    /**
     * Perform detail batch rest.
     */
    private function doDetailBatchRest(): void
    {
        $detailConfig = config('app.crawler.batch_rest.detail');
        $sleepSec = rand($detailConfig['sleep_min'], $detailConfig['sleep_max']);

        logger()->info("Detail batch rest: sleeping {$sleepSec}s");
        sleep($sleepSec);
    }
}
