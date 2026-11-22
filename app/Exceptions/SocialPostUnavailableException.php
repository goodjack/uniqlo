<?php

namespace App\Exceptions;

use App\Enums\SocialSyncStatus;
use RuntimeException;
use Throwable;

/**
 * 確定看不到這篇貼文（已刪除、只給登入的人看），不是暫時連不上；帶著原因，讓整理清單的人分得出來。
 */
class SocialPostUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly SocialSyncStatus $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
