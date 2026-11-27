-- =============================================================================
-- 002: 都道府県マスタ・市区町村マスタを追加する（性能改善）
--   既存の DB（本番）に1回だけ実行する。新しく作る DB は db/schema.sql に反映済み
--   実行したあと、続けて db/refresh_masters.sql を実行して中身を作ること
-- =============================================================================
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
