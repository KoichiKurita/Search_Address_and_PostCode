-- =============================================================================
-- 004: 事業所の個別郵便番号のテーブルを追加する（#36）
--   既存の DB（本番）に1回だけ実行する。新しく作る DB は db/schema.sql に反映済み
--   phpMyAdmin の「SQL」タブ、または mysql コマンドで実行する
--
--   ※ アプリをデプロイする「前」に実行すること（新しいアプリは、このテーブルがあることを前提に検索する）
--     テーブルを足すだけなので、先に実行しても今のアプリはそのまま動く
--   ※ 実行直後は空。GitHub Actions の「郵便番号データの更新」を実行すると入る
-- =============================================================================
CREATE TABLE IF NOT EXISTS offices (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_code          CHAR(8)      NOT NULL COMMENT '郵便番号（ハイフンあり）',
    post_code_noHyphen CHAR(7)      NOT NULL COMMENT '郵便番号（ハイフンなし）',
    name               VARCHAR(100) NOT NULL COMMENT '事業所名',
    name_kana          VARCHAR(100) NOT NULL DEFAULT '' COMMENT '事業所名のフリガナ（全角カタカナ）',
    prefecture         VARCHAR(10)  NOT NULL COMMENT '都道府県',
    municipality       VARCHAR(50)  NOT NULL COMMENT '市区町村',
    town_area          VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '町域',
    street             VARCHAR(100) NOT NULL DEFAULT '' COMMENT '小字・丁目・番地など',
    is_po_box          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1: 私書箱 / 0: 大口事業所',
    PRIMARY KEY (id),
    KEY idx_offices_post_code_nohyphen (post_code_noHyphen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
