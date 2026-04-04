# GitHub 連携設定

## 1. GitHub OAuth アプリの作成

GitHub でログイン認証とリポジトリ同期を行うために、OAuth アプリの登録が必要です。

1. GitHub → **Settings** → **Developer settings** → **OAuth Apps** → **New OAuth App**
2. 以下の情報を入力する

| 項目 | 値（ローカル開発時） |
|---|---|
| Application name | Agile Board（任意） |
| Homepage URL | `http://localhost:8000` |
| Authorization callback URL | `http://localhost:8000/auth/github/callback` |

3. **Register application** をクリック
4. **Client ID** と **Client Secret** をメモする

### .env への設定

```env
GITHUB_CLIENT_ID=取得したClient ID
GITHUB_CLIENT_SECRET=取得したClient Secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```

---

## 2. リポジトリの登録

ログイン後、**設定 → リポジトリ** から監視するリポジトリを追加します。

- オーナー名（例: `octocat`）とリポジトリ名（例: `hello-world`）を入力
- 追加したリポジトリを「有効」に切り替える

詳細は [設定画面の使い方](../usage/settings.md) を参照してください。

---

## 3. スプリントの同期モード

リポジトリに `github_project_number` を設定しているかどうかで、スプリントの扱いが変わります。

| 条件 | 動作 |
|---|---|
| `github_project_number` 設定済み | **Iteration モード**: GitHub Projects の `Sprint` Iteration フィールドからスプリントを同期 |
| `github_project_number` 未設定（デフォルト） | **同期なし**: スプリントは手動管理 |

> **マイルストーンについて**: マイルストーンはアプリ独自管理です。GitHub マイルストーンとの同期は行いません。マイルストーン一覧画面にアクセスするたびに、現在月 −6〜+12ヶ月分が自動生成されます。

---

## 4. GitHub 同期

ログイン後、ナビゲーションバーの **「GitHub 同期」** ボタンを押すと、登録済みのアクティブなリポジトリからデータを取得します。

同期される内容：
- スプリント（Iteration モードのみ: GitHub Projects の Sprint Iteration → スプリント）
- Issue（担当者・状態・クローズ日時）
- ラベル

同期されない（手動設定値として保護される）項目：
- Issue のストーリーポイント
- Issue のベロシティ除外フラグ
- スプリントの開始日・稼働日数
- マイルストーン（アプリ独自管理のため同期対象外）

---

## 5. 必要な GitHub 権限

OAuth ログイン時に要求する権限は以下の通りです。

| スコープ | 用途 |
|---|---|
| `read:user` | ユーザー情報の取得（プロフィール・ログイン） |
| `repo` | プライベートリポジトリの Issue・マイルストーン読み取り |
