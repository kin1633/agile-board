<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

/**
 * GitHub GraphQL API クライアント。
 *
 * REST API では取得できない ProjectV2 の Iteration フィールドと
 * Sub-issues（サブイシュー）の取得に使用する。
 * GitHub GraphQL エンドポイント: POST https://api.github.com/graphql
 */
class GitHubGraphQLClient
{
    private const GRAPHQL_ENDPOINT = 'https://api.github.com/graphql';

    /**
     * owner が Organization か個人アカウントかのキャッシュ。
     * 同一リクエスト内で複数回呼ばれても API コールを節約する。
     *
     * @var array<string, 'organization'|'user'>
     */
    private array $ownerTypeCache = [];

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
     *   iterationsByField: array<string, array<int, array{id: string, title: string, startDate: string, duration: int}>>,
     *   issuesByIteration: array<string, array<int, array{number: int, title: string, state: string, closed_at: string|null, assignee: string|null, labels: array<int, string>}>>
     * }
     */
    public function fetchProjectIterationsWithItems(
        string $owner,
        int $projectNumber,
        string $token
    ): array {
        $iterationsByField = $this->fetchIterationFields($owner, $projectNumber, $token);

        // 全フィールドのイテレーションをフラット化して items 取得に渡す
        $allIterations = array_merge(...array_values($iterationsByField));

        $issuesByIteration = $this->fetchProjectItems($owner, $projectNumber, $token, $allIterations);

        return [
            'iterationsByField' => $iterationsByField,
            'issuesByIteration' => $issuesByIteration,
        ];
    }

    /**
     * ProjectV2 の IterationField からフィールド名別の Iteration 一覧を取得する。
     *
     * organization → user の順でフォールバックして owner 種別を解決する。
     *
     * @return array<string, array<int, array{id: string, title: string, startDate: string, duration: int}>>
     */
    private function fetchIterationFields(
        string $owner,
        int $projectNumber,
        string $token
    ): array {
        $ownerType = $this->resolveOwnerType($owner, $token);

        // $ownerType を PHP 変数として展開するため heredoc（クォートなし）を使用。
        // GraphQL 変数の $ は \$ でエスケープして PHP 展開を防ぐ。
        $query = <<<GRAPHQL
        query(\$owner: String!, \$number: Int!) {
            {$ownerType}(login: \$owner) {
                projectV2(number: \$number) {
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
            'number' => $projectNumber,
        ], $token);

        $fields = $data[$ownerType]['projectV2']['fields']['nodes'] ?? [];

        // フィールド名をキーにしてイテレーション一覧をグループ化する
        // 例: ['Sprint' => [...], 'Monthly' => [...]]
        $iterationsByField = [];

        foreach ($fields as $field) {
            // IterationField のみ configuration キーを持つ
            if (! isset($field['configuration'])) {
                continue;
            }

            $fieldName = $field['name'];
            $active = $field['configuration']['iterations'] ?? [];
            $completed = $field['configuration']['completedIterations'] ?? [];
            $iterationsByField[$fieldName] = [];

            foreach (array_merge($active, $completed) as $iteration) {
                $iterationsByField[$fieldName][] = [
                    'id' => $iteration['id'],
                    'title' => $iteration['title'],
                    'startDate' => $iteration['startDate'],
                    'duration' => $iteration['duration'],
                ];
            }
        }

        return $iterationsByField;
    }

    /**
     * ProjectV2 のアイテム（Issue）を全ページ取得し、Iteration ID でグループ化して返す。
     *
     * カーソルベースのページネーション（100件ずつ）で全件取得する。
     * 複数リポジトリが同一プロジェクトに紐付く場合を考慮し、
     * Issue の所属リポジトリ情報（repository.owner.login / name）も取得する。
     * GitHub Projects の Status フィールド値（SingleSelectField）も取得する。
     *
     * @param  array<int, array{id: string}>  $iterations  既知の Iteration 一覧（ID 集合の構築に使用）
     * @return array<string, array<int, array{number: int, title: string, state: string, project_status: string|null, closed_at: string|null, assignee: string|null, labels: array<int, string>, repo_owner: string, repo_name: string}>>
     */
    private function fetchProjectItems(
        string $owner,
        int $projectNumber,
        string $token,
        array $iterations
    ): array {
        // resolveOwnerType はキャッシュ済みのため追加 API コールは発生しない
        $ownerType = $this->resolveOwnerType($owner, $token);

        $query = <<<GRAPHQL
        query(\$owner: String!, \$number: Int!, \$cursor: String) {
            {$ownerType}(login: \$owner) {
                projectV2(number: \$number) {
                    items(first: 100, after: \$cursor) {
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
                                    repository {
                                        owner { login }
                                        name
                                    }
                                }
                            }
                            fieldValues(first: 20) {
                                nodes {
                                    ... on ProjectV2ItemFieldIterationValue {
                                        iterationId
                                    }
                                    # Status フィールド（SingleSelectField）の値を取得する
                                    ... on ProjectV2ItemFieldSingleSelectValue {
                                        name
                                        field {
                                            ... on ProjectV2SingleSelectField {
                                                name
                                            }
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
                'number' => $projectNumber,
                'cursor' => $cursor,
            ], $token);

            $items = $data[$ownerType]['projectV2']['items'];
            $pageInfo = $items['pageInfo'];

            foreach ($items['nodes'] as $item) {
                // Issue 以外（PR、Draft など）はスキップ
                if (empty($item['content']['number'])) {
                    continue;
                }

                $fieldValueNodes = $item['fieldValues']['nodes'];
                $iterationId = $this->extractIterationId($fieldValueNodes, $knownIterationIds);

                if ($iterationId === null) {
                    continue;
                }

                $content = $item['content'];
                $issuesByIteration[$iterationId][] = [
                    'number' => $content['number'],
                    'title' => $content['title'],
                    // GitHub GraphQL は "OPEN" / "CLOSED" の大文字
                    'state' => strtolower($content['state']),
                    // GitHub Projects の Status フィールド値（例: "In Progress"）
                    'project_status' => $this->extractSingleSelectValue($fieldValueNodes, 'Status'),
                    'closed_at' => $content['closedAt'] ?? null,
                    'assignee' => $content['assignees']['nodes'][0]['login'] ?? null,
                    'labels' => collect($content['labels']['nodes'])->pluck('name')->all(),
                    // 複数リポジトリが同一プロジェクトに紐付く場合の識別用
                    'repo_owner' => $content['repository']['owner']['login'] ?? null,
                    'repo_name' => $content['repository']['name'] ?? null,
                ];
            }

            $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;
        } while ($cursor !== null);

        return $issuesByIteration;
    }

    /**
     * 指定 Issue のサブイシュー（Sub-issues）を全件取得する。
     *
     * Sub-issues API は GitHub Public Preview のため、
     * GraphQL-Features: sub_issues ヘッダーが必要。
     * サブイシューは同一リポジトリのみ（GitHub 仕様）。
     *
     * @return array<int, array{number: int, title: string, state: string, closed_at: string|null, assignee: string|null, node_id: string}>
     */
    public function fetchSubIssues(string $issueNodeId, string $token): array
    {
        $query = <<<'GRAPHQL'
        query($nodeId: ID!, $cursor: String) {
            node(id: $nodeId) {
                ... on Issue {
                    subIssues(first: 100, after: $cursor) {
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                        nodes {
                            id
                            number
                            title
                            state
                            closedAt
                            assignees(first: 1) {
                                nodes { login }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $subIssues = [];
        $cursor = null;

        do {
            // Sub-issues API は Public Preview のため専用ヘッダーが必要
            $response = $this->http
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'GraphQL-Features' => 'sub_issues',
                ])
                ->post(self::GRAPHQL_ENDPOINT, [
                    'query' => $query,
                    'variables' => ['nodeId' => $issueNodeId, 'cursor' => $cursor],
                ]);

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            $body = $response->json();

            if (! empty($body['errors'])) {
                $message = collect($body['errors'])->pluck('message')->join(', ');
                throw new \RuntimeException("GitHub GraphQL エラー (Sub-issues): {$message}");
            }

            $data = $body['data']['node']['subIssues'] ?? null;

            if ($data === null) {
                break;
            }

            foreach ($data['nodes'] as $node) {
                $subIssues[] = [
                    'node_id' => $node['id'],
                    'number' => $node['number'],
                    'title' => $node['title'],
                    'state' => strtolower($node['state']),
                    'closed_at' => $node['closedAt'] ?? null,
                    'assignee' => $node['assignees']['nodes'][0]['login'] ?? null,
                ];
            }

            $pageInfo = $data['pageInfo'];
            $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;
        } while ($cursor !== null);

        return $subIssues;
    }

    /**
     * 指定 Issue の GraphQL Node ID を取得する。
     *
     * fetchSubIssues() の引数として使用するためのヘルパー。
     */
    public function fetchIssueNodeId(string $owner, string $repo, int $issueNumber, string $token): ?string
    {
        $query = <<<'GRAPHQL'
        query($owner: String!, $repo: String!, $number: Int!) {
            repository(owner: $owner, name: $repo) {
                issue(number: $number) {
                    id
                }
            }
        }
        GRAPHQL;

        $data = $this->query($query, [
            'owner' => $owner,
            'repo' => $repo,
            'number' => $issueNumber,
        ], $token);

        return $data['repository']['issue']['id'] ?? null;
    }

    /**
     * owner が Organization か個人アカウントかを判定して返す。
     *
     * organization クエリが成功すれば 'organization'、
     * GraphQL エラーが返った場合（個人アカウント）は 'user' にフォールバックする。
     * 結果はリクエスト内でキャッシュし、同一 owner への重複 API コールを防ぐ。
     *
     * @return 'organization'|'user'
     */
    private function resolveOwnerType(string $owner, string $token): string
    {
        if (array_key_exists($owner, $this->ownerTypeCache)) {
            return $this->ownerTypeCache[$owner];
        }

        try {
            $this->query(
                'query($owner: String!) { organization(login: $owner) { login } }',
                ['owner' => $owner],
                $token,
            );
            $type = 'organization';
        } catch (\RuntimeException) {
            // organization が存在しない（個人アカウント等）場合は user として扱う
            $type = 'user';
        }

        return $this->ownerTypeCache[$owner] = $type;
    }

    /**
     * fieldValues から指定フィールド名の SingleSelectField の値を返す。
     *
     * GitHub Projects の Status などの SingleSelectField に使用する。
     *
     * @param  array<int, mixed>  $fieldValueNodes
     */
    private function extractSingleSelectValue(array $fieldValueNodes, string $fieldName): ?string
    {
        foreach ($fieldValueNodes as $node) {
            // SingleSelectValue は name と field.name を持つ
            if (isset($node['name']) && isset($node['field']['name']) && $node['field']['name'] === $fieldName) {
                return $node['name'];
            }
        }

        return null;
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
