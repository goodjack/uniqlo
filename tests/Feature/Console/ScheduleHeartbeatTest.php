<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ScheduleHeartbeatTest extends TestCase
{
    private const START_URL = 'https://heartbeat.example/start';

    private const FINISH_URL = 'https://heartbeat.example/finish';

    public function test_daily_schedule_pings_start_before_and_finish_after(): void
    {
        Http::fake();
        $event = $this->dailyScheduleEvent(self::START_URL, self::FINISH_URL);

        $event->callBeforeCallbacks($this->app);
        $this->assertSame([self::START_URL], $this->sentUrls());

        $event->callAfterCallbacks($this->app);
        $this->assertSame([self::START_URL, self::FINISH_URL], $this->sentUrls());
    }

    public function test_daily_schedule_sends_nothing_when_urls_are_not_set(): void
    {
        Http::fake();
        $event = $this->dailyScheduleEvent(null, null);

        $event->callBeforeCallbacks($this->app);
        $event->callAfterCallbacks($this->app);

        Http::assertNothingSent();
    }

    public function test_an_unreachable_monitor_does_not_stop_the_daily_schedule(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        Log::spy();
        $event = $this->dailyScheduleEvent(self::START_URL, self::FINISH_URL);

        $event->callBeforeCallbacks($this->app);
        $event->callAfterCallbacks($this->app);

        Log::shouldHaveReceived('error')->twice();
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
        return Http::recorded()->map(fn (array $pair) => $pair[0]->url())->all();
    }
}
