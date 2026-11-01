<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_the_daily_crawl_never_runs_twice_at_once(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => Str::after((string) $event->command, "'artisan' ") === 'app:schedule');

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        // 一輪可能超過一天，鎖要撐得比一天久；也不能到兩天，中止留下的鎖才不會連擋兩輪
        $this->assertGreaterThan(1440, $event->expiresAt);
        $this->assertLessThan(2880, $event->expiresAt);
    }
}
