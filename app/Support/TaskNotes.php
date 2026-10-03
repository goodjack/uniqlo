<?php

namespace App\Support;

/**
 * 指令留給 AppSchedule 彙整通知的說明文字（AppSchedule 只拿得到 exit code）。
 *
 * 綁成 singleton，同一個 process 裡指令與排程才拿到同一份。取走即刪：同一個指令
 * 會對兩個品牌各跑一次，上一次的說明不能貼到下一次。
 */
final class TaskNotes
{
    /** @var array<string, string> */
    private array $notes = [];

    public function put(string $command, ?string $target, string $note): void
    {
        $this->notes[$this->key($command, $target)] = $note;
    }

    public function pull(string $command, ?string $target): ?string
    {
        $key = $this->key($command, $target);

        $note = $this->notes[$key] ?? null;
        unset($this->notes[$key]);

        return $note;
    }

    private function key(string $command, ?string $target): string
    {
        return trim("{$command} {$target}");
    }
}
