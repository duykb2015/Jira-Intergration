<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessClockifyWebhook;
use App\Models\ClockifyConnection;
use App\Models\WebhookEvent;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClockifyWebhookController extends Controller
{
    public function __invoke(Request $request, string $connectionUuid, string $secret): JsonResponse
    {
        $connection = ClockifyConnection::query()->where('uuid', $connectionUuid)->first();
        if (! $connection || $connection->status !== 'connected' || ! $connection->matchesWebhookSecret($secret)) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) return response()->json(['message' => 'Invalid JSON payload.'], 422);
        $hash = CanonicalJson::hash($payload);
        try {
            $event = WebhookEvent::query()->create([
                'clockify_connection_id' => $connection->id,
                'event_type' => data_get($payload, 'type') ?? data_get($payload, 'eventType'),
                'external_event_id' => data_get($payload, 'id') ?? data_get($payload, 'eventId'),
                'external_object_id' => data_get($payload, 'timeEntry.id') ?? data_get($payload, 'data.id'),
                'payload_hash' => $hash, 'payload' => $payload,
                'status' => 'pending', 'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (WebhookEvent::query()->where('clockify_connection_id', $connection->id)->where('payload_hash', $hash)->exists()) {
                return response()->json(['status' => 'duplicate'], 202);
            }
            throw $e;
        }
        ProcessClockifyWebhook::dispatch($event->id);
        return response()->json(['status' => 'accepted'], 202);
    }
}
