-- =============================================================================
-- 003: フリガナの列を追加する（#37）
--   既存の DB（本番）に1回だけ実行する。新しく作る DB は db/schema.sql に反映済み
--   phpMyAdmin の「SQL」タブ、または mysql コマンドで実行する
--
--   ※ アプリをデプロイする「前」に実行すること（新しいアプリは、この列があることを前提に検索する）
--     列を足すだけで、今のアプリが使う列は変わらないので、先に実行しても今のアプリはそのまま動く
--   ※ 実行直後のフリガナは空。GitHub Actions の「郵便番号データの更新」を実行すると入る
--   ※ MySQL 8.0 では、列を末尾に足すだけなら、表を作り直さずに一瞬で終わる（ALGORITHM=INSTANT）
-- =============================================================================
ALTER TABLE post_codes
    ADD COLUMN prefecture_kana   VARCHAR(20)   NOT NULL DEFAULT '' COMMENT '都道府県のフリガナ',
    ADD COLUMN municipality_kana VARCHAR(100)  NOT NULL DEFAULT '' COMMENT '市区町村のフリガナ',
    ADD COLUMN town_area_kana    VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '町域のフリガナ';
