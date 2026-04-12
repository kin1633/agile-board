# Agile Board ドキュメント

GitHub Issues を使ったアジャイル開発チーム向けのプロジェクト管理ツールです。
GitHub のスプリント（Iteration）・Issue を同期し、マイルストーンの独立管理・スプリント管理・バーンダウンチャート・レトロスペクティブを一元管理できます。

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
- [バックログ](./usage/backlog.md)
- [デイリースクラム](./usage/daily-scrum.md)
- [マイルストーン](./usage/milestones.md)
- [エピック](./usage/epics.md)
- [レトロスペクティブ](./usage/retrospectives.md)
- [実績入力（ワークログ）](./usage/work-logs.md)
- [勤怠管理](./usage/attendance.md)
- [設定](./usage/settings.md)

### スライド資料

エンジニア向けのビジュアル資料（Marp スライド）を GitHub Pages で公開しています。

- 🔗 **https://kin1633.github.io/agile-board-docs/**
  - [1. 基本概念](https://kin1633.github.io/agile-board-docs/1_overview.html) — データモデル・同期フロー・ベロシティ計算など
  - [2. 使い方ガイド](https://kin1633.github.io/agile-board-docs/2_usage.html) — セットアップから各画面の操作手順まで

> スライドのソース: https://github.com/kin1633/agile-board-docs

---

## 画面構成

```
/                        → ダッシュボード（現在のスプリント概要・ベロシティトレンド）
/sprints                 → スプリント一覧
/sprints/{id}            → スプリント詳細（Issue・バーンダウン・担当者別）
/sprints/{id}/plan       → スプリント計画（キャパシティ計算・Issue 割り当て・DoD）
/sprints/{id}/board      → スプリントボード（カンバン・PR/CI 表示）
/sprints/{id}/cfd        → 累積フロー図（CFD）
/sprints/{id}/review     → スプリントレビュー記録
/backlog                 → バックログ（スプリント未割当 Issue）
/daily-scrum             → デイリースクラム進捗記録
/milestones              → マイルストーン一覧
/epics                   → エピック管理・ロードマップ
/retrospectives          → レトロスペクティブ（KPT）
/work-logs               → 実績入力（週次タイムライン）
/attendance              → 勤怠管理（休暇・早退・遅刻）
/settings/general           → 一般設定（工数基準・外観・実績入力設定）
/settings/repositories      → リポジトリ設定
/settings/members           → メンバー設定
/settings/labels            → ラベル設定
/settings/work-log-categories → 実績種別管理
/settings/holidays          → 祝日管理
```
