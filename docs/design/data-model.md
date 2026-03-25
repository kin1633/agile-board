# データモデル

## ER 図

```
users
  id, name, email, github_id, github_token, avatar_url

repositories
  id, owner, name, full_name, active, github_project_number, synced_at

milestones
  id, repository_id → repositories
  github_milestone_id (nullable), github_iteration_id (nullable, unique)
  title, due_on, state, synced_at

sprints
  id, milestone_id → milestones (nullable)
  github_iteration_id (nullable, unique)
  title, start_date*, working_days*, end_date, iteration_duration_days, state

epics
  id, title, description, status

issues
  id, repository_id → repositories
  sprint_id → sprints
  epic_id → epics
  parent_issue_id → issues (nullable, 自己参照)
  github_issue_number, title, state, assignee_login
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

| カラム | 型 | 説明 |
|---|---|---|
| github_milestone_id | int\|null | Milestone モード時の GitHub マイルストーン ID。Iteration モードでは NULL |
| github_iteration_id | string\|null | Iteration モード時の GitHub Iteration ID（Monthly フィールド）。一意 |
| title | string | マイルストーン名 |
| due_on | date\|null | 期限日。Iteration モードでは `startDate + duration週 - 1日` で自動計算 |
| state | string | `open` / `closed` |

> `github_milestone_id` と `github_iteration_id` はどちらか一方のみ持ちます。

### sprints

| カラム | 型 | 説明 |
|---|---|---|
| milestone_id | FK\|null | Milestone モード時の紐付け。Iteration モードでは NULL |
| github_iteration_id | string\|null | Iteration モード時の GitHub Iteration ID（一意） |
| title | string | スプリント名 |
| start_date | date | **手動設定**。スプリント開始日（バーンダウン計算に使用） |
| end_date | date | スプリント終了日（同期で更新） |
| working_days | int | **手動設定**。稼働日数（ベロシティ計算の分母） |
| iteration_duration_days | int\|null | Iteration の期間（日数）。GitHub の duration（週）から換算 |
| state | string | `open` / `closed` |

> `milestone_id` と `github_iteration_id` はどちらか一方のみ持ちます。両方 NULL になることはありません。

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
| closed_at | datetime | クローズされた日時（バーンダウン実績線に使用） |

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

## スプリントの2つのモード

### Milestone モード（`github_project_number` 未設定）

```
repositories ─→ milestones ─→ sprints ─→ issues
```

### Iteration モード（`github_project_number` 設定済み）

```
repositories ─→ sprints（Sprint フィールド, github_iteration_id で識別）─→ issues
              └─→ milestones（Monthly フィールド, github_iteration_id で識別）
```

REST マイルストーン API は使用しません。Sprint / Monthly の2つの Iteration フィールドでそれぞれ管理します。

---

## ベロシティ計算ロジック

ベロシティは以下の条件を全て満たす Issue を対象に計算します。

- `state = 'closed'`
- `exclude_velocity = false`（Issue 単位の除外フラグ）
- ラベルに `exclude_velocity = true` のものが含まれていない（ラベル単位の除外フラグ）

**ポイントベロシティ**: 対象 Issue の `story_points` 合計
**Issue ベロシティ**: 対象 Issue の件数
