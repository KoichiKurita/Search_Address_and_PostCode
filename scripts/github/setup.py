#!/usr/bin/env python3
"""
GitHub リポジトリの設定をコードで管理するスクリプト（GitHub CLI `gh` を使用）

project.json の内容に合わせて、次のものを作成・更新する。何度実行しても同じ結果になる（冪等）。
  repo         マージ方法（Squash のみ）、マージ後のブランチ自動削除
  labels       ラベル
  milestones   マイルストーン（フェーズ）
  issues       Issue（同じタイトルの Issue があれば作らない）
  ruleset      main のブランチ保護（PR 必須・CI 必須・強制 push / 削除の禁止）
  environment  デプロイ用 Environment（production：main からのみデプロイ可）
  secrets      Environment の Secrets / Variables（scripts/github/deploy.env がある場合のみ）
  tag          ベースラインのタグとリリース（v0.1.0）

使い方:
  python3 scripts/github/setup.py all --dry-run   # 何が変わるかを表示するだけ
  python3 scripts/github/setup.py all             # 実行
  python3 scripts/github/setup.py labels issues   # 一部だけ実行

前提:
  gh auth login 済みで、リポジトリの管理者権限があること。標準ライブラリのみで動作する。
"""
from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
PROJECT_FILE = HERE / "project.json"
DEPLOY_ENV_FILE = HERE / "deploy.env"

# deploy.env のキーの振り分け
ENV_VARIABLES = ["DEPLOY_PORT", "APP_DIR", "APP_URL"]
ENV_SECRETS = ["DEPLOY_HOST", "DEPLOY_USER"]
ENV_SECRET_FILES = {"SSH_PRIVATE_KEY_FILE": "SSH_PRIVATE_KEY", "SSH_KNOWN_HOSTS_FILE": "SSH_KNOWN_HOSTS"}

ALL_STEPS = ["repo", "labels", "milestones", "issues", "ruleset", "environment", "secrets", "tag"]

DRY_RUN = False


# ---------------------------------------------------------------------------
# gh コマンドの呼び出し
# ---------------------------------------------------------------------------
class GhError(Exception):
    pass


def gh(*args: str, input_text: str | None = None, mutate: bool = False, show: str | None = None) -> str:
    """gh を実行して標準出力を返す。mutate=True の処理は dry-run 時に実行しない。"""
    if mutate and DRY_RUN:
        print(f"    [dry-run] {show or 'gh ' + ' '.join(args)}")
        return ""
    # 渡す入力がないときは標準入力を空にして、gh が入力待ちで止まらないようにする
    stdin_opts = {"input": input_text} if input_text is not None else {"stdin": subprocess.DEVNULL}
    result = subprocess.run(["gh", *args], text=True, capture_output=True, **stdin_opts)
    if result.returncode != 0:
        raise GhError(result.stderr.strip() or result.stdout.strip())
    return result.stdout


def api(method: str, path: str, payload: dict | None = None, mutate: bool = False):
    args = ["api", "-X", method, path, "-H", "Accept: application/vnd.github+json"]
    body = None
    if payload is not None:
        args += ["--input", "-"]
        body = json.dumps(payload, ensure_ascii=False)
    out = gh(*args, input_text=body, mutate=mutate, show=f"gh api -X {method} {path}")
    return json.loads(out) if out.strip() else None


def api_exists(path: str) -> bool:
    try:
        api("GET", path)
        return True
    except GhError as e:
        if "404" in str(e) or "Not Found" in str(e):
            return False
        raise


# ---------------------------------------------------------------------------
# 各ステップ
# ---------------------------------------------------------------------------
def step_repo(cfg: dict, repo: str) -> None:
    desired = cfg["repo_settings"]
    current = api("GET", f"repos/{repo}")
    diff = {k: v for k, v in desired.items() if current.get(k) != v}
    if not diff:
        print("  変更なし")
        return
    for k, v in diff.items():
        print(f"  {k}: {current.get(k)} → {v}")
    api("PATCH", f"repos/{repo}", diff, mutate=True)


def step_labels(cfg: dict, repo: str) -> None:
    existing = {l["name"]: l for l in json.loads(gh("label", "list", "--repo", repo, "--limit", "300",
                                                    "--json", "name,color,description"))}
    for label in cfg["labels"]:
        cur = existing.get(label["name"])
        if cur and cur["color"].lower() == label["color"].lower() and cur["description"] == label["description"]:
            continue
        print(f"  {'更新' if cur else '作成'}: {label['name']}")
        gh("label", "create", label["name"], "--color", label["color"], "--description", label["description"],
           "--force", "--repo", repo, mutate=True)
    print("  完了")


