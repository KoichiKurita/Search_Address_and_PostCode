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

    /**
     * ハイフンのパターン文字列（config_dummy.php と同じもの）
     */
    define('PATTERN_HYPHEN', '/[\x{30FC}\x{2010}-\x{2015}\x{2212}\x{FF70}\x{2053}\x{2E3A}\x{301C}\x{3030}\x{2500}\x{4E00}\x{FE63}\x{FF0D}\x{2043}\x{FE58}\x{23AF}\x{208B}\x{007E}]/u');
