<?php

namespace App\Support;

/**
 * 排程步驟留給彙整通知的說明文字。
 *
 * AppSchedule 只看得到每個步驟的 exit code，所以同樣是「部分成功」的兩種狀況在
 * 通知裡長得一模一樣。指令自己知道這一輪發生什麼事，跑完把說明留在這裡，
 * AppSchedule 彙整通知時取走，通知就分得出差別。
 *
 * 綁成 singleton，指令與排程才拿得到同一份（同一個 process 裡 $this->call()
 * 跑完一個步驟就會回到 AppSchedule）。取走即刪：排程對兩個品牌各跑一次同一個
 * 指令，上一次的說明不可以貼到下一次的通知上。
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
