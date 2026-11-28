-- 郵便番号・住所検索 テーブル定義（MySQL 8 / MariaDB 用）
-- さくらのレンタルサーバでは phpMyAdmin の「SQL」タブから実行してください。

CREATE TABLE IF NOT EXISTS post_codes (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_code          CHAR(8)      NOT NULL COMMENT '郵便番号（ハイフンあり 例: 330-0063）',
    post_code_noHyphen CHAR(7)      NOT NULL COMMENT '郵便番号（ハイフンなし 例: 3300063）',
    prefecture         VARCHAR(10)  NOT NULL COMMENT '都道府県',
    municipality       VARCHAR(50)  NOT NULL COMMENT '市区町村',
    -- 町域は、番地や通り名を列挙した長いものがある（最長: 鹿児島県鹿児島市「川上町（３６４９、…）」の297文字）
    -- VARCHAR の長さは「文字数」なので、余裕をもって1000文字にしている（変換スクリプトでも長さを事前に確認する）
    town_area          VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '町域',
    -- フリガナ（日本郵便のデータは全角カタカナ）。町域のフリガナは町域より長くなるので、2倍の長さにしている
    prefecture_kana    VARCHAR(20)   NOT NULL DEFAULT '' COMMENT '都道府県のフリガナ',
    municipality_kana  VARCHAR(100)  NOT NULL DEFAULT '' COMMENT '市区町村のフリガナ',
    town_area_kana     VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '町域のフリガナ',
    PRIMARY KEY (id),
    KEY idx_post_code (post_code),
    KEY idx_post_code_nohyphen (post_code_noHyphen),
    KEY idx_pref_muni (prefecture, municipality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- -----------------------------------------------------------------------------
-- 都道府県マスタ・市区町村マスタ
--   post_codes（約12万件）から、プルダウンに出す一覧だけを集計しておくテーブル。
--   画面を開くたびに post_codes 全体を GROUP BY しなくて済むようにする（性能改善）。
--   中身は db/refresh_masters.sql で post_codes から作り直す（郵便番号データを取り込むたびに実行）。
--   sort_order には post_codes の最小 id を入れる。日本郵便のデータは全国地方公共団体コード順に
--   並んでいるので、sort_order の順に並べると「北海道 → 沖縄県」の順になる。
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prefectures (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(10)  NOT NULL COMMENT '都道府県',
    sort_order INT UNSIGNED NOT NULL COMMENT '並び順（post_codes の最小 id）',
    PRIMARY KEY (id),
    UNIQUE KEY uk_prefectures_name (name),
    KEY idx_prefectures_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS municipalities (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefecture_id INT UNSIGNED NOT NULL,
    name          VARCHAR(50)  NOT NULL COMMENT '市区町村',
    sort_order    INT UNSIGNED NOT NULL COMMENT '並び順（post_codes の最小 id）',
    PRIMARY KEY (id),
    UNIQUE KEY uk_municipalities_pref_name (prefecture_id, name),
    KEY idx_municipalities_pref_sort (prefecture_id, sort_order),
    CONSTRAINT fk_municipalities_prefecture FOREIGN KEY (prefecture_id) REFERENCES prefectures (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
