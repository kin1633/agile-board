# はじめに（ローカル環境構築）

## 必要なもの

- PHP 8.4 以上
- Composer
- Node.js 22 以上
- npm
- SQLite（デフォルト）または MySQL / PostgreSQL

---

## セットアップ手順

### 1. リポジトリのクローン

```bash
git clone https://github.com/your-org/agile-board.git
cd agile-board
```

### 2. 依存関係のインストール

```bash
composer install
npm install
```

### 3. 環境変数の設定

```bash
cp .env.example .env
php artisan key:generate
```

`.env` を開き、最低限以下を設定してください。

```env
APP_URL=http://localhost:8000

# GitHub OAuth（取得方法は github-setup.md を参照）
GITHUB_CLIENT_ID=your_client_id
GITHUB_CLIENT_SECRET=your_client_secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```

### 4. データベースのセットアップ

```bash
php artisan migrate
```

### 5. フロントエンドビルド

```bash
npm run build
# 開発中はホットリロードが使える
# npm run dev
```

### 6. 開発サーバーの起動

```bash
composer run dev
```

ブラウザで `http://localhost:8000` を開き、「GitHub でログイン」ボタンが表示されれば成功です。

---

## テスト実行

```bash
php artisan test --compact
```

---

## 本番デプロイ時の追加設定

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

GITHUB_REDIRECT_URI=https://your-domain.com/auth/github/callback
```

キャッシュを最適化します。

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```
