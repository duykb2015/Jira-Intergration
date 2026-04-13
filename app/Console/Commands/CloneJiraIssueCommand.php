<?php

namespace App\Console\Commands;

use App\Services\JiraService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class CloneJiraIssueCommand extends Command
{
    private const JOB_TYPES = [
        'Review Code',
        'Planning Detail',
    ];

    protected $signature = 'jira:clone-issue
                            {issueKey : Jira issue key, e.g. QTNB-123}
                            {--type= : Job type (Review Code|Planning Detail)}';

    protected $description = 'Clone a Jira issue and perform required transitions';

    public function __construct(protected JiraService $jiraService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $issueKey = strtoupper((string) $this->argument('issueKey'));
        $projectKey = (string) config('services.jira.project_key');
        $startDateField = (string) config('services.jira.start_date_field');

        if ($projectKey === '' || $startDateField === '') {
            $this->error('JIRA_PROJECT_KEY or JIRA_START_DATE_FIELD is not configured.');

            return self::FAILURE;
        }

        try {
            $jobType = $this->resolveJobType();
            $validatedStartDateField = $this->validateStartDateField($startDateField);
            $sourceIssue = $this->jiraService->getIssue($issueKey);
            $sourceFields = $sourceIssue['fields'] ?? [];

            $issueTypeId = (string) ($sourceFields['issuetype']['id'] ?? '');

            if ($issueTypeId === '') {
                throw new RuntimeException('Source issue is missing issuetype.');
            }

            $timezone = (string) config('app.timezone', 'UTC');
            $nextMonday = CarbonImmutable::now($timezone)
                ->startOfWeek(CarbonInterface::MONDAY);
            $nextFriday = $nextMonday->addDays(4);

            $monday = $nextMonday->toDateString();
            $friday = $nextFriday->toDateString();

            $summary = sprintf(
                '%s - Duy (%s - %s)',
                $jobType,
                $nextMonday->format('d/m'),
                $nextFriday->format('d/m/Y')
            );

            $newIssueFields = [
                'project' => ['key' => $projectKey],
                'summary' => $summary,
                'issuetype' => ['id' => $issueTypeId],
                'description' => $sourceFields['description'] ?? null,
                'duedate' => $friday,
                $validatedStartDateField => $monday,
            ];

            if (! empty($sourceFields['labels']) && is_array($sourceFields['labels'])) {
                $newIssueFields['labels'] = $sourceFields['labels'];
            }

            $createdIssue = $this->jiraService->createIssue($newIssueFields);
            $newIssueKey = (string) ($createdIssue['key'] ?? '');

            if ($newIssueKey === '') {
                throw new RuntimeException('Jira did not return key for created issue.');
            }

            $this->jiraService->linkIssuesAsClone($newIssueKey, $issueKey);

            $sourceDoneTransitionId = $this->jiraService->findTransitionIdByName($issueKey, 'Done');

            if ($sourceDoneTransitionId === null) {
                throw new RuntimeException("Cannot find 'Done' transition for source issue {$issueKey}.");
            }

            $newInProgressTransitionId = $this->jiraService->findTransitionIdByName($newIssueKey, 'In Progress');

            if ($newInProgressTransitionId === null) {
                throw new RuntimeException("Cannot find 'In Progress' transition for new issue {$newIssueKey}.");
            }

            $this->jiraService->transitionIssue($issueKey, $sourceDoneTransitionId);
            $this->jiraService->transitionIssue($newIssueKey, $newInProgressTransitionId);

            $this->info("Source issue: {$issueKey}");
            $this->info("Cloned issue: {$newIssueKey}");
            $this->info("Job type: {$jobType}");
            $this->info("Start date ({$validatedStartDateField}): {$monday}");
            $this->info("Due date (duedate): {$friday}");
            $this->info("Link applied: {$newIssueKey} clones {$issueKey}");
            $this->info("Transitions applied: {$issueKey} -> Done, {$newIssueKey} -> In Progress");

            return self::SUCCESS;
        } catch (RequestException $exception) {
            $body = $exception->response?->body();
            $this->error('Jira API request failed.');

            if ($body !== null && $body !== '') {
                $this->line($body);
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @throws RequestException
     */
    protected function validateStartDateField(string $startDateField): string
    {
        $field = $this->jiraService->getFieldById($startDateField);

        if ($field === null) {
            throw new RuntimeException(
                "JIRA_START_DATE_FIELD '{$startDateField}' was not found in Jira field list."
            );
        }

        $schemaType = strtolower((string) Arr::get($field, 'schema.type'));
        $fieldName = (string) ($field['name'] ?? $startDateField);

        if ($schemaType !== 'date') {
            $candidates = $this->buildDateFieldCandidatesMessage();

            throw new RuntimeException(
                "JIRA_START_DATE_FIELD '{$startDateField}' ({$fieldName}) has schema.type='{$schemaType}', expected 'date'. {$candidates}"
            );
        }

        return $startDateField;
    }

    /**
     * @throws RequestException
     */
    protected function buildDateFieldCandidatesMessage(): string
    {
        $candidates = Collection::make($this->jiraService->getFields())
            ->filter(function (array $field): bool {
                $isCustom = str_starts_with((string) ($field['id'] ?? ''), 'customfield_');
                $isDate = strtolower((string) Arr::get($field, 'schema.type')) === 'date';

                return $isCustom && $isDate;
            })
            ->map(fn (array $field): string => sprintf('%s (%s)', (string) $field['id'], (string) ($field['name'] ?? 'unknown')))
            ->take(10)
            ->values()
            ->all();

        if ($candidates === []) {
            return 'No custom date fields were found from /rest/api/3/field.';
        }

        return 'Date field candidates: '.implode(', ', $candidates).'.';
    }

    protected function resolveJobType(): string
    {
        $typeOption = trim((string) $this->option('type'));

        if ($typeOption !== '') {
            foreach (self::JOB_TYPES as $jobType) {
                if (strcasecmp($typeOption, $jobType) === 0) {
                    return $jobType;
                }
            }

            throw new RuntimeException(
                "Invalid --type value '{$typeOption}'. Allowed values: ".implode(', ', self::JOB_TYPES).'.'
            );
        }

        if (! $this->input->isInteractive()) {
            return self::JOB_TYPES[0];
        }

        return $this->choice('Select job type', self::JOB_TYPES, 0);
    }
}
