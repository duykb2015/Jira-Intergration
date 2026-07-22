<?php

namespace App\Services;

use App\Data\ClockifyTimeEntryData;
use App\Data\ResolvedJiraIssue;
use App\Models\ClockifyConnection;
use App\Models\ClockifyTask;

class JiraIssueResolver
{
    public function resolve(ClockifyConnection $connection, ClockifyTimeEntryData $entry): ResolvedJiraIssue
    {
        if ($entry->taskId) {
            $mapping = $connection->relationLoaded('tasks')
                ? $connection->tasks->first(fn (ClockifyTask $task): bool => $task->clockify_task_id === $entry->taskId && filled($task->jira_issue_key))
                : ClockifyTask::query()
                    ->where('clockify_connection_id', $connection->id)
                    ->where('clockify_task_id', $entry->taskId)
                    ->whereNotNull('jira_issue_key')
                    ->first();

            if ($mapping) {
                return new ResolvedJiraIssue($mapping->jira_issue_id, $mapping->jira_issue_key);
            }
        }

        foreach ([$entry->taskName, $entry->description] as $candidate) {
            if ($candidate && preg_match('/\b([A-Z][A-Z0-9]+-\d+)\b/', $candidate, $matches)) {
                return new ResolvedJiraIssue(null, $matches[1]);
            }
        }

        return new ResolvedJiraIssue(null, null, 'No Jira issue mapping or issue key was found.');
    }
}
