<?php

namespace App\Jobs;

use App\Exceptions\UnparseableClockifyWebhook;
use App\Models\WebhookEvent;
use App\Services\Clockify\ClockifyEntryProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessClockifyWebhook implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $eventId) {}

    public function handle(ClockifyEntryProcessor $processor): void
    {
        $event = WebhookEvent::query()->with('connection')->findOrFail($this->eventId);
        $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1]);
        try {
            $processor->processPayload($event->connection, $event->payload);
            $event->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null]);
        } catch (UnparseableClockifyWebhook $e) {
            $event->update(['status' => 'requires_review', 'processed_at' => now(), 'last_error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            throw $e;
        }
    }
}
