# 開発ルール

## ブランチ戦略（GitHub Flow）

```
main ────●─────────●─────────●──────▶  常に本番と同じ状態（push で自動デプロイ）
          \       / \       /
           ●──●──●   ●──●──●
        feature/12-xxx   fix/15-xxx
```

- `main` は **常にデプロイ可能**な状態を保つ。直接 push はできない（ブランチ保護）
- 作業は必ず Issue を起点にし、`main` からブランチを切る
- Pull Request を作ると CI（lint）が走る。通ったら **Squash merge** する
- マージ後、ブランチは自動で削除される

### ブランチ名

`<種類>/<Issue番号>-<短い説明>`（英小文字とハイフン）

| 種類 | 用途 | 例 |
|---|---|---|
| `feature/` | 新機能 | `feature/31-json-api` |
| `fix/` | 不具合修正 | `fix/5-fullwidth-hyphen` |
| `security/` | セキュリティ対応 | `security/16-input-validation` |
| `refactor/` | 動作を変えない設計改善 | `refactor/27-repository-layer` |
| `docs/` | ドキュメント | `docs/14-readme` |
| `chore/` | 環境・設定・CI | `chore/1-docker` |

## コミットメッセージ（Conventional Commits）

```
<type>: <要約（日本語OK）> (#Issue番号)
```

例: `fix: 全角ハイフンが正規化されない不具合を修正 (#5)`

type は `feat` / `fix` / `security` / `refactor` / `perf` / `test` / `docs` / `ci` / `chore` から選ぶ。

## 1つの Issue を進める流れ（すべてコマンドで操作）

```bash
# 1. Issue を確認
gh issue list --milestone "Phase 1: 不具合修正・ドキュメント"

# 2. Issue に紐づいたブランチを作成してチェックアウト
gh issue develop 5 --name fix/5-fullwidth-hyphen --base main --checkout

# 3. 実装して動作確認
make up
make lint

# 4. コミットして push
git add -A
git commit -m "fix: 全角ハイフンが正規化されない不具合を修正 (#5)"
git push -u origin HEAD

# 5. PR を作成（本文に Closes #5 を書くと、マージ時に Issue が自動で閉じる）
gh pr create --fill --body "Closes #5"

# 6. CI が通るのを待ってから Squash merge（ブランチも削除される）
gh pr checks --watch
gh pr merge --squash --delete-branch

# 7. main を最新にする
git switch main && git pull
```

## バージョンとリリース（セマンティックバージョニング）

マイルストーン（フェーズ）が完了するたびに、タグとリリースを作る。

| マイルストーン | バージョン |
|---|---|
| 改修前（ベースライン） | `v0.1.0` |
| Phase 0: 開発基盤整備 | `v0.2.0` |
| Phase 1: 不具合修正・ドキュメント | `v0.3.0` |
| Phase 2: セキュリティ強化 | `v0.4.0` |
| Phase 3: 品質・非機能要件 | `v0.5.0` |
| Phase 4: 設計刷新・機能追加 | `v1.0.0` |

```bash
# リリースノートは PR のラベルから自動で分類される（.github/release.yml）
gh release create v0.2.0 --target main --title "v0.2.0 開発基盤整備" --generate-notes
gh api -X PATCH "repos/{owner}/{repo}/milestones/<番号>" -f state=closed
```
