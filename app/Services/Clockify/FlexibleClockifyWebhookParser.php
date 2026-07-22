<?php

namespace App\Services\Clockify;

use App\Contracts\ClockifyWebhookParser;
use App\Data\ClockifyTimeEntryData;
use App\Exceptions\UnparseableClockifyWebhook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class FlexibleClockifyWebhookParser implements ClockifyWebhookParser
{
    public function parse(array $payload): ClockifyTimeEntryData
    {
        // TODO: Narrow these candidates after real Clockify webhook fixtures are captured.
        $entry = $this->firstArray($payload, ['timeEntry', 'time_entry', 'data.timeEntry', 'data.time_entry', 'data']) ?? $payload;
        $id = $this->first($entry, ['id', 'timeEntryId', 'time_entry_id']);
        $start = $this->first($entry, ['timeInterval.start', 'time_interval.start', 'start', 'startedAt']);
        $end = $this->first($entry, ['timeInterval.end', 'time_interval.end', 'end', 'endedAt']);

        if (! is_string($id) || $id === '' || ! is_string($start) || $start === '') {
            throw new UnparseableClockifyWebhook('Clockify entry id or start time is missing.');
        }

        try {
            $startedAt = CarbonImmutable::parse($start);
            $endedAt = is_string($end) && $end !== '' ? CarbonImmutable::parse($end) : null;
        } catch (\Throwable $e) {
            throw new UnparseableClockifyWebhook('Clockify time fields are invalid.', previous: $e);
        }

        $duration = $this->first($entry, ['duration', 'durationSeconds', 'timeInterval.duration']);
        $durationSeconds = is_numeric($duration) ? (int) $duration : ($endedAt ? $startedAt->diffInSeconds($endedAt) : null);

        return new ClockifyTimeEntryData(
            id: $id,
            startedAt: $startedAt,
            endedAt: $endedAt,
            durationSeconds: $durationSeconds,
            taskId: $this->string($entry, ['taskId', 'task.id', 'task_id']),
            taskName: $this->string($entry, ['taskName', 'task.name', 'task_name']),
            projectId: $this->string($entry, ['projectId', 'project.id', 'project_id']),
            description: $this->string($entry, ['description']),
            raw: $payload,
        );
    }

    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (Arr::has($data, $key)) return Arr::get($data, $key);
        }
        return null;
    }

    private function string(array $data, array $keys): ?string
    {
        $value = $this->first($data, $keys);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function firstArray(array $data, array $keys): ?array
    {
        $value = $this->first($data, $keys);
        return is_array($value) ? $value : null;
    }
}
