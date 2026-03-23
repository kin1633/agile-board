# アジャイル管理アプリ 実装指示

## 環境

- Laravel（最新安定版）+ React + Inertia.js + Tailwind CSS + Vite
- Laravel Sail（Docker）で起動済み
- DB: MySQL、キャッシュ: Valkey（Redis互換）
- GitHub OAuth の .env 設定済み（GITHUB_CLIENT_ID / GITHUB_CLIENT_SECRET / GITHUB_REDIRECT_URI）
- `laravel new` で built-in auth + React スターターキットが適用済み

---

## アプリ概要

アジャイル開発（スクラム）チーム向けのプロジェクト管理アプリ。
GitHub と連携し、スプリント管理・エピック見積・レトロスペクティブ（KPT）を一元管理する。

---

## 画面構成（5画面）

| # | 画面 | 主な役割 |
|---|---|---|
| 1 | ダッシュボード `/dashboard` | 現スプリント俯瞰・KPTサマリー・バーンダウンチャート・進行中Issue |
| 2 | スプリント詳細 `/sprints/{id}` | Issue一覧・ベロシティ・バーンダウン・人別推定工数 |
| 3 | エピック `/epics` | 案件単位の見積・ストーリー分解・推定工数・推定期間 |
| 4 | レトロスペクティブ `/retrospectives` | KPT入力（Keep/Problem/Try）・過去履歴 |
| 5 | 設定 `/settings` | GitHubリポジトリ・メンバー・ラベル管理・稼働時間設定 |

- ナビバーに「GitHub同期」ボタンを常時表示する
- `/` へのアクセスは未認証なら `/login`、認証済みなら `/dashboard` にリダイレクト
- ウェルカムページ（`welcome.blade.php`）は削除する

---

## 認証仕様

- GitHub OAuth のみ（パスワード認証は使わない）
- パッケージ: `laravel/socialite`（要インストール）
- スコープ: `repo`, `read:user`, `read:org`
- `github_token` は Laravel の `encrypted` キャストで暗号化保存
- ログイン後は `/dashboard` にリダイレクト
- ログアウト後は `/login` にリダイレクト
- `config/services.php` に github の設定を追加する

```php
'github' => [
    'client_id'     => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect'      => env('GITHUB_REDIRECT_URI'),
],
```

---

## DB設計

### GitHub同期系テーブル

```
users
  id                bigint PK auto_increment
  github_id         string unique
  name              string
  avatar            string nullable
  github_token      text               -- encrypted キャスト必須
  created_at        timestamp
  updated_at        timestamp

members
  id                bigint PK auto_increment
  user_id           bigint FK -> users.id unique
  github_login      string             -- GitHubのlogin名（Assignee紐づけに使用）
  display_name      string
  daily_hours       integer default 6  -- 1日あたりの稼働時間
  created_at        timestamp
  updated_at        timestamp

repositories
  id                bigint PK auto_increment
  owner             string             -- org名 or ユーザー名
  name              string             -- リポジトリ名
  full_name         string unique      -- owner/name
  active            boolean default true
  synced_at         timestamp nullable
  created_at        timestamp
  updated_at        timestamp

milestones
  id                bigint PK auto_increment
  repository_id     bigint FK -> repositories.id
  github_milestone_id integer
  title             string
  due_on            date nullable
  state             string default 'open'
  synced_at         timestamp nullable
  created_at        timestamp
  updated_at        timestamp
  UNIQUE(repository_id, github_milestone_id)

sprints
  id                bigint PK auto_increment
  milestone_id      bigint FK -> milestones.id unique
  title             string
  start_date        date               -- due_onの8日前を初期値として自動計算、手動修正可
  end_date          date               -- milestones.due_on と同値
  working_days      integer default 5  -- 稼働日数（祝日週は手動変更）
  state             string default 'open'
  created_at        timestamp
  updated_at        timestamp

labels
  id                bigint PK auto_increment
  name              string unique      -- 複数リポジトリをまたいで同名は統合
  exclude_velocity  boolean default false
  created_at        timestamp
  updated_at        timestamp
```

### アプリ独自テーブル

```
epics
  id                bigint PK auto_increment
  title             string
  description       text nullable
  status            string default 'planning'  -- planning / in_progress / done
  created_at        timestamp
  updated_at        timestamp

issues
  id                bigint PK auto_increment
  repository_id     bigint FK -> repositories.id
  sprint_id         bigint FK -> sprints.id nullable
  epic_id           bigint FK -> epics.id nullable
  github_issue_number integer
  title             string
  state             string default 'open'
  assignee_login    string nullable
  story_points      integer nullable        -- 同期時に上書きしない
  exclude_velocity  boolean default false   -- 同期時に上書きしない
  synced_at         timestamp nullable
  created_at        timestamp
  updated_at        timestamp
  UNIQUE(repository_id, github_issue_number)

issue_labels
  issue_id          bigint FK -> issues.id
  label_id          bigint FK -> labels.id
  PRIMARY KEY(issue_id, label_id)

retrospectives
  id                bigint PK auto_increment
  sprint_id         bigint FK -> sprints.id
  type              string     -- keep / problem / try
  content           text
  created_at        timestamp
  updated_at        timestamp
```

