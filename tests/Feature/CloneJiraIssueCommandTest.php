<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloneJiraIssueCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Ho_Chi_Minh',
            'services.jira.base_url' => 'https://beadtech.atlassian.net',
            'services.jira.email' => 'duybl@adsplus.vn',
            'services.jira.api_token' => 'token',
            'services.jira.project_key' => 'QTNB',
            'services.jira.start_date_field' => 'customfield_10010',
        ]);
    }

    public function test_it_clones_issue_and_transitions_both_issues(): void
    {
        CarbonImmutable::setTestNow('2026-04-13 09:00:00');

        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'GET' && str_ends_with($url, '/rest/api/3/field')) {
                return Http::response([
                    [
                        'id' => 'customfield_10010',
                        'name' => 'Start date',
                        'schema' => ['type' => 'date'],
                    ],
                ], 200);
            }

            if ($method === 'GET' && str_ends_with($url, '/rest/api/3/issue/QTNB-123')) {
                return Http::response([
                    'key' => 'QTNB-123',
                    'fields' => [
                        'summary' => 'Original summary',
                        'description' => [
                            'type' => 'doc',
                            'version' => 1,
                            'content' => [],
                        ],
                        'issuetype' => ['id' => '10001'],
                        'labels' => ['backend', 'urgent'],
                    ],
                ], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/rest/api/3/issue')) {
                return Http::response([
                    'id' => '99999',
                    'key' => 'QTNB-456',
                ], 201);
            }

            if ($method === 'POST' && str_ends_with($url, '/rest/api/3/issueLink')) {
                return Http::response([], 201);
            }

            if ($method === 'GET' && str_ends_with($url, '/rest/api/3/issue/QTNB-123/transitions')) {
                return Http::response([
                    'transitions' => [
                        ['id' => '31', 'name' => 'Done'],
                    ],
                ], 200);
            }

            if ($method === 'GET' && str_ends_with($url, '/rest/api/3/issue/QTNB-456/transitions')) {
                return Http::response([
                    'transitions' => [
                        ['id' => '21', 'name' => 'In Progress'],
                    ],
                ], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/rest/api/3/issue/QTNB-123/transitions')) {
                return Http::response([], 204);
            }

            if ($method === 'POST' && str_ends_with($url, '/rest/api/3/issue/QTNB-456/transitions')) {
                return Http::response([], 204);
            }

            return Http::response(['error' => 'Unexpected request'], 500);
        });

        $this->artisan('jira:clone-issue QTNB-123 --type="Review Code"')
            ->expectsOutput('Source issue: QTNB-123')
            ->expectsOutput('Cloned issue: QTNB-456')
            ->expectsOutput('Job type: Review Code')
            ->expectsOutput('Start date (customfield_10010): 2026-04-13')
            ->expectsOutput('Due date (duedate): 2026-04-17')
            ->expectsOutput('Link applied: QTNB-456 clones QTNB-123')
            ->expectsOutput('Transitions applied: QTNB-123 -> Done, QTNB-456 -> In Progress')
            ->assertSuccessful();

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/rest/api/3/issue')) {
                return false;
            }

            $data = $request->data();
            $fields = $data['fields'] ?? [];

            return ($fields['project']['key'] ?? null) === 'QTNB'
                && ($fields['summary'] ?? null) === 'Review Code - Duy (13/04 - 17/04/2026)'
                && ($fields['issuetype']['id'] ?? null) === '10001'
                && ($fields['customfield_10010'] ?? null) === '2026-04-13'
                && ($fields['duedate'] ?? null) === '2026-04-17'
                && ($fields['labels'] ?? null) === ['backend', 'urgent'];
        });

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/rest/api/3/issueLink')
                && ($request->data()['type']['name'] ?? null) === 'Cloners'
                && ($request->data()['outwardIssue']['key'] ?? null) === 'QTNB-456'
                && ($request->data()['inwardIssue']['key'] ?? null) === 'QTNB-123';
        });

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/rest/api/3/issue/QTNB-123/transitions')
                && ($request->data()['transition']['id'] ?? null) === '31';
        });

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/rest/api/3/issue/QTNB-456/transitions')
                && ($request->data()['transition']['id'] ?? null) === '21';
        });

        CarbonImmutable::setTestNow();
    }

    public function test_it_fails_early_when_start_date_field_is_not_date_type(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/rest/api/3/field')) {
                return Http::response([
                    [
                        'id' => 'customfield_10010',
                        'name' => 'Request Type',
                        'schema' => ['type' => 'sd-request-type'],
                    ],
                    [
                        'id' => 'customfield_10020',
                        'name' => 'Start date',
                        'schema' => ['type' => 'date'],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'Unexpected request'], 500);
        });

        $this->artisan('jira:clone-issue QTNB-123 --type="Review Code"')
            ->expectsOutput("JIRA_START_DATE_FIELD 'customfield_10010' (Request Type) has schema.type='sd-request-type', expected 'date'. Date field candidates: customfield_10020 (Start date).")
            ->assertFailed();
    }
}