def step_milestones(cfg: dict, repo: str) -> None:
    existing = {m["title"]: m for m in api("GET", f"repos/{repo}/milestones?state=all&per_page=100")}
    for ms in cfg["milestones"]:
        cur = existing.get(ms["title"])
        payload = {"title": ms["title"], "description": ms["description"]}
        if ms.get("due_on"):
            payload["due_on"] = ms["due_on"]          # 例: "2026-12-31T23:59:59Z"
        if cur is None:
            print(f"  作成: {ms['title']}")
            api("POST", f"repos/{repo}/milestones", payload, mutate=True)
        elif cur.get("description") != ms["description"] or (ms.get("due_on") and cur.get("due_on") != ms["due_on"]):
            print(f"  更新: {ms['title']}")
            api("PATCH", f"repos/{repo}/milestones/{cur['number']}", payload, mutate=True)
    print("  完了")


def step_issues(cfg: dict, repo: str) -> None:
    existing = {i["title"]: i["number"] for i in json.loads(
        gh("issue", "list", "--repo", repo, "--state", "all", "--limit", "1000", "--json", "number,title"))}
    created = 0
    for issue in cfg["issues"]:
        if issue["title"] in existing:
            continue
        args = ["issue", "create", "--repo", repo, "--title", issue["title"], "--body-file", "-"]
        for label in issue.get("labels", []):
            args += ["--label", label]
        if issue.get("milestone"):
            args += ["--milestone", issue["milestone"]]
        url = gh(*args, input_text=issue["body"], mutate=True,
                 show=f"gh issue create \"{issue['title']}\" [{issue.get('milestone') or 'マイルストーンなし'}]").strip()
        print(f"  作成: {issue['title']} {url}")
        created += 1
    print(f"  完了（新規 {created} 件 / 既存 {len(cfg['issues']) - created} 件はスキップ）")


