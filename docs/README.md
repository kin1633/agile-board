# Agile Board ドキュメント

GitHub Issues を使ったアジャイル開発チーム向けのプロジェクト管理ツールです。
GitHub のマイルストーン・Issue を同期し、スプリント管理・バーンダウンチャート・レトロスペクティブを一元管理できます。

---

## ドキュメント一覧

### セットアップ
- [はじめに（ローカル環境構築）](./setup/getting-started.md)
- [GitHub 連携設定](./setup/github-setup.md)

### 設計
- [システムアーキテクチャ](./design/architecture.md)
- [データモデル](./design/data-model.md)
- [GitHub 同期フロー](./design/sync-flow.md)

### 使い方
- [ダッシュボード](./usage/dashboard.md)
- [スプリント](./usage/sprints.md)
- [マイルストーン](./usage/milestones.md)
- [エピック](./usage/epics.md)
- [レトロスペクティブ](./usage/retrospectives.md)
- [設定](./usage/settings.md)

---

## 画面構成

```
/                  → ダッシュボード（現在のスプリント概要）
/sprints           → スプリント一覧
/sprints/{id}      → スプリント詳細（Issue・バーンダウン・担当者別）
/milestones        → マイルストーン一覧
/epics             → エピック管理
/retrospectives    → レトロスペクティブ（KPT）
/settings/repositories → リポジトリ設定
/settings/members      → メンバー設定
/settings/labels       → ラベル設定
```
