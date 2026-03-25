# GitHub 同期フロー

## 概要

「GitHub 同期」ボタンを押すと `POST /sync` が呼ばれ、`GitHubSyncService` が実行されます。
アクティブなリポジトリ全てを対象に、スプリント・Issue・ラベルを同期します。

リポジトリに `github_project_number` が設定されているかどうかで、スプリントの同期元が変わります。

---

## 処理フロー

```
POST /sync (SyncController)
  └─ GitHubSyncService::syncAll(githubToken)
      └─ Repository::where('active', true) の全リポジトリに対して繰り返す
          │
          ├─ [Milestone モードのみ] syncMilestones()
          │   └─ GitHub REST API: GET /repos/{owner}/{repo}/milestones?state=all
          │       ├─ Milestone を upsert（github_milestone_id で識別）
          │       │
          │       └─ syncSprintForMilestone()
          │           ├─ Sprint を upsert（既存レコードの start_date/working_days は保護）
          │           └─ syncIssuesForMilestone()
          │               └─ GitHub REST API: GET /repos/{owner}/{repo}/issues?milestone={number}
          │                   └─ Issue を upsert（story_points/exclude_velocity は保護）
          │
          ├─ [Iteration モードのみ] syncProjectIterations()
          │   └─ GitHubGraphQLClient::fetchProjectIterationsWithItems()
          │       └─ GitHub GraphQL API: POST https://api.github.com/graphql
          │           ├─ projectV2.fields から IterationField をフィールド名別に取得
          │           │   → ['Sprint' => [...], 'Monthly' => [...]] 形式で返す
          │           │   → 各フィールド: iterations（進行中） + completedIterations（完了済み）
          │           └─ projectV2.items をカーソルページネーションで全件取得
          │               → 各 Item の fieldValues から iterationId を抽出し Issue をグループ化
          │
          │   [Sprint フィールド] 各 Iteration を Sprint として upsert
          │       ├─ github_iteration_id で識別
          │       ├─ 新規: start_date = Iteration の startDate、working_days = 5（デフォルト）
          │       └─ 既存: start_date / working_days は保護（上書きしない）
          │
          │   [Sprint フィールド] 各 Iteration に属する Issue を syncIssuesForIteration() で upsert
          │       ├─ story_points / exclude_velocity / estimated_hours / actual_hours は保護
          │       └─ repo_owner / repo_name で正しい repository_id に紐付け（マルチリポジトリ対応）
          │
          │   [Sprint フィールド] 各 Issue に対して syncSubIssues() でサブイシューを同期
          │       └─ GitHubGraphQLClient::fetchIssueNodeId() で Issue の Node ID 取得
          │           └─ GitHubGraphQLClient::fetchSubIssues() でサブイシュー一覧取得
          │               ├─ GitHub Sub-issues Public Preview API（GraphQL-Features ヘッダー必要）
          │               ├─ 各サブイシューを parent_issue_id 付きで upsert
          │               ├─ estimated_hours / actual_hours は保護（ユーザー入力値を守る）
          │               └─ 新規サブイシューは exclude_velocity = true（デフォルト）
          │
          │   [Monthly フィールド] 各 Iteration を Milestone として upsert
          │       ├─ github_iteration_id で識別（github_milestone_id は使用しない）
          │       └─ due_on = startDate + duration週 - 1日 で自動計算
          │
          ├─ syncLabels()
          │   └─ GitHub REST API: GET /repos/{owner}/{repo}/labels
          │       └─ Label を upsert
          │           └─ 各 Issue と Label の紐付けを更新（issue_labels）
          │
          └─ Repository::update(['synced_at' => now()])
      │
      └─ syncEpicStartDates()
          └─ started_at が未設定の Epic を対象に
              └─ 配下の Story Issue（parent_issue_id IS NULL）に
                 project_status = 'In Progress' のものがあれば
                 → Epic.started_at = today() をセット（既設定は上書きしない）
```

### モード判定

| 条件 | 動作 |
|------|------|
| `repositories.github_project_number` が NULL | **Milestone モード**: REST マイルストーン → Sprint（後方互換） |
| `repositories.github_project_number` が設定済み | **Iteration モード**: `Sprint` フィールド → Sprint、`Monthly` フィールド → Milestone |

Iteration モードでは REST Milestones API を**完全にスキップ**し、GitHub Projects の Iteration から Sprint と Milestone を一元管理します。

フィールド名（`Sprint` / `Monthly`）は `settings` テーブルの `sprint_iteration_field` / `monthly_iteration_field` で変更できます（デフォルト値は SettingSeeder で投入）。

---

## 上書きしない項目（保護される値）

同期はあくまでも GitHub 側の最新状態を反映するものですが、以下の項目はユーザーがアプリ側で設定する値のため、同期で上書きしません。

| テーブル | カラム | 理由 |
|---|---|---|
| sprints | start_date | アプリ側で手動設定する開始日 |
| sprints | working_days | 稼働日数は GitHub に存在しない |
| issues | story_points | GitHub Issue にストーリーポイントの概念がない |
| issues | exclude_velocity | ベロシティ除外設定は GitHub に存在しない |
| issues | estimated_hours | ユーザーがアプリ側で入力する予定工数 |
| issues | actual_hours | ユーザーがアプリ側で入力する実績工数 |
| epics | started_at | 手動設定された着手日は同期で上書きしない（未設定の場合のみ自動設定） |

実装上は `updateOrCreate` 時に、既存レコードがある場合はこれらのカラムを `update` の対象から除外しています。

---

## マルチリポジトリ対応

Iteration モードでは、1つの GitHub Project に複数のリポジトリの Issue が含まれる場合があります。GraphQL レスポンスの各 Issue には `repository { owner { login } name }` が含まれており、これを使って正しい `repository_id` を解決します。

```
resolveRepository(fallback, repo_owner, repo_name):
  1. repo_owner / repo_name が null → fallback リポジトリを使用
  2. DB に該当リポジトリが存在しない → fallback リポジトリを使用
  3. 一致するリポジトリが存在する → そのリポジトリを使用
```

> fallback は `github_project_number` が設定されたアクティブリポジトリです。

---

## ページネーション対応

**REST API（Milestone・Issue・Label）:** `fetchAllPages()` メソッドで `Link` ヘッダーを解析し、全ページを自動取得します（100件/ページ）。

**GraphQL API（Project Items）:** カーソルベースのページネーション（`after: $cursor`）で 100件ずつ全件取得します。

---

## エラーハンドリング

同期中にエラーが発生した場合、Laravel の例外ハンドラーがログに記録します。フロントエンドにはエラーメッセージがフラッシュされます。

GraphQL API 特有のエラー:
- HTTP 非200 → `RequestException` に変換
- レスポンス内 `errors` 配列 → `RuntimeException` に変換（メッセージに GitHub のエラー内容を含む）

---

## 同期タイミング

現在は手動同期のみです。定期自動同期が必要な場合は、`app/Console/Kernel.php` にスケジュールを追加することで対応できます。

```php
// 例: 毎時同期
$schedule->call(fn () => app(GitHubSyncService::class)->syncAll(...))->hourly();
```
