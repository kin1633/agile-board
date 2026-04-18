<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GitHub Webhook イベント受信コントローラー。
 *
 * GitHub Repository Settings > Webhooks から以下のイベントを送信するよう設定する:
 * - issues: Issue の open/close/edit をリアルタイムで反映
 * - pull_request: PR の open/close/merge/review をリアルタイムで反映
 *
 * Webhook Secret は GITHUB_WEBHOOK_SECRET 環境変数で設定する。
 * 未設定の場合は署名検証をスキップする（開発環境向け）。
 */
class WebhookController extends Controller
{
    /**
     * GitHub Webhook を受信して処理する。
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        match ($event) {
            'issues' => $this->handleIssueEvent($payload),
            'pull_request' => $this->handlePullRequestEvent($payload),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /**
     * GitHub の HMAC-SHA256 署名を検証する。
     *
     * GITHUB_WEBHOOK_SECRET が未設定の場合は検証をスキップする。
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.github.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * issues イベントを処理する。
     *
     * action: opened / closed / edited / reopened
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleIssueEvent(array $payload): void
    {
        $action = $payload['action'] ?? '';
        $ghIssue = $payload['issue'] ?? [];
        $repoFullName = $payload['repository']['full_name'] ?? '';

        $repository = Repository::where('full_name', $repoFullName)->first();
        if (! $repository) {
            return;
        }

        $issue = Issue::where('repository_id', $repository->id)
            ->where('github_issue_number', $ghIssue['number'])
            ->first();

        if (! $issue) {
            return;
        }

        if (in_array($action, ['opened', 'closed', 'reopened'], true)) {
            $issue->update([
                'state' => $ghIssue['state'],
                'closed_at' => $ghIssue['closed_at'] ? now()->parse($ghIssue['closed_at']) : null,
            ]);
        }

        if ($action === 'edited' && isset($ghIssue['title'])) {
            $issue->update(['title' => $ghIssue['title']]);
        }
    }

    /**
     * pull_request イベントを処理する。
     *
     * action: opened / closed / synchronize / review_requested
     *
     * @param  array<string, mixed>  $payload
     */
    private function handlePullRequestEvent(array $payload): void
    {
        $action = $payload['action'] ?? '';
        $ghPr = $payload['pull_request'] ?? [];
        $repoFullName = $payload['repository']['full_name'] ?? '';

        $repository = Repository::where('full_name', $repoFullName)->first();
        if (! $repository) {
            return;
        }

        // PR の状態を決定する（merged は closed と区別する）
        $state = ($ghPr['merged'] ?? false) ? 'merged' : ($ghPr['state'] ?? 'open');

        PullRequest::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'github_pr_number' => $ghPr['number'],
            ],
            [
                'title' => $ghPr['title'],
                'state' => $state,
                'author_login' => $ghPr['user']['login'] ?? null,
                'head_branch' => $ghPr['head']['ref'] ?? null,
                'base_branch' => $ghPr['base']['ref'] ?? null,
                'github_url' => $ghPr['html_url'] ?? null,
                'merged_at' => $ghPr['merged_at'] ? now()->parse($ghPr['merged_at']) : null,
                'synced_at' => now(),
            ]
        );

        // PR タイトルや本文にクローズキーワードが含まれる場合に自動リンクする（省略）
    }
}
