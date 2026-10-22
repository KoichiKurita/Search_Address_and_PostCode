-- 郵便番号・住所検索 テーブル定義（MySQL 8 / MariaDB 用）
-- さくらのレンタルサーバでは phpMyAdmin の「SQL」タブから実行してください。

CREATE TABLE IF NOT EXISTS post_codes (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_code          CHAR(8)      NOT NULL COMMENT '郵便番号（ハイフンあり 例: 330-0063）',
    post_code_noHyphen CHAR(7)      NOT NULL COMMENT '郵便番号（ハイフンなし 例: 3300063）',
    prefecture         VARCHAR(10)  NOT NULL COMMENT '都道府県',
    municipality       VARCHAR(50)  NOT NULL COMMENT '市区町村',
    town_area          VARCHAR(255) NOT NULL DEFAULT '' COMMENT '町域',
    PRIMARY KEY (id),
    KEY idx_post_code (post_code),
    KEY idx_post_code_nohyphen (post_code_noHyphen),
    KEY idx_pref_muni (prefecture, municipality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
