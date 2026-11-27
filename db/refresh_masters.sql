-- =============================================================================
-- post_codes の内容から、都道府県マスタ・市区町村マスタを作り直す
--   郵便番号データを取り込んだあとに毎回実行する
--     ローカル（Docker）: make seed-full が自動で実行する
--     本番: phpMyAdmin の「SQL」タブで、データの取り込みのあとに実行する
--   トランザクションの中で入れ替えるので、途中で一覧が空になった状態を画面に見せない
--   （BEGIN / COMMIT は MySQL と SQLite のどちらでも使える書き方。テストでも同じファイルを使う）
-- =============================================================================
BEGIN;

DELETE FROM municipalities;
DELETE FROM prefectures;

INSERT INTO prefectures (name, sort_order)
    SELECT prefecture, MIN(id)
    FROM post_codes
    GROUP BY prefecture;

INSERT INTO municipalities (prefecture_id, name, sort_order)
    SELECT p.id, pc.municipality, MIN(pc.id)
    FROM post_codes pc
    INNER JOIN prefectures p ON p.name = pc.prefecture
    GROUP BY p.id, pc.municipality;

COMMIT;
