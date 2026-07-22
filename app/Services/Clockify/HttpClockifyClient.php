<?php

namespace App\Services\Clockify;

use App\Contracts\ClockifyClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HttpClockifyClient implements ClockifyClient
{
    public function currentUser(string $apiToken): array
    {
        return $this->http($apiToken)->get('/api/v1/user')->throw()->json();
    }

    public function workspaces(string $apiToken): array
    {
        return $this->http($apiToken)->get('/api/v1/workspaces')->throw()->json();
    }

    public function timeEntries(string $apiToken, string $workspaceId, string $userId, string $from, string $to): array
    {
        return $this->http($apiToken)
            ->get("/api/v1/workspaces/{$workspaceId}/user/{$userId}/time-entries", ['start' => $from, 'end' => $to, 'hydrated' => 'true'])
            ->throw()->json();
    }

    private function http(string $token): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.clockify.base_url'), '/'))
            ->acceptJson()->withHeaders(['X-Api-Key' => $token])
            ->timeout((int) config('services.clockify.timeout', 15))
            ->retry(
                (int) config('services.clockify.retries', 2),
                500,
                fn (\Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                throw: false,
            );
    }
}