---

## Eloquent モデルのリレーション

```
User        hasOne Member
Member      belongsTo User
Repository  hasMany Milestone / hasMany Issue
Milestone   belongsTo Repository / hasOne Sprint
Sprint      belongsTo Milestone / hasMany Issue / hasMany Retrospective
Epic        hasMany Issue
Issue       belongsTo Repository / belongsTo Sprint / belongsTo Epic
            belongsToMany Label (through issue_labels)
Label       belongsToMany Issue (through issue_labels)
Retrospective belongsTo Sprint
```

---

## GitHub同期仕様

### 同期トリガー
`POST /sync`（ナビバーの「GitHub同期」ボタン）

### App\Services\GitHubSyncService の処理内容

```
1. repositories テーブルで active=true のリポジトリを取得
2. 各リポジトリに対して以下を実行:
   a. GET /repos/{owner}/{repo}/milestones?state=all
      → milestones テーブルに upsert（repository_id + github_milestone_id で一意）
      → sprints テーブル:
         新規レコードのみ start_date を due_on の8日前で自動計算
         既存レコードの start_date / working_days は上書きしない
   b. GET /repos/{owner}/{repo}/issues?state=all&milestone={number}
      → issues テーブルに upsert（repository_id + github_issue_number で一意）
         story_points / exclude_velocity は既存値を保護（上書きしない）
   c. GET /repos/{owner}/{repo}/labels
      → labels テーブルに upsert（name で一意・複数リポジトリをまたいで統合）
3. issue_labels を更新
```

GitHub API の認証は `Auth::user()->github_token` を Bearer トークンとして使用。

---

## ベロシティ計算ロジック

```php
$issues = Issue::where('sprint_id', $sprintId)
    ->where('state', 'closed')
    ->where('exclude_velocity', false)
    ->whereDoesntHave('labels', fn($q) => $q->where('exclude_velocity', true))
    ->get();

$pointVelocity = $issues->sum('story_points');
$issueVelocity = $issues->count();
```

---

## エピック見積ロジック

```php
$totalPoints      = $epic->issues->sum('story_points');
$avgVelocity      = Sprint::where('state', 'closed')
                       ->latest('end_date')->take(3)->get()
                       ->avg(fn($s) => $s->pointVelocity());
$teamHoursPerSprint = Member::sum('daily_hours') * $defaultWorkingDays;
$estimatedSprints = $avgVelocity > 0 ? round($totalPoints / $avgVelocity, 1) : null;
$estimatedHours   = $estimatedSprints ? $estimatedSprints * $teamHoursPerSprint : null;
```

---

## 実装手順

**Step ごとに完了報告を行い、確認を取ってから次に進むこと。**

### Step 1: 認証・DB基盤
1. `laravel/socialite` をインストール
2. `config/services.php` に github 設定を追加
3. 全テーブルのマイグレーションファイルを作成・実行
4. 全 Eloquent モデルを作成（リレーション・キャスト定義含む）
5. GitHub OAuth のルート・コントローラを実装
   - `GET /auth/github` → GitHub認証画面へリダイレクト
   - `GET /auth/github/callback` → ユーザー作成/更新・ログイン・`/dashboard`へリダイレクト
   - `POST /logout` → ログアウト・`/login`へリダイレクト
6. `/login` 画面（「GitHubでログイン」ボタンのみのシンプルな画面）を実装
7. `/` を未認証→`/login`、認証済み→`/dashboard` にリダイレクト
8. `welcome.blade.php` を削除

### Step 2: GitHub同期
9. `App\Services\GitHubSyncService` を実装
10. 同期用コントローラ・ルート（`POST /sync`）を実装

### Step 3: 共通レイアウト・ダッシュボード
11. 共通レイアウトコンポーネント（ナビバー・GitHub同期ボタン）
12. ダッシュボード画面（メトリクス4枚・バーンダウンチャート・KPTサマリー・進行中Issue一覧）

### Step 4: 残り画面
13. スプリント詳細画面（Issue一覧タブ・バーンダウンチャート・人別推定工数グラフ）
14. エピック画面（エピック一覧・詳細・推定工数サマリー）
15. レトロスペクティブ画面（KPT入力・過去履歴一覧）
16. 設定画面（リポジトリ登録・メンバー管理・ラベル管理・稼働時間設定）

---

## 注意事項

- `github_token` は必ず `encrypted` キャストを使うこと
- Issue の `story_points` / `exclude_velocity` は同期時に既存値を上書きしないこと
- Sprint の `start_date` / `working_days` は同期時に既存値を上書きしないこと
- バーンダウンチャート・ベロシティグラフは Recharts で React コンポーネントとして実装
- Inertia.js の `useForm` / `usePage` を活用すること
- 全ルートに `auth` ミドルウェアをかけること（`/auth/github` 系・`/login` は除く）
- 不明点があれば実装を止めて質問すること
