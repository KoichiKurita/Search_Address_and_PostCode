# 郵便番号・住所検索 開発用コマンド集
#   make help で一覧を表示
#   Windows の場合は WSL2（Ubuntu）上で実行してください

SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose

# .env の値（DB のユーザー名など）を、make の変数としても使えるようにする（無ければ下の初期値）
-include .env
DB_LOADER_USERNAME ?= loader
DB_LOADER_PASSWORD ?= loader_password

# app コンテナで一時的にコマンドを実行する（自分のユーザー ID で動かし、作られるファイルを root の持ち物にしない）
RUN_APP := $(COMPOSE) run --rm --no-deps --user "$$(id -u):$$(id -g)" -e COMPOSER_HOME=/tmp/composer app

WORK_DIR := db/work
# 日本郵便「住所の郵便番号（1レコード1行、UTF-8形式）」全国一括データ
# 現在の URL を先に試し、使えなければ移転前の URL を試す（KEN_ALL_URL=... で上書き可）
KEN_ALL_URL ?= https://www.post.japanpost.jp/service/search/zipcode/download/utf/zip/utf_ken_all.zip
KEN_ALL_URL_ALT ?= https://www.post.japanpost.jp/zipcode/dl/utf/zip/utf_ken_all.zip
# 事業所の個別郵便番号（#36。Shift_JIS）
JIGYOSYO_URL ?= https://www.post.japanpost.jp/service/search/zipcode/download/office/zip/jigyosyo.zip
JIGYOSYO_URL_ALT ?= https://www.post.japanpost.jp/zipcode/dl/jigyosyo/zip/jigyosyo.zip

.PHONY: help init build up down restart logs ps sh db tools seed-full import db-reset lint composer-install composer test stan fmt fmt-check gh-setup gh-setup-dry

help: ## このヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

init: ## 初回セットアップ（.env 作成 → ビルド → 起動）
	@test -f .env || (cp .env.example .env && echo ".env を作成しました")
	$(COMPOSE) build
	$(MAKE) composer-install
	$(MAKE) up

up: ## 開発環境を起動
	$(COMPOSE) up -d
	@echo "→ http://localhost:$${APP_PORT:-8080}/address"

down: ## 開発環境を停止
	$(COMPOSE) down

restart: down up ## 再起動

build: ## Docker のイメージを作り直す（docker/php/Dockerfile を変えたとき）
	$(COMPOSE) build

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

seed-full: ## 日本郵便の全国データ（住所 約12万件・事業所 約2万件）をダウンロードして投入（本番の月次更新と同じ手順）
	mkdir -p $(WORK_DIR)
	curl -fsSL -o $(WORK_DIR)/utf_ken_all.zip "$(KEN_ALL_URL)" \
	  || curl -fsSL -o $(WORK_DIR)/utf_ken_all.zip "$(KEN_ALL_URL_ALT)"
	unzip -o -q $(WORK_DIR)/utf_ken_all.zip -d $(WORK_DIR)
	python3 db/ken_all_to_sql.py $(WORK_DIR)/utf_ken_all.csv $(WORK_DIR)/post_codes.csv --format csv
	$(MAKE) import CSV=$(WORK_DIR)/post_codes.csv
	curl -fsSL -o $(WORK_DIR)/jigyosyo.zip "$(JIGYOSYO_URL)" \
	  || curl -fsSL -o $(WORK_DIR)/jigyosyo.zip "$(JIGYOSYO_URL_ALT)"
	@# zip の中のファイル名は大文字（JIGYOSYO.CSV）のことがあるので、名前に頼らず中身を取り出す
	unzip -p $(WORK_DIR)/jigyosyo.zip > $(WORK_DIR)/jigyosyo.csv
	python3 db/jigyosyo_to_csv.py $(WORK_DIR)/jigyosyo.csv $(WORK_DIR)/offices.csv
	$(MAKE) import CSV=$(WORK_DIR)/offices.csv OFFICES=1

import: ## CSV を DB に取り込む（例: make import CSV=db/work/post_codes.csv / 事業所は OFFICES=1 を付ける）
	@test -n "$(CSV)" || (echo "CSV=… で取り込む CSV を指定してください" && exit 1)
	@# アプリは SELECT だけのユーザーで動くので、取り込みのときだけ、書き込みのできるユーザーで接続する
	$(COMPOSE) run --rm -e DB_USERNAME="$(DB_LOADER_USERNAME)" -e DB_PASSWORD="$(DB_LOADER_PASSWORD)" app \
	  php bin/import-postcodes.php $(if $(OFFICES),--offices) $(CSV)

db-reset: ## DB を削除して作り直す（サンプルデータに戻る）
	$(COMPOSE) down -v
	$(MAKE) up

lint: ## PHP の構文チェック（CI と同じ内容）
	$(COMPOSE) run --rm --no-deps app sh -c "find . -name '*.php' -not -path './.git/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l"

composer-install: ## Composer のパッケージ（PHPUnit など）を vendor/ に入れる
	$(RUN_APP) composer install

composer: ## 任意の Composer コマンドを実行する（例: make composer ARGS="require --dev 〇〇"）
	$(RUN_APP) composer $(ARGS)

test: ## PHPUnit でテストを実行する（初回は make composer-install が必要）
	$(RUN_APP) vendor/bin/phpunit

stan: ## PHPStan で静的解析する（CI と同じ内容）
	$(RUN_APP) vendor/bin/phpstan analyse --memory-limit=1G

fmt: ## PHP-CS-Fixer でコードを自動整形する（PSR-12）
	$(RUN_APP) vendor/bin/php-cs-fixer fix

fmt-check: ## 整形が必要な箇所を表示するだけ（CI と同じ内容。ファイルは変更しない）
	$(RUN_APP) vendor/bin/php-cs-fixer fix --dry-run --diff
	
gh-setup-dry: ## GitHub 設定の確認だけ（何も変更しない）
	python3 scripts/github/setup.py all --dry-run

gh-setup: ## GitHub のラベル・マイルストーン・Issue・ブランチ保護などを作成/更新
	python3 scripts/github/setup.py all
