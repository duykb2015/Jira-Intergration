<?php

namespace App\Contracts;

interface ClockifyClient
{
    public function currentUser(string $apiToken): array;
    public function workspaces(string $apiToken): array;
    public function timeEntries(string $apiToken, string $workspaceId, string $userId, string $from, string $to): array;
}