def step_ruleset(cfg: dict, repo: str) -> None:
    rs = cfg["ruleset"]
    payload = {
        "name": rs["name"],
        "target": "branch",
        "enforcement": "active",
        "conditions": {"ref_name": {"include": ["~DEFAULT_BRANCH"], "exclude": []}},
        "rules": [
            {"type": "deletion"},                     # ブランチ削除の禁止
            {"type": "non_fast_forward"},             # 強制 push の禁止
            {"type": "pull_request", "parameters": {  # PR 経由でのみ変更可能
                "required_approving_review_count": 0,    # 個人開発なので承認数は 0
                "dismiss_stale_reviews_on_push": False,
                "require_code_owner_review": False,
                "require_last_push_approval": False,
                "required_review_thread_resolution": True,
            }},
            {"type": "required_status_checks", "parameters": {  # CI が通らないとマージ不可
                "strict_required_status_checks_policy": True,
                "required_status_checks": [{"context": c} for c in rs["required_status_checks"]],
            }},
        ],
        # 管理者は「PR 経由なら」緊急時にチェックを飛ばしてマージできる（直接 push は不可）
        "bypass_actors": [{"actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "pull_request"}],
    }
    current = next((r for r in api("GET", f"repos/{repo}/rulesets") if r["name"] == rs["name"]), None)
    if current is None:
        print(f"  作成: {rs['name']}")
        api("POST", f"repos/{repo}/rulesets", payload, mutate=True)
    else:
        print(f"  更新: {rs['name']}（id={current['id']}）")
        api("PUT", f"repos/{repo}/rulesets/{current['id']}", payload, mutate=True)


def step_environment(cfg: dict, repo: str) -> None:
    env = cfg["environment"]
    name = env["name"]
    print(f"  Environment: {name}（デプロイ可能なブランチ: {', '.join(env['deployment_branches'])}）")
    api("PUT", f"repos/{repo}/environments/{name}",
        {"deployment_branch_policy": {"protected_branches": False, "custom_branch_policies": True}}, mutate=True)
    try:
        policies = api("GET", f"repos/{repo}/environments/{name}/deployment-branch-policies")["branch_policies"]
    except GhError:
        policies = []          # dry-run で Environment がまだ無い場合
    have = {p["name"] for p in policies}
    for branch in env["deployment_branches"]:
        if branch not in have:
            api("POST", f"repos/{repo}/environments/{name}/deployment-branch-policies",
                {"name": branch, "type": "branch"}, mutate=True)


def read_deploy_env() -> dict[str, str]:
    values: dict[str, str] = {}
    for line in DEPLOY_ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def step_secrets(cfg: dict, repo: str) -> None:
    if not DEPLOY_ENV_FILE.exists():
        print(f"  スキップ（{DEPLOY_ENV_FILE.relative_to(HERE.parent.parent)} がありません）")
        return
    env_name = cfg["environment"]["name"]
    values = read_deploy_env()

    for key in ENV_VARIABLES:
        if values.get(key):
            print(f"  Variable: {key} = {values[key]}")
            gh("variable", "set", key, "--env", env_name, "--body", values[key], "--repo", repo, mutate=True)

    # Secret の値は画面に出さない（標準入力で渡す）
    secrets = {k: values[k] for k in ENV_SECRETS if values.get(k)}
    for file_key, secret_name in ENV_SECRET_FILES.items():
        if values.get(file_key):
            path = Path(values[file_key]).expanduser()
            if not path.exists():
                raise SystemExit(f"ファイルが見つかりません: {path}（{file_key}）")
            secrets[secret_name] = path.read_text(encoding="utf-8")
    for name, value in secrets.items():
        print(f"  Secret: {name}（値は表示しません）")
        gh("secret", "set", name, "--env", env_name, "--repo", repo, input_text=value, mutate=True,
           show=f"gh secret set {name} --env {env_name}")

    # デプロイの停止スイッチ（リポジトリ全体の Variable）。止めたいときは false にする
    enabled = values.get("DEPLOY_ENABLED", "true")
    print(f"  Variable（リポジトリ）: DEPLOY_ENABLED = {enabled}")
    gh("variable", "set", "DEPLOY_ENABLED", "--body", enabled, "--repo", repo, mutate=True)


def step_tag(cfg: dict, repo: str) -> None:
    tag = cfg["baseline_tag"]
    if api_exists(f"repos/{repo}/git/ref/tags/{tag['tag']}"):
        print(f"  既存: {tag['tag']}")
        return
    print(f"  作成: {tag['tag']}（{cfg['default_branch']} の最新コミット）")
    gh("release", "create", tag["tag"], "--repo", repo, "--target", cfg["default_branch"],
       "--title", tag["title"], "--notes", tag["notes"], mutate=True)


STEPS = {
    "repo": ("リポジトリ設定", step_repo),
    "labels": ("ラベル", step_labels),
    "milestones": ("マイルストーン", step_milestones),
    "issues": ("Issue", step_issues),
    "ruleset": ("ブランチ保護（Ruleset）", step_ruleset),
    "environment": ("Environment", step_environment),
    "secrets": ("Secrets / Variables", step_secrets),
    "tag": ("ベースラインタグ", step_tag),
}


def main() -> None:
    global DRY_RUN
    parser = argparse.ArgumentParser(description="GitHub リポジトリの設定を project.json に合わせる")
    parser.add_argument("steps", nargs="+", choices=["all", *ALL_STEPS], help="実行するステップ（all で全部）")
    parser.add_argument("--dry-run", action="store_true", help="変更せずに内容だけ表示する")
    parser.add_argument("--repo", help="owner/name（省略時は project.json の repository）")
    args = parser.parse_args()
    DRY_RUN = args.dry_run

    if shutil.which("gh") is None:
        raise SystemExit("gh（GitHub CLI）が見つかりません。https://cli.github.com/ からインストールしてください。")
    try:
        gh("auth", "status")
    except GhError:
        raise SystemExit("gh にログインしていません。`gh auth login` を実行してください。")

    cfg = json.loads(PROJECT_FILE.read_text(encoding="utf-8"))
    repo = args.repo or cfg["repository"]
    steps = ALL_STEPS if "all" in args.steps else [s for s in ALL_STEPS if s in args.steps]

    print(f"対象リポジトリ: {repo}{'（dry-run）' if DRY_RUN else ''}")
    for key in steps:
        title, func = STEPS[key]
        print(f"\n■ {title}")
        try:
            func(cfg, repo)
        except GhError as e:
            print(f"  エラー: {e}", file=sys.stderr)
            sys.exit(1)
    print("\nすべて完了しました。")


if __name__ == "__main__":
    main()
