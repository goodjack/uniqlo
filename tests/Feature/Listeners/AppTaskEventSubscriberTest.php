<?php

namespace Tests\Feature\Listeners;

use App\Events\AppTaskFailed;
use App\Events\AppTaskFinished;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppTaskEventSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.discord_webhook_url', 'https://discord.test/webhook');
        Http::fake();
    }

    /**
     * 失敗原因是中文，序列化時不能被逃脫成 \uXXXX，否則 Discord 上是一串亂碼、
     * 等於分辨不出失敗類型。
     */
    public function test_failure_notification_keeps_chinese_readable(): void
    {
        AppTaskFailed::dispatch('AppSchedule', null, null, [
            'failed_steps' => ['hmall-product:fetch UNIQLO（部分成功）'],
            'total_steps' => 15,
        ]);

        Http::assertSent(function ($request) {
            // 檢查 Discord 解析後真正會顯示的那個欄位值。
            // 外層 payload 本來就會被 JSON 逃脫，那層 Discord 自己會還原；
            // 出問題的是 data 欄位——它已經先被序列化成字串，逃脫序列會被當文字顯示。
            $value = $request->data()['embeds'][0]['fields'][0]['value'];

            return str_contains($value, 'hmall-product:fetch UNIQLO（部分成功）');
        });
    }

    public function test_failure_notification_uses_a_different_colour_from_success(): void
    {
        AppTaskFailed::dispatch('AppSchedule');
        AppTaskFinished::dispatch('AppSchedule');

        $colours = [];

        Http::assertSent(function ($request) use (&$colours) {
            $colours[] = $request->data()['embeds'][0]['color'];

            return true;
        });

        $this->assertCount(2, $colours);
        $this->assertNotSame($colours[0], $colours[1]);
    }

    public function test_nothing_is_sent_when_the_webhook_is_not_configured(): void
    {
        Config::set('app.discord_webhook_url', null);

        AppTaskFailed::dispatch('AppSchedule');

        Http::assertNothingSent();
    }
}
