<?php

namespace App\Services\Clockify;

use App\Contracts\ClockifyClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;

class ClockifyConnectionVerifier
{
    public function __construct(private readonly ClockifyClient $client) {}

    public function verify(string $token, string $workspaceId): array
    {
        try {
            $user = $this->client->currentUser($token);
            $workspace = collect($this->client->workspaces($token))
                ->first(fn ($item) => is_array($item) && (string) ($item['id'] ?? '') === $workspaceId);
        } catch (RequestException) {
            throw ValidationException::withMessages([
                'data.api_token' => 'Clockify token không hợp lệ hoặc Clockify không phản hồi.',
            ]);
        }

        if (! isset($user['id'])) {
            throw ValidationException::withMessages(['data.api_token' => 'Response user từ Clockify không hợp lệ.']);
        }

        if (! $workspace) {
            throw ValidationException::withMessages(['data.clockify_workspace_id' => 'Token không có quyền truy cập workspace này.']);
        }

        return [$user, $workspace];
    }
}
