<?php

namespace App\Services\Teamboard;

use App\Contracts\TeamboardClient;
use App\Data\TeamboardTimelogData;
use LogicException;

class PendingTeamboardClient implements TeamboardClient
{
    public function isConfigured(): bool { return false; }
    public function find(TeamboardTimelogData $data): ?array { return null; }
    public function create(TeamboardTimelogData $data): array { throw $this->unavailable(); }
    public function update(string $timelogId, TeamboardTimelogData $data): array { throw $this->unavailable(); }

    private function unavailable(): LogicException
    {
        return new LogicException('Teamboard API contract is not configured.');
    }
}
