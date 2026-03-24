<?php

use App\Services\GitHubGraphQLClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

// organization チェック成功レスポンス（resolveOwnerType 用）
function orgCheckResponse(): array
{
    return ['data' => ['organization' => ['login' => 'owner']]];
}

// Iteration フィールドが含まれる GraphQL レスポンスのモック（organization ノード）
function iterationFieldsResponse(): array
{
    return [
        'data' => [
            'organization' => [
                'projectV2' => [
                    'fields' => [
                        'nodes' => [
                            [
                                // IterationField には configuration キーが存在する
                                'id' => 'field-1',
                                'name' => 'Sprint',
                                'configuration' => [
                                    'iterations' => [
                                        [
                                            'id' => 'iter-1',
                                            'title' => 'Sprint 1',
                                            'startDate' => '2026-04-07',
                                            'duration' => 2,
                                        ],
                                    ],
                                    'completedIterations' => [
                                        [
                                            'id' => 'iter-0',
                                            'title' => 'Sprint 0',
                                            'startDate' => '2026-03-24',
                                            'duration' => 2,
                                        ],
                                    ],
                                ],
                            ],
                            // 他のフィールド（Iteration でない）はスキップされる
                            ['id' => 'field-2', 'name' => 'Status'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

// アイテム（Issue）が含まれる GraphQL レスポンスのモック（organization ノード）
function projectItemsResponse(string $iterationId): array
{
    return [
        'data' => [
            'organization' => [
                'projectV2' => [
                    'items' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'nodes' => [
                            [
                                'content' => [
                                    'number' => 42,
                                    'title' => 'Test Issue',
                                    'state' => 'OPEN',
                                    'closedAt' => null,
                                    'assignees' => ['nodes' => [['login' => 'bob']]],
                                    'labels' => ['nodes' => [['name' => 'bug']]],
                                ],
                                'fieldValues' => [
                                    'nodes' => [
                                        ['iterationId' => $iterationId],
                                    ],
                                ],
                            ],
                            // PR（content が空）はスキップされる
                            [
                                'content' => [],
                                'fieldValues' => ['nodes' => []],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('Iteration フィールドと完了済みイテレーションを取得できる', function () {
    Http::fake([
        // 1回目: resolveOwnerType の org チェック、2回目: fetchIterationFields、3回目: fetchProjectItems
        'api.github.com/graphql' => Http::sequence()
            ->push(orgCheckResponse())
            ->push(iterationFieldsResponse())
            ->push(projectItemsResponse('iter-1')),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $result = $client->fetchProjectIterationsWithItems('owner', 1, 'token');

    expect($result['iterations'])->toHaveCount(2);
    expect($result['iterations'][0])->toMatchArray([
        'id' => 'iter-1',
        'title' => 'Sprint 1',
        'startDate' => '2026-04-07',
        'duration' => 2,
    ]);
    expect($result['iterations'][1]['id'])->toBe('iter-0');
});

test('Issue が正しい Iteration にグループ化される', function () {
    Http::fake([
        'api.github.com/graphql' => Http::sequence()
            ->push(orgCheckResponse())
            ->push(iterationFieldsResponse())
            ->push(projectItemsResponse('iter-1')),
    ]);

    $client = app(GitHubGraphQLClient::class);
    $result = $client->fetchProjectIterationsWithItems('owner', 1, 'token');

    $issues = $result['issuesByIteration']['iter-1'] ?? [];
    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'number' => 42,
        'title' => 'Test Issue',
        'state' => 'open',
        'assignee' => 'bob',
        'labels' => ['bug'],
    ]);
});

test('個人アカウントの場合は user ノードにフォールバックして Iteration を取得できる', function () {
    // organization チェックが GraphQL エラーを返した場合 user ノードを使う
    $orgCheckError = ['errors' => [['message' => 'Could not resolve to an Organization']]];
    $iterationFieldsUser = [
        'data' => [
            'user' => [
                'projectV2' => [
                    'fields' => [
                        'nodes' => [
                            [
                                'id' => 'field-u',
                                'name' => 'Sprint',
                                'configuration' => [
                                    'iterations' => [
                                        ['id' => 'iter-u1', 'title' => 'My Sprint', 'startDate' => '2026-04-14', 'duration' => 2],
                                    ],
                                    'completedIterations' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $projectItemsUser = [
        'data' => [
            'user' => [
                'projectV2' => [
                    'items' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'nodes' => [],
                    ],
                ],
            ],
        ],
    ];

    Http::fake([
        'api.github.com/graphql' => Http::sequence()
            ->push($orgCheckError)       // resolveOwnerType: org 失敗 → user にフォールバック
            ->push($iterationFieldsUser) // fetchIterationFields: user ノード
            ->push($projectItemsUser),   // fetchProjectItems: user ノード
    ]);

    $client = app(GitHubGraphQLClient::class);
    $result = $client->fetchProjectIterationsWithItems('kin1633', 2, 'token');

    expect($result['iterations'])->toHaveCount(1);
    expect($result['iterations'][0])->toMatchArray([
        'id' => 'iter-u1',
        'title' => 'My Sprint',
    ]);
});

test('GraphQL エラーレスポンスで RuntimeException が投げられる', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response([
            'errors' => [['message' => 'Field does not exist']],
        ]),
    ]);

    $client = app(GitHubGraphQLClient::class);

    expect(fn () => $client->query('{ viewer { login } }', [], 'token'))
        ->toThrow(RuntimeException::class, 'GitHub GraphQL エラー');
});

test('HTTP エラーで RequestException が投げられる', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response([], 401),
    ]);

    $client = app(GitHubGraphQLClient::class);

    expect(fn () => $client->query('{ viewer { login } }', [], 'token'))
        ->toThrow(RequestException::class);
});
