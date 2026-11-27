#!/bin/sh
# =============================================================================
# 参照専用の DB ユーザー（SELECT だけ）を作る
#   docker-entrypoint-initdb.d に置くと、DB の初回作成時に1回だけ実行される
#   （作り直すときは make db-reset）
#
#   ユーザーの役割分担（最小権限の原則）
#     DB_READONLY_USERNAME … アプリ（PHP）が使う。SELECT だけできる
#     MYSQL_USER           … データ投入（make seed-full）と管理（make db）で使う。この DB の全権限
#
#   ※ このファイルは docker-entrypoint.sh から読み込まれて（source されて）実行されるため、
#     set -u などで呼び出し元の動作を変えないようにしている
# =============================================================================

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS '${DB_READONLY_USERNAME}'@'%' IDENTIFIED BY '${DB_READONLY_PASSWORD}';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.* TO '${DB_READONLY_USERNAME}'@'%';
SQL

echo "参照専用ユーザー ${DB_READONLY_USERNAME} を作成しました（SELECT のみ）"
