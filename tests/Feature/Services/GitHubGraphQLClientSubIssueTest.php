<?php

use App\Services\GitHubGraphQLClient;
use Illuminate\Support\Facades\Http;

/** fetchIssueNodeId の正常レスポンス */
function issueNodeIdResponse(string $nodeId): array
{
    return [
        'data' => [
            'repository' => [
                'issue' => ['id' => $nodeId],
            ],
        ],
    ];
}

/** fetchSubIssues のレスポンス */
function subIssuesGraphQLResponse(array $nodes = [], bool $hasNextPage = false, string $endCursor = ''): array
{
    return [
        'data' => [
            'node' => [
                'subIssues' => [
                    'pageInfo' => ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor],
                    'nodes' => $nodes,
                ],
            ],
        ],
    ];
}

test('fetchIssueNodeId は Issue の Node ID を返す', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(issueNodeIdResponse('I_kwDO123')),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $nodeId = $client->fetchIssueNodeId('owner', 'repo', 42, 'token');

    expect($nodeId)->toBe('I_kwDO123');
});

test('fetchIssueNodeId は Issue が存在しない場合 null を返す', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response([
            'data' => ['repository' => ['issue' => null]],
        ]),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $nodeId = $client->fetchIssueNodeId('owner', 'repo', 999, 'token');

    expect($nodeId)->toBeNull();
});

test('fetchSubIssues はサブイシュー一覧を返す', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(subIssuesGraphQLResponse([
            [
                'id' => 'I_sub1',
                'number' => 10,
                'title' => 'タスク1',
                'state' => 'OPEN',
                'closedAt' => null,
                'assignees' => ['nodes' => [['login' => 'alice']]],
            ],
            [
                'id' => 'I_sub2',
                'number' => 11,
                'title' => 'タスク2',
                'state' => 'CLOSED',
                'closedAt' => '2026-03-01T00:00:00Z',
                'assignees' => ['nodes' => []],
            ],
        ])),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $subIssues = $client->fetchSubIssues('I_parent', 'token');

    expect($subIssues)->toHaveCount(2);
    expect($subIssues[0])->toMatchArray([
        'node_id' => 'I_sub1',
        'number' => 10,
        'title' => 'タスク1',
        'state' => 'open',
        'assignee' => 'alice',
    ]);
    expect($subIssues[1])->toMatchArray([
        'node_id' => 'I_sub2',
        'number' => 11,
        'state' => 'closed',
        'assignee' => null,
    ]);
});

test('fetchSubIssues はページネーションで全件取得する', function () {
    Http::fake([
        'api.github.com/graphql' => Http::sequence()
            ->push(subIssuesGraphQLResponse(
                [['id' => 'I_s1', 'number' => 1, 'title' => 'Task 1', 'state' => 'OPEN', 'closedAt' => null, 'assignees' => ['nodes' => []]]],
                true,
                'cursor-abc'
            ))
            ->push(subIssuesGraphQLResponse(
                [['id' => 'I_s2', 'number' => 2, 'title' => 'Task 2', 'state' => 'OPEN', 'closedAt' => null, 'assignees' => ['nodes' => []]]]
            )),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $subIssues = $client->fetchSubIssues('I_parent', 'token');

    expect($subIssues)->toHaveCount(2);
    expect($subIssues[0]['number'])->toBe(1);
    expect($subIssues[1]['number'])->toBe(2);
});

test('fetchSubIssues は node が subIssues を持たない場合に空配列を返す', function () {
    Http::fake([
        // node に subIssues キーが存在しない（Issue 以外の node など）
        'api.github.com/graphql' => Http::response([
            'data' => ['node' => []],
        ]),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $subIssues = $client->fetchSubIssues('I_parent', 'token');

    expect($subIssues)->toBeEmpty();
});

test('fetchSubIssues は GraphQL エラーで RuntimeException を投げる', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response([
            'errors' => [['message' => 'Sub-issues feature not enabled']],
        ]),
    ]);

    $client = app(GitHubGraphQLClient::class);

    expect(fn () => $client->fetchSubIssues('I_parent', 'token'))
        ->toThrow(RuntimeException::class, 'GitHub GraphQL エラー (Sub-issues)');
});
