# データモデル

## ER 図

```
users
  id, name, email, github_id, github_token, avatar_url

repositories
  id, owner, name, full_name, active, synced_at

milestones
  id, repository_id → repositories
  github_milestone_id, title, due_on, state, synced_at

sprints
  id, milestone_id → milestones
  title, start_date*, working_days*, end_date, state

epics
  id, title, description, status

issues
  id, repository_id → repositories
  sprint_id → sprints
  epic_id → epics
  github_issue_number, title, state, assignee_login
  story_points*, exclude_velocity*, closed_at, synced_at

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

### sprints

| カラム | 型 | 説明 |
|---|---|---|
| milestone_id | FK | GitHub マイルストーンとの紐付け |
| title | string | スプリント名（マイルストーンのタイトル） |
| start_date | date | **手動設定**。スプリント開始日（バーンダウン計算に使用） |
| end_date | date | マイルストーンの期限日（同期で更新） |
| working_days | int | **手動設定**。稼働日数（ベロシティ計算の分母） |
| state | string | `open` / `closed` |

### issues

| カラム | 型 | 説明 |
|---|---|---|
| github_issue_number | int | GitHub 上の Issue 番号 |
| story_points | int\|null | **手動設定**。ストーリーポイント |
| exclude_velocity | boolean | **手動設定**。ベロシティ計算から除外するか |
| state | string | `open` / `closed` |
| closed_at | datetime | クローズされた日時（バーンダウン実績線に使用） |

### labels

| カラム | 型 | 説明 |
|---|---|---|
| exclude_velocity | boolean | このラベルを持つ Issue をベロシティから除外するか |

---

## ベロシティ計算ロジック

ベロシティは以下の条件を全て満たす Issue を対象に計算します。

- `state = 'closed'`
- `exclude_velocity = false`（Issue 単位の除外フラグ）
- ラベルに `exclude_velocity = true` のものが含まれていない（ラベル単位の除外フラグ）

**ポイントベロシティ**: 対象 Issue の `story_points` 合計
**Issue ベロシティ**: 対象 Issue の件数
