<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

/**
 * GitHub GraphQL API クライアント。
 *
 * REST API では取得できない ProjectV2 の Iteration フィールドを扱うために使用する。
 * GitHub GraphQL エンドポイント: POST https://api.github.com/graphql
 */
class GitHubGraphQLClient
{
    private const GRAPHQL_ENDPOINT = 'https://api.github.com/graphql';

    public function __construct(
        private readonly Http $http,
    ) {}

    /**
     * GraphQL クエリを実行して結果を返す。
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RequestException GraphQL errors または HTTP エラー時
     */
    public function query(string $query, array $variables, string $token): array
    {
        $response = $this->http
            ->withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post(self::GRAPHQL_ENDPOINT, [
                'query' => $query,
                'variables' => $variables,
            ]);

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        $body = $response->json();

        // GraphQL は HTTP 200 でもエラーを返す場合があるため明示的にチェック
        if (! empty($body['errors'])) {
            $message = collect($body['errors'])->pluck('message')->join(', ');
            throw new \RuntimeException("GitHub GraphQL エラー: {$message}");
        }

        return $body['data'] ?? [];
    }

    /**
     * ProjectV2 の Iteration フィールドと各 Iteration に属する Issue を取得する。
     *
     * @return array{
     *   iterations: array<int, array{id: string, title: string, startDate: string, duration: int}>,
     *   issuesByIteration: array<string, array<int, array{number: int, title: string, state: string, closed_at: string|null, assignee: string|null, labels: array<int, string>}>>
     * }
     */
    public function fetchProjectIterationsWithItems(
        string $owner,
        string $repo,
        int $projectNumber,
        string $token
    ): array {
        $iterations = $this->fetchIterationFields($owner, $repo, $projectNumber, $token);
        $issuesByIteration = $this->fetchProjectItems($owner, $repo, $projectNumber, $token, $iterations);

        return [
            'iterations' => $iterations,
            'issuesByIteration' => $issuesByIteration,
        ];
    }

    /**
     * ProjectV2 の IterationField から Iteration の一覧を取得する。
     *
     * @return array<int, array{id: string, title: string, startDate: string, duration: int}>
     */
    private function fetchIterationFields(
        string $owner,
        string $repo,
        int $projectNumber,
        string $token
    ): array {
        $query = <<<'GRAPHQL'
        query($owner: String!, $repo: String!, $number: Int!) {
            repository(owner: $owner, name: $repo) {
                projectV2(number: $number) {
                    fields(first: 20) {
                        nodes {
                            ... on ProjectV2IterationField {
                                id
                                name
                                configuration {
                                    iterations {
                                        id
                                        title
                                        startDate
                                        duration
                                    }
                                    completedIterations {
                                        id
                                        title
                                        startDate
                                        duration
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $data = $this->query($query, [
            'owner' => $owner,
            'repo' => $repo,
            'number' => $projectNumber,
        ], $token);

        $fields = $data['repository']['projectV2']['fields']['nodes'] ?? [];
        $iterations = [];

        foreach ($fields as $field) {
            // IterationField のみ configuration キーを持つ
            if (! isset($field['configuration'])) {
                continue;
            }

            $active = $field['configuration']['iterations'] ?? [];
            $completed = $field['configuration']['completedIterations'] ?? [];

            foreach (array_merge($active, $completed) as $iteration) {
                $iterations[] = [
                    'id' => $iteration['id'],
                    'title' => $iteration['title'],
                    'startDate' => $iteration['startDate'],
                    'duration' => $iteration['duration'],
                ];
            }
        }

        return $iterations;
    }

    /**
     * ProjectV2 のアイテム（Issue）を全ページ取得し、Iteration ID でグループ化して返す。
     *
     * カーソルベースのページネーション（100件ずつ）で全件取得する。
     *
     * @param  array<int, array{id: string}>  $iterations  既知の Iteration 一覧（ID 集合の構築に使用）
     * @return array<string, array<int, array{number: int, title: string, state: string, closed_at: string|null, assignee: string|null, labels: array<int, string>}>>
     */
    private function fetchProjectItems(
        string $owner,
        string $repo,
        int $projectNumber,
        string $token,
        array $iterations
    ): array {
        $query = <<<'GRAPHQL'
        query($owner: String!, $repo: String!, $number: Int!, $cursor: String) {
            repository(owner: $owner, name: $repo) {
                projectV2(number: $number) {
                    items(first: 100, after: $cursor) {
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                        nodes {
                            content {
                                ... on Issue {
                                    number
                                    title
                                    state
                                    closedAt
                                    assignees(first: 1) {
                                        nodes { login }
                                    }
                                    labels(first: 10) {
                                        nodes { name }
                                    }
                                }
                            }
                            fieldValues(first: 20) {
                                nodes {
                                    ... on ProjectV2ItemFieldIterationValue {
                                        iterationId
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        // 既知 Iteration ID の集合（素早い存在確認のため）
        $knownIterationIds = collect($iterations)->pluck('id')->flip()->all();

        $issuesByIteration = [];
        $cursor = null;

        do {
            $data = $this->query($query, [
                'owner' => $owner,
                'repo' => $repo,
                'number' => $projectNumber,
                'cursor' => $cursor,
            ], $token);

            $items = $data['repository']['projectV2']['items'];
            $pageInfo = $items['pageInfo'];

            foreach ($items['nodes'] as $item) {
                // Issue 以外（PR、Draft など）はスキップ
                if (empty($item['content']['number'])) {
                    continue;
                }

                $iterationId = $this->extractIterationId($item['fieldValues']['nodes'], $knownIterationIds);

                if ($iterationId === null) {
                    continue;
                }

                $content = $item['content'];
                $issuesByIteration[$iterationId][] = [
                    'number' => $content['number'],
                    'title' => $content['title'],
                    // GitHub GraphQL は "OPEN" / "CLOSED" の大文字
                    'state' => strtolower($content['state']),
                    'closed_at' => $content['closedAt'] ?? null,
                    'assignee' => $content['assignees']['nodes'][0]['login'] ?? null,
                    'labels' => collect($content['labels']['nodes'])->pluck('name')->all(),
                ];
            }

            $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;
        } while ($cursor !== null);

        return $issuesByIteration;
    }

    /**
     * fieldValues から既知の Iteration ID を探して返す。
     *
     * @param  array<int, mixed>  $fieldValueNodes
     * @param  array<string, int>  $knownIterationIds  id => ダミー値 のマップ（isset で高速検索）
     */
    private function extractIterationId(array $fieldValueNodes, array $knownIterationIds): ?string
    {
        foreach ($fieldValueNodes as $node) {
            if (isset($node['iterationId']) && isset($knownIterationIds[$node['iterationId']])) {
                return $node['iterationId'];
            }
        }

        return null;
    }
}
