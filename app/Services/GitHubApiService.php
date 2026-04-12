<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

/**
 * GitHub REST API への書き戻しサービス。
 *
 * GitHubSyncService が読み取り専用なのに対し、このサービスは
 * アプリでの操作（Issue クローズ / ラベル変更等）を GitHub に反映する。
 * GitHub Token は認証ユーザーの token カラムから取得する。
 */
class GitHubApiService
{
    private const BASE_URL = 'https://api.github.com';

    public function __construct(
        private readonly Http $http,
    ) {}

    /**
     * Issue の状態を変更する（open / closed）。
     *
     * @throws RequestException
     */
    public function updateIssueState(Issue $issue, string $state, string $token): void
    {
        $repo = $issue->repository;
        if (! $repo) {
            return;
        }

        $this->http
            ->withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->patch(
                self::BASE_URL."/repos/{$repo->full_name}/issues/{$issue->github_issue_number}",
                ['state' => $state],
            )
            ->throw();
    }

    /**
     * Issue のラベルを上書きする。
     *
     * @param  string[]  $labelNames
     *
     * @throws RequestException
     */
    public function updateIssueLabels(Issue $issue, array $labelNames, string $token): void
    {
        $repo = $issue->repository;
        if (! $repo) {
            return;
        }

        $this->http
            ->withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->patch(
                self::BASE_URL."/repos/{$repo->full_name}/issues/{$issue->github_issue_number}",
                ['labels' => $labelNames],
            )
            ->throw();
    }

    /**
     * リポジトリの全PRをGitHub APIから取得してDBに同期する。
     *
     * PRとIssueの紐付けはブランチ名（例: feature/issue-123）またはPR本文の "closes #123" パターンで行う。
     *
     * @throws RequestException
     */
    public function syncPullRequests(Repository $repo, string $token): void
    {
        $response = $this->http
            ->withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->get(
                self::BASE_URL."/repos/{$repo->full_name}/pulls",
                ['state' => 'all', 'per_page' => 100],
            )
            ->throw();

        $prs = $response->json();

        foreach ($prs as $prData) {
            $issueId = $this->extractIssueIdFromPr($repo, $prData);

            PullRequest::updateOrCreate(
                [
                    'repository_id' => $repo->id,
                    'github_pr_number' => $prData['number'],
                ],
                [
                    'issue_id' => $issueId,
                    'title' => $prData['title'],
                    'state' => $prData['state'],
                    'author_login' => $prData['user']['login'] ?? null,
                    'review_state' => null, // TODO: review_state は別途 API から取得
                    'merged_at' => $prData['merged_at'] ? now()->parse($prData['merged_at']) : null,
                    'head_branch' => $prData['head']['ref'] ?? null,
                    'base_branch' => $prData['base']['ref'] ?? null,
                    'github_url' => $prData['html_url'],
                    'synced_at' => now(),
                ]
            );

            // open PR の CI ステータスを同期
            if ($prData['state'] === 'open') {
                $pr = PullRequest::where('repository_id', $repo->id)
                    ->where('github_pr_number', $prData['number'])
                    ->first();

                if ($pr) {
                    $this->syncCiStatus($pr, $token);
                }
            }
        }
    }

    /**
     * PR本文またはブランチ名からIssue IDを抽出して、Issueを紐付ける。
     */
    private function extractIssueIdFromPr(Repository $repo, array $prData): ?int
    {
        // パターン1: PR本文から "closes #123" / "fixes #123" を抽出
        $body = $prData['body'] ?? '';
        if (preg_match('/(?:closes|fixes)\s+#(\d+)/i', $body, $matches)) {
            $issueNumber = (int) $matches[1];
            $issue = Issue::where('repository_id', $repo->id)
                ->where('github_issue_number', $issueNumber)
                ->first();

            if ($issue) {
                return $issue->id;
            }
        }

        // パターン2: ブランチ名から "issue-123" を抽出
        $headBranch = $prData['head']['ref'] ?? '';
        if (preg_match('/issue-(\d+)/', $headBranch, $matches)) {
            $issueNumber = (int) $matches[1];
            $issue = Issue::where('repository_id', $repo->id)
                ->where('github_issue_number', $issueNumber)
                ->first();

            if ($issue) {
                return $issue->id;
            }
        }

        return null;
    }

    /**
     * PRのCIステータス（GitHub Checks）を取得してDBに保存する。
     *
     * GET /repos/{owner}/{repo}/commits/{ref}/check-runs
     * 全チェックのうち最悪ステータスをPRのci_statusとして保存する。
     *
     * @throws RequestException
     */
    public function syncCiStatus(PullRequest $pr, string $token): void
    {
        if (! $pr->head_branch) {
            return;
        }

        $repo = $pr->repository;
        if (! $repo) {
            return;
        }

        try {
            $response = $this->http
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
                ->get(
                    self::BASE_URL."/repos/{$repo->full_name}/commits/{$pr->head_branch}/check-runs",
                )
                ->throw();

            $checkRuns = $response->json('check_runs') ?? [];

            // 全チェックのうち最悪ステータスを決定
            $ciStatus = $this->determineOverallCiStatus($checkRuns);

            $pr->update(['ci_status' => $ciStatus]);
        } catch (RequestException $e) {
            // CI ステータス取得に失敗してもPR同期は継続する
            $pr->update(['ci_status' => null]);
        }
    }

    /**
     * GitHub Check Runs の結果から総合的な CI ステータスを決定する。
     *
     * - any `failure` or `timed_out` → `failure`
     * - all `success` or `skipped` → `success`
     * - any `in_progress` or null → `pending`
     * - otherwise → `pending`
     *
     * @param  array<int, array{conclusion: string|null, status: string}>  $checkRuns
     */
    private function determineOverallCiStatus(array $checkRuns): ?string
    {
        if (empty($checkRuns)) {
            return null;
        }

        $conclusions = array_map(fn ($run) => $run['conclusion'] ?? null, $checkRuns);
        $statuses = array_map(fn ($run) => $run['status'] ?? null, $checkRuns);

        // failure または timed_out がある
        if (in_array('failure', $conclusions, true) || in_array('timed_out', $conclusions, true)) {
            return 'failure';
        }

        // in_progress または status が completed でない場合は pending
        if (in_array('in_progress', $statuses, true) || in_array('queued', $statuses, true)) {
            return 'pending';
        }

        // 全て success または skipped
        $validConclusions = array_filter($conclusions, fn ($c) => $c !== null);
        if (! empty($validConclusions) && collect($validConclusions)->every(fn ($c) => in_array($c, ['success', 'skipped', 'neutral'], true))) {
            return 'success';
        }

        return 'pending';
    }
}
