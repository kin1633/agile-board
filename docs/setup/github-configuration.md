# GitHub 設定ガイド

このアプリを最大限活用するための GitHub リポジトリ設定手順。

---

## スプリント同期の2つのモード

このアプリは2つのモードでスプリントを同期します。

| モード | 条件 | スプリントの元データ |
|--------|------|---------------------|
| **Iteration モード** | リポジトリ設定で `GitHub Project Number` を設定済み | GitHub Projects の Iteration フィールド |
| **Milestone モード** | `GitHub Project Number` 未設定（デフォルト） | GitHub マイルストーン |

Iteration モードでは REST マイルストーン API を使用しません。スプリントと月次マイルストーンを GitHub Projects の Iteration フィールドで一元管理します。

---

## Iteration モード（推奨）

### 1. GitHub Projects v2 の作成

1. GitHub リポジトリ or Organization → `Projects` → `New project`
2. `Board` または `Table` テンプレートを選択して作成
3. 左サイドバー `+ Add field` → `Iteration` を追加し、**フィールド名を `Sprint`** に設定（週単位の期間を指定）
4. 月次目標も管理する場合は、さらに `+ Add field` → `Iteration` を追加し、**フィールド名を `Monthly`** に設定

> フィールド名は `Sprint` / `Monthly` がデフォルト値です。別名にした場合は一般設定（`/settings/general`）で変更してください。

### 2. アプリにプロジェクト番号を登録

1. GitHub Projects の URL からプロジェクト番号を確認する
   - 例: `https://github.com/orgs/myorg/projects/5` → プロジェクト番号は `5`
2. アプリの「設定 → リポジトリ」画面を開く
3. 対象リポジトリの `Project #` 列に番号を入力して保存

### 3. Iteration（スプリント）の追加

1. GitHub Projects のボード or テーブルを開く
2. Iteration フィールドの設定から `Add iteration` → 期間を設定
3. Issue を Iteration にアサインする

### 4. 同期

1. アプリのナビバー「GitHub同期」ボタンを押す
2. スプリントが Iteration ベースで作成される
3. スプリント詳細画面から `start_date`・`working_days` を調整

> **OAuth スコープについて:** Iteration モードでは GitHub Projects の読み取りに `project` スコープが必要です。初回ログイン以降に本機能を有効にした場合は、一度ログアウト → 再ログインで再認証してください。

---

## Milestone モード（後方互換）

`GitHub Project Number` を設定しない場合、従来どおりマイルストーンをスプリントとして同期します。

### 1. マイルストーンの作成

1. リポジトリ → `Issues` → `Milestones` → `New milestone`
2. 以下の形式で設定する

| 項目 | 設定 | 備考 |
|------|------|------|
| Title | `Sprint 1` または `2026-04 Sprint 1` | 番号 or 年月 + 番号が見やすい |
| Due date | スプリント最終日 | **必須**。未設定だとバーンダウンチャートが表示されない |
| Description | スプリントゴール（任意） | |

**同期後にアプリ側で調整できる項目:**
- `start_date` — デフォルトは due_date の8日前で自動計算。スプリント詳細画面から変更可
- `working_days` — デフォルト5日。稼働日数に合わせて変更可

---

## ラベルの整備

アプリはラベルを同期します。以下を統一して作成しておくと管理しやすくなります。

| ラベル名 | 色 | 用途 |
|---------|-----|------|
| `story` | `#0075ca` | ユーザーストーリー |
| `bug` | `#d73a4a` | バグ |
| `task` | `#e4e669` | 技術タスク |
| `epic` | `#8b5cf6` | エピック（大きな機能） |
| `blocked` | `#b60205` | ブロック中 |

**作成手順:** `Issues` → `Labels` → `New label`

---

## Issue テンプレート

`.github/ISSUE_TEMPLATE/` に3種類のテンプレートが用意されています。

| ファイル | 用途 |
|---------|------|
| `user_story.md` | 機能開発・改善 |
| `bug_report.md` | バグ報告 |
| `task.md` | リファクタリング・調査・技術タスク |

各テンプレートには「ストーリーポイント候補」のコメントを記載しています。
Issueを作成したらアプリ側でストーリーポイントを設定してください（スプリント詳細画面から設定可能）。

---

## ブランチ保護（推奨）

`Settings` → `Branches` → `main` に以下を設定:

- Require pull request reviews before merging
- Require status checks to pass
