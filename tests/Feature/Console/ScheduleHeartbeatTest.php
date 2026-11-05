<?php

namespace Tests\Feature\Console;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleHeartbeatTest extends TestCase
{
    private const START_URL = 'https://uptime.betterstack.com/api/v1/heartbeat/start-token';

    private const FINISH_URL = 'https://uptime.betterstack.com/api/v1/heartbeat/finish-token';

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentRequests = [];
        $handler = HandlerStack::create(new MockHandler(array_fill(0, 4, new Response(200))));
        $handler->push(Middleware::history($this->sentRequests));
        $this->app->instance(Client::class, new Client(['handler' => $handler]));
    }

    public function test_daily_schedule_pings_start_before_and_finish_after(): void
    {
        $event = $this->dailyScheduleEvent(self::START_URL, self::FINISH_URL);

        $event->callBeforeCallbacks($this->app);
        $this->assertSame([self::START_URL], $this->sentUrls());

        $event->callAfterCallbacks($this->app);
        $this->assertSame([self::START_URL, self::FINISH_URL], $this->sentUrls());
    }

    public function test_daily_schedule_sends_nothing_when_urls_are_not_set(): void
    {
        $event = $this->dailyScheduleEvent(null, null);

        $event->callBeforeCallbacks($this->app);
        $event->callAfterCallbacks($this->app);

        $this->assertSame([], $this->sentUrls());
    }

    private function dailyScheduleEvent(?string $startUrl, ?string $finishUrl): Event
    {
        config([
            'app.schedule_heartbeat.start_url' => $startUrl,
            'app.schedule_heartbeat.finish_url' => $finishUrl,
        ]);

        $events = array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event) => str_ends_with($event->command, "'artisan' app:schedule"),
        );
        $this->assertCount(1, $events);

        return array_values($events)[0];
    }

    /** @return array<int, string> */
    private function sentUrls(): array
    {
        return array_map(fn (array $entry) => (string) $entry['request']->getUri(), $this->sentRequests);
    }
}
