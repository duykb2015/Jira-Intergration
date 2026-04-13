<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JiraService
{
    public function __construct()
    {
        //
    }

    /**
     * @throws RequestException
     */
    public function getIssue(string $issueKey): array
    {
        return $this->http()
            ->get("/rest/api/3/issue/{$issueKey}")
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function createIssue(array $fields): array
    {
        return $this->http()
            ->post('/rest/api/3/issue', ['fields' => $fields])
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function updateIssue(string $issueKey, array $fields): void
    {
        $this->http()
            ->put("/rest/api/3/issue/{$issueKey}", ['fields' => $fields])
            ->throw();
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function getTransitions(string $issueKey): array
    {
        return $this->http()
            ->get("/rest/api/3/issue/{$issueKey}/transitions")
            ->throw()
            ->collect('transitions')
            ->all();
    }

    /**
     * @throws RequestException
     */
    public function transitionIssue(string $issueKey, string $transitionId): void
    {
        $this->http()
            ->post("/rest/api/3/issue/{$issueKey}/transitions", [
                'transition' => [
                    'id' => $transitionId,
                ],
            ])
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function linkIssuesAsClone(string $newIssueKey, string $sourceIssueKey): void
    {
        $this->http()
            ->post('/rest/api/3/issueLink', [
                'type' => [
                    'name' => 'Cloners',
                ],
                'outwardIssue' => [
                    'key' => $newIssueKey,
                ],
                'inwardIssue' => [
                    'key' => $sourceIssueKey,
                ],
            ])
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function findTransitionIdByName(string $issueKey, string $name): ?string
    {
        $transitions = $this->getTransitions($issueKey);

        foreach ($transitions as $transition) {
            if (strcasecmp((string) ($transition['name'] ?? ''), $name) === 0) {
                return (string) $transition['id'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function getFields(): array
    {
        return $this->http()
            ->get('/rest/api/3/field')
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function getFieldById(string $fieldId): ?array
    {
        foreach ($this->getFields() as $field) {
            if (($field['id'] ?? null) === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    protected function http(): PendingRequest
    {
        $baseUrl = (string) config('services.jira.base_url');
        $email = (string) config('services.jira.email');
        $apiToken = (string) config('services.jira.api_token');

        if ($baseUrl === '' || $email === '' || $apiToken === '') {
            throw new RuntimeException('Jira credentials are not configured.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($email, $apiToken);
    }
}
