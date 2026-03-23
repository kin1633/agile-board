# GitHub 同期フロー

## 概要

「GitHub 同期」ボタンを押すと `POST /sync` が呼ばれ、`GitHubSyncService` が実行されます。
アクティブなリポジトリ全てを対象に、マイルストーン・Issue・ラベルを同期します。

---

## 処理フロー

```
POST /sync (SyncController)
  └─ GitHubSyncService::syncAll(githubToken)
      └─ Repository::where('active', true) の全リポジトリに対して繰り返す
          ├─ syncMilestones()
          │   └─ GitHub API: GET /repos/{owner}/{repo}/milestones?state=all
          │       ├─ Milestone を upsert（github_milestone_id で識別）
          │       ├─ Sprint を upsert（既存レコードの start_date/working_days は保護）
          │       └─ syncIssuesForMilestone()
          │           └─ GitHub API: GET /repos/{owner}/{repo}/issues?milestone={number}
          │               └─ Issue を upsert（story_points/exclude_velocity は保護）
          ├─ syncLabels()
          │   └─ GitHub API: GET /repos/{owner}/{repo}/labels
          │       └─ Label を upsert
          │           └─ 各 Issue と Label の紐付けを更新（issue_labels）
          └─ Repository::update(['synced_at' => now()])
```

---

## 上書きしない項目（保護される値）

同期はあくまでも GitHub 側の最新状態を反映するものですが、以下の項目はユーザーがアプリ側で設定する値のため、同期で上書きしません。

| テーブル | カラム | 理由 |
|---|---|---|
| sprints | start_date | スプリント開始日は GitHub マイルストーンに存在しない |
| sprints | working_days | 稼働日数は GitHub に存在しない |
| issues | story_points | GitHub Issue にストーリーポイントの概念がない |
| issues | exclude_velocity | ベロシティ除外設定は GitHub に存在しない |

実装上は `updateOrCreate` 時に、既存レコードがある場合はこれらのカラムを `update` の対象から除外しています。

---

## ページネーション対応

GitHub API は 1 リクエストあたり最大 100 件しか返しません。`fetchAllPages()` メソッドで `Link` ヘッダーを解析し、全ページを自動取得します。

---

## エラーハンドリング

同期中にエラーが発生した場合、Laravel の例外ハンドラーがログに記録します。フロントエンドにはエラーメッセージがフラッシュされます。

---

## 同期タイミング

現在は手動同期のみです。定期自動同期が必要な場合は、`app/Console/Kernel.php` にスケジュールを追加することで対応できます。

```php
// 例: 毎時同期
$schedule->call(fn () => app(GitHubSyncService::class)->syncAll(...))->hourly();
```
