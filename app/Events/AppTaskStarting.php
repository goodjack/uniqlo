<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppTaskStarting
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $task,
        public ?string $brand = null,
        public ?string $country = null,
        public ?array $data = null,
    ) {
    }
}
