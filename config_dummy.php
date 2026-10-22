<?php
    declare(strict_types=1);

    /**
     * Webアプリ実践課題
     * 郵便番号・住所検索(config_dummy.php)
     * 作成者：リンクス新越谷 栗田幸一
     * 作成日：2023.5.25
     */

    /**
     * ハイフンについて
     * https://qiita.com/ryounagaoka/items/4cf5191d1a2763667add
     * https://ja.wikipedia.org/wiki/%E3%83%80%E3%83%83%E3%82%B7%E3%83%A5_%28%E8%A8%98%E5%8F%B7%29
     * http://ash.jp/code/unitbl21.htm
     * https://hydrocul.github.io/wiki/blog/2014/1101-hyphen-minus-wave-tilde.html
     */

    define("DB_NAME", null);    // 実際に使用するDBのURL
    define("DB_USER", null);    // 実際に使用するDBのユーザID
    define("DB_PASS", null);    // 実際に使用するDBのパスワード

    /**
     * ハイフンのパターン文字列
     */
    define('PATTERN_HYPHEN', '/[\x{30FC}\x{2010}-\x{2015}\x{2212}\x{FF70}\x{2053}\x{2E3A}\x{301C}\x{3030}\x{2500}\x{4E00}\x{FE63}\x{FF0D}\x{2043}\x{FE58}\x{23AF}\x{208B}\x{007E}]/u');
?>