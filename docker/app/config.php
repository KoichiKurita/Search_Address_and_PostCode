<?php
    declare(strict_types=1);

    /**
     * Docker 開発環境用の設定ファイル
     * docker-compose.yml で /var/www/html/config.php に重ねてマウントされる。
     * 接続情報は docker-compose.yml の environment（.env）から読み込む。
     */

    define('DB_NAME', sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'db',
        getenv('DB_DATABASE') ?: 'postcode'
    ));
    define('DB_USER', getenv('DB_USERNAME') ?: 'app');
    define('DB_PASS', getenv('DB_PASSWORD') ?: '');
