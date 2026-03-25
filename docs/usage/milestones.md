# マイルストーン

**URL**: `/milestones`

GitHub マイルストーンの一覧を表示します。マイルストーンは中長期ゴールの管理に使用し、スプリントとは独立して表示されます。

---

## 表示内容

各マイルストーンには以下の情報が表示されます。

| 項目 | 説明 |
|------|------|
| タイトル | マイルストーン名 |
| 期限 | `due_on`（未設定の場合は「期限なし」） |
| ステータス | `open`（緑バッジ）/ `closed`（グレーバッジ） |

マイルストーンはリポジトリごとにグループ表示されます。各グループのヘッダーには以下が表示されます。

| 要素 | 説明 |
|------|------|
| リポジトリ名 | `owner/repo` 形式 |
| `Project #N` バッジ | `github_project_number` が設定済みの場合に表示 |
| GitHub リンク | Iteration モード時は GitHub Projects へのリンク、Milestone モード時は GitHub Milestones へのリンク |

---

## 同期元の違い

| モード | マイルストーンの同期元 |
|--------|----------------------|
| **Milestone モード**（`github_project_number` 未設定） | GitHub マイルストーン REST API |
| **Iteration モード**（`github_project_number` 設定済み） | GitHub Projects の `Monthly` Iteration フィールド |

Iteration モードでは REST マイルストーン API は使用しません。GitHub Projects に `Monthly` という名前の Iteration フィールドを作成し、月次目標を Iteration として登録すると、このページに表示されます。

- `due_on`（期限）は Iteration の `startDate + duration週 - 1日` で自動計算されます
- フィールド名 `Monthly` は一般設定（`/settings/general`）で変更できます

---

## データの更新

マイルストーンはナビバーの「GitHub 同期」ボタンを押すことで最新状態に更新されます。
