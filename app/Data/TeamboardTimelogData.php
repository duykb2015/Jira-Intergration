<?php

namespace App\Data;

final readonly class TeamboardTimelogData
{
    public function __construct(
        public string $jiraIssueKey,
        public string $userId,
        public string $startedAt,
        public ?string $endedAt,
        public ?int $durationSeconds,
        public ?string $description,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
