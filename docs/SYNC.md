# ドキュメント同期状態

最後にドキュメントを実装と照合したコミット:

```
bf6acb9  2026-04-05  fix: MilestoneFactory の year/month 重複によるテストの UNIQUE 制約違反を修正
```

次回 docs 更新時は以下のコマンドで差分を確認する:

```bash
git log bf6acb9..HEAD --oneline
```
