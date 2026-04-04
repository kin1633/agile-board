# データモデル

## ER 図

```
users
  id, name, email, github_id, github_token, avatar_url

repositories
  id, owner, name, full_name, active, github_project_number, synced_at

milestones
  id, year, month, title, goal, status, started_at, due_date
  ユニーク制約: (year, month)

sprints
  id, milestone_id → milestones (nullable, SET NULL on delete)
  github_iteration_id (nullable, unique)
  title, start_date*, working_days*, end_date, iteration_duration_days, state

epics
  id, title, description, status, due_date, priority
  started_at (nullable), started_at は着手日（手動設定 or 同期時自動設定）

issues
  id, repository_id → repositories
  sprint_id → sprints
  epic_id → epics
  parent_issue_id → issues (nullable, 自己参照)
  github_issue_number, title, state, project_status, assignee_login
  story_points*, exclude_velocity*, estimated_hours*, actual_hours*
  closed_at, synced_at

labels
  id, repository_id → repositories
  name, exclude_velocity

issue_labels (pivot)
  issue_id → issues
  label_id → labels

retrospectives
  id, sprint_id → sprints
  type (keep/problem/try), content

members
  id, user_id → users
  role
```

`*` = GitHub 同期で上書きされない手動設定項目

---

## 主要テーブル詳細

### repositories

| カラム | 型 | 説明 |
|---|---|---|
| github_project_number | int\|null | GitHub Projects v2 のプロジェクト番号。設定すると Iteration モードで同期 |

### milestones

マイルストーンはアプリ独自管理に完全移行しました。GitHub との同期は行いません。

| カラム | 型 | 説明 |
|---|---|---|
| year | int | マイルストーンの年（2020～2099） |
| month | int | マイルストーンの月（1～12）。year + month で一意 |
| title | string | マイルストーン名 |
| goal | text\|null | マイルストーンの目標・説明 |
| status | string | `planning` / `in_progress` / `done` |
| started_at | date\|null | 着手日（手動設定） |
| due_date | date\|null | 期限日（手動設定） |

> マイルストーン作成・編集・削除はマイルストーン画面（`/milestones`）から手動で管理します。GitHub Iteration との同期は行いません。

### sprints

| カラム | 型 | 説明 |
|---|---|---|
| milestone_id | FK\|null | 紐付けマイルストーン。手動で設定・解除可能。スプリント削除時は SET NULL |
| github_iteration_id | string\|null | Iteration モード時の GitHub Iteration ID（一意）。GitHub 同期で設定される |
| title | string | スプリント名 |
| start_date | date | **手動設定**。スプリント開始日（バーンダウン計算に使用） |
| end_date | date | スプリント終了日。GitHub 同期で更新（Iteration モード）または手動設定 |
| working_days | int | **手動設定**。稼働日数（ベロシティ計算の分母） |
| iteration_duration_days | int\|null | Iteration の期間（日数）。GitHub の duration（週）から換算（Iteration モード） |
| state | string | `open` / `closed` |

> Iteration モードでは `github_iteration_id` が設定されて GitHub と同期されます。`milestone_id` はマイルストーン画面から手動で紐付けられます。

### issues

| カラム | 型 | 説明 |
|---|---|---|
| github_issue_number | int | GitHub 上の Issue 番号 |
| parent_issue_id | FK\|null | 親 Issue の id（GitHub Sub-issue の場合に設定）。NULL = Story Issue |
| story_points | int\|null | **手動設定**。ストーリーポイント（Story Issue のみ使用） |
| exclude_velocity | boolean | **手動設定**。ベロシティ計算から除外するか（Sub-issue はデフォルト true） |
| estimated_hours | decimal(8,2)\|null | **手動設定**。予定工数（Task Issue の工数管理に使用） |
| actual_hours | decimal(8,2)\|null | **手動設定**。実績工数（Task Issue の工数管理に使用） |
| state | string | `open` / `closed` |
| project_status | string\|null | GitHub Projects の Status フィールド値（例: `In Progress`）。同期時に更新 |
| closed_at | datetime | クローズされた日時（バーンダウン実績線に使用） |

### epics

| カラム | 型 | 説明 |
|---|---|---|
| title | string | エピック名 |
| description | text\|null | エピックの概要 |
| status | string | `planning` / `in_progress` / `done` |
| due_date | date\|null | リリース予定日 |
| priority | string | `high` / `medium` / `low` |
| started_at | date\|null | 着手日。手動設定またはGitHub同期時に自動設定（Issue の `project_status = 'In Progress'` が条件） |

> `estimated_start_date`（着手日目安）はDBには保存されません。`due_date` / `estimated_hours` / `team_daily_hours` から毎回計算されます。

### Issue の種類（3階層）

| 種類 | 条件 | 説明 |
|---|---|---|
| Story Issue | `parent_issue_id IS NULL` | スプリントに紐付く主な Issue。`story_points` でベロシティ計測 |
| Task Issue（Sub-issue） | `parent_issue_id IS NOT NULL` | Story の子 Issue（GitHub Sub-issues）。`estimated_hours` / `actual_hours` で工数管理。ベロシティから除外 |

```
Epic（案件）
  └─ Story Issue（epic_id で紐付け）
       └─ Task Issue（parent_issue_id で紐付け）
```

### labels

| カラム | 型 | 説明 |
|---|---|---|
| exclude_velocity | boolean | このラベルを持つ Issue をベロシティから除外するか |

---

## マイルストーンとスプリントの関係

マイルストーンはアプリ独自管理に完全移行し、GitHub 同期から切り離されました。

### 管理方式

- **マイルストーン**: `/milestones` 画面で手動作成・編集・削除
- **スプリント**: GitHub 同期（Iteration モード）またはアプリ手動作成
- **紐付け**: スプリント → マイルストーン の紐付けは `PATCH /sprints/{sprint}/milestone` で手動設定

### Iteration モード（`github_project_number` 設定済み）

```
repositories ─→ sprints（GitHub Sprint フィールド）─→ issues
```

マイルストーンはこのモードに関わらず、アプリ独自管理です。スプリント作成時は `milestone_id = NULL` となり、マイルストーン画面から手動で紐付けします。

---

## ベロシティ計算ロジック

ベロシティは以下の条件を全て満たす Issue を対象に計算します。

- `state = 'closed'`
- `exclude_velocity = false`（Issue 単位の除外フラグ）
- ラベルに `exclude_velocity = true` のものが含まれていない（ラベル単位の除外フラグ）

**ポイントベロシティ**: 対象 Issue の `story_points` 合計
**Issue ベロシティ**: 対象 Issue の件数
