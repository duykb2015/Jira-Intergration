<?php

namespace App\Data;

final readonly class ResolvedJiraIssue
{
    public function __construct(public ?string $id, public ?string $key, public ?string $reason = null) {}
    public function resolved(): bool { return $this->key !== null; }
}
