<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ClockifyTimeEntryData
{
    public function __construct(
        public string $id,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public ?int $durationSeconds,
        public ?string $taskId,
        public ?string $taskName,
        public ?string $projectId,
        public ?string $description,
        public array $raw,
    ) {}
}
