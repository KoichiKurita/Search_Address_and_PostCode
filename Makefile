# 郵便番号・住所検索 開発用コマンド集
#   make help で一覧を表示
#   Windows の場合は WSL2（Ubuntu）上で実行してください

SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
WORK_DIR := db/work
# 日本郵便「住所の郵便番号（1レコード1行、UTF-8形式）」全国一括データ
# 現在の URL を先に試し、使えなければ移転前の URL を試す（KEN_ALL_URL=... で上書き可）
KEN_ALL_URL ?= https://www.post.japanpost.jp/service/search/zipcode/download/utf/zip/utf_ken_all.zip
KEN_ALL_URL_ALT ?= https://www.post.japanpost.jp/zipcode/dl/utf/zip/utf_ken_all.zip

.PHONY: help init up down restart logs ps sh db tools seed-full db-reset lint gh-setup gh-setup-dry

help: ## このヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

init: ## 初回セットアップ（.env 作成 → ビルド → 起動）
	@test -f .env || (cp .env.example .env && echo ".env を作成しました")
	$(COMPOSE) build
	$(MAKE) up

up: ## 開発環境を起動
	$(COMPOSE) up -d
	@echo "→ http://localhost:$${APP_PORT:-8080}/search_address.html"

down: ## 開発環境を停止
	$(COMPOSE) down

restart: down up ## 再起動

logs: ## ログを表示（Ctrl+C で終了）
	$(COMPOSE) logs -f

ps: ## コンテナの状態を表示
	$(COMPOSE) ps

sh: ## app コンテナに入る
	$(COMPOSE) exec app bash

db: ## MySQL クライアントで DB に接続
	$(COMPOSE) exec db sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

tools: ## phpMyAdmin も起動（http://localhost:8081）
	$(COMPOSE) --profile tools up -d

seed-full: ## 日本郵便の全国データ（約12万件）をダウンロードして投入
	mkdir -p $(WORK_DIR)
	curl -fsSL -o $(WORK_DIR)/utf_ken_all.zip "$(KEN_ALL_URL)" \
	  || curl -fsSL -o $(WORK_DIR)/utf_ken_all.zip "$(KEN_ALL_URL_ALT)"
	unzip -o -q $(WORK_DIR)/utf_ken_all.zip -d $(WORK_DIR)
	python3 db/ken_all_to_sql.py $(WORK_DIR)/utf_ken_all.csv $(WORK_DIR)/out
	cat $(WORK_DIR)/out/*.sql | $(COMPOSE) exec -T db sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
	$(COMPOSE) exec -T db sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" -e "SELECT COUNT(*) AS count FROM post_codes;"'

db-reset: ## DB を削除して作り直す（サンプルデータに戻る）
	$(COMPOSE) down -v
	$(MAKE) up

lint: ## PHP の構文チェック（CI と同じ内容）
	$(COMPOSE) run --rm --no-deps app sh -c "find . -name '*.php' -not -path './.git/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l"

gh-setup-dry: ## GitHub 設定の確認だけ（何も変更しない）
	python3 scripts/github/setup.py all --dry-run

gh-setup: ## GitHub のラベル・マイルストーン・Issue・ブランチ保護などを作成/更新
	python3 scripts/github/setup.py all
