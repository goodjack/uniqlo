<?php

namespace App\Listeners;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class AppTaskEventSubscriber
{
    /**
     * Handle app task starting events.
     */
    public function handleAppTaskStarting(AppTaskStarting $event): void
    {
        $this->handleEvent($event, 'start');
    }

    /**
     * Handle app task finished events.
     */
    public function handleAppTaskFinished(AppTaskFinished $event): void
    {
        $this->handleEvent($event, 'end');
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AppTaskStarting::class => 'handleAppTaskStarting',
            AppTaskFinished::class => 'handleAppTaskFinished',
        ];
    }

    private function handleEvent($event, string $status): void
    {
        $message = sprintf(
            '%s%s%s %s%s',
            $event->task,
            $event->brand ? " {$event->brand}" : '',
            $event->country ? " {$event->country}" : '',
            $status,
            $event->data ? ', ' . json_encode($event->data) : '',
        );

        logger()->debug($message);

        $this->sendDiscordNotification($event, $status);
    }

    private function sendDiscordNotification($event, string $status = 'start'): void
    {
        $webhookUrl = config('app.discord_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        $json = $this->getDiscordJson($event, $status);

        try {
            Http::post($webhookUrl, $json)->throwUnlessStatus(200);
        } catch (RequestException $e) {
            logger()->error('Failed to send Discord notification', [
                'message' => $e->getMessage(),
                'response' => $e->response->json(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function getDiscordJson($event, string $status = 'start'): array
    {
        $color = match ($status) {
            'start' => 0x5865F2,
            'end' => 0x56F287,
            default => 0xFFFFFF,
        };

        $json = [
            'username' => 'UQ Search Notifier',
            'embeds' => [
                [
                    'title' => "{$event->task} {$status}",
                    'color' => $color,
                    'author' => [
                        'name' => config('app.name'),
                        'url' => config('app.url'),
                    ],
                    'footer' => [
                        'text' => ucfirst(config('app.env')),
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        $fields = ['brand', 'country', 'data'];

        foreach ($fields as $field) {
            if ($event->$field) {
                $json['embeds'][0]['fields'][] = [
                    'name' => ucfirst($field),
                    'value' => ($field === 'data') ? json_encode($event->data) : $event->$field,
                    'inline' => ($field !== 'data'),
                ];
            }
        }

        return $json;
    }
}
