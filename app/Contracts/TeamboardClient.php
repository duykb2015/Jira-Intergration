<?php

namespace App\Contracts;

use App\Data\TeamboardTimelogData;

interface TeamboardClient
{
    public function isConfigured(): bool;
    public function find(TeamboardTimelogData $data): ?array;
    public function create(TeamboardTimelogData $data): array;
    public function update(string $timelogId, TeamboardTimelogData $data): array;
}
