<?php

namespace App\Services\Clockify;

use App\Contracts\ClockifyWebhookParser;
use App\Contracts\TeamboardClient;
use App\Data\ClockifyTimeEntryData;
use App\Data\TeamboardTimelogData;
use App\Models\ClockifyConnection;
use App\Models\ClockifyTimeEntry;
use App\Models\TeamboardTimelogMapping;
use App\Services\JiraIssueResolver;
use App\Support\CanonicalJson;

class ClockifyEntryProcessor
{
    public function __construct(
        private ClockifyWebhookParser $parser,
        private JiraIssueResolver $resolver,
        private TeamboardClient $teamboard,
    ) {}

    public function processPayload(ClockifyConnection $connection, array $payload): ClockifyTimeEntry
    {
        return $this->process($connection, $this->parser->parse($payload));
    }

    public function process(ClockifyConnection $connection, ClockifyTimeEntryData $data): ClockifyTimeEntry
    {
        $sourceHash = CanonicalJson::hash($data->raw);
        $entry = ClockifyTimeEntry::query()->firstOrNew([
            'clockify_connection_id' => $connection->id,
            'clockify_time_entry_id' => $data->id,
        ]);
        $unchanged = $entry->exists && hash_equals((string) $entry->source_payload_hash, $sourceHash);

        if ($unchanged && ($entry->sync_status === 'synced'
            || ($entry->sync_status === 'pending_teamboard' && ! $this->teamboard->isConfigured()))) {
            return $entry;
        }

        $resolved = $this->resolver->resolve($connection, $data);
        $entry->fill([
            'clockify_task_id' => $data->taskId,
            'clockify_project_id' => $data->projectId,
            'jira_issue_id' => $resolved->id,
            'jira_issue_key' => $resolved->key,
            'started_at' => $data->startedAt,
            'ended_at' => $data->endedAt,
            'duration_seconds' => $data->durationSeconds,
            'description' => $data->description,
            'raw_data' => $data->raw,
            'source_payload_hash' => $sourceHash,
            'sync_status' => $resolved->resolved() ? 'pending_teamboard' : 'requires_review',
            'last_error' => $resolved->reason,
        ])->save();

        if (! $resolved->resolved()) {
            return $entry;
        }

        $integrationUser = $connection->integrationUser;
        $teamboardUser = $integrationUser?->teamboard_user_id ?: $integrationUser?->jira_account_id;
        if (! $teamboardUser || ! $this->teamboard->isConfigured()) {
            return $entry;
        }

        $dto = new TeamboardTimelogData(
            $resolved->key, $teamboardUser, $data->startedAt->toIso8601String(),
            $data->endedAt?->toIso8601String(), $data->durationSeconds, $data->description,
        );
        $request = $dto->toArray();
        $payloadHash = CanonicalJson::hash($request);
        $mapping = TeamboardTimelogMapping::query()->firstOrNew([
            'clockify_connection_id' => $connection->id,
            'clockify_time_entry_id' => $entry->id,
        ]);
        if ($mapping->exists && $mapping->payload_hash === $payloadHash && $mapping->teamboard_timelog_id) {
            if ($entry->sync_status !== 'synced') {
                $entry->update(['sync_status' => 'synced', 'synced_at' => $mapping->synced_at, 'last_error' => null]);
            }

            return $entry;
        }

        $response = $mapping->teamboard_timelog_id
            ? $this->teamboard->update($mapping->teamboard_timelog_id, $dto)
            : $this->teamboard->create($dto);
        $mapping->fill([
            'teamboard_timelog_id' => $response['id'] ?? $mapping->teamboard_timelog_id,
            'payload_hash' => $payloadHash, 'raw_request' => $request,
            'raw_response' => $response, 'synced_at' => now(),
        ])->save();
        $entry->update(['sync_status' => 'synced', 'synced_at' => now(), 'last_error' => null]);

        return $entry->fresh();
    }
}
