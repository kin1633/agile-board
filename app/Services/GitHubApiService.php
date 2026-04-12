<?php

namespace App\Services;

use App\Models\Issue;
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
}
