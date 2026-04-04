# システムアーキテクチャ

## 技術スタック

| 分類 | 技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 13 |
| フロントエンド | React 19 / TypeScript |
| サーバーサイドレンダリング連携 | Inertia.js v2 |
| スタイリング | Tailwind CSS v4 |
| ルート型安全 | Laravel Wayfinder |
| グラフ描画 | Recharts |
| 認証 | GitHub OAuth（Laravel Socialite） |
| テスト | Pest v4 |

---

## アーキテクチャ概要

```
ブラウザ (React + Inertia.js)
    ↕ HTTP (Inertia プロトコル)
Laravel (コントローラー → Inertia::render)
    ↕ Eloquent ORM
データベース (SQLite / MySQL)
    ↑
GitHub API (同期時のみ)
```

Inertia.js により、Laravel のルーティング・コントローラーをそのまま使いながら React SPA として動作します。ページ遷移ごとに JSON レスポンスだけを返すため、フルリロードなしにページが切り替わります。

---

## ディレクトリ構成（主要部分）

```
app/
  Http/
    Controllers/
      Auth/           # GitHub OAuth / ログアウト
      Settings/       # 設定系コントローラー
      DashboardController.php
      EpicController.php
      MilestoneController.php
      RetrospectiveController.php
      SprintController.php
      SyncController.php
  Models/             # Eloquent モデル
  Services/
    GitHubSyncService.php  # GitHub API 同期ロジック

resources/js/
  pages/              # React ページコンポーネント（Inertia のルート）
    dashboard.tsx
    milestones/
    sprints/
    epics/
    retrospectives/
    settings/
  components/         # 共通 UI コンポーネント
  layouts/            # AppLayout など共通レイアウト
  routes/             # Wayfinder 生成のルート関数
  actions/            # Wayfinder 生成のアクション関数

routes/
  web.php             # 全ルート定義

database/
  migrations/         # マイグレーションファイル

tests/
  Feature/            # 機能テスト（コントローラー・API）
  Unit/               # ユニットテスト（モデル・サービス）
```

---

## 認証フロー

```
ユーザー → /login → GitHub OAuth → /auth/github/callback
    → GitHubController がユーザー情報を取得
    → users テーブルに upsert（同一 github_id なら更新）
    → セッション確立 → /dashboard にリダイレクト
```

---

## データ同期フロー

詳細は [GitHub 同期フロー](./sync-flow.md) を参照してください。

---

## ルート型安全（Wayfinder）

フロントエンドから Laravel ルートを呼び出す際、`php artisan wayfinder:generate` で生成された TypeScript 関数を使います。URL の文字列直書きを排除し、型チェックが効く状態を維持します。

```typescript
// 例: エピック更新
router.put(epicRoutes.update({ epic: id }).url, data);
```
