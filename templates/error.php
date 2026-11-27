<?php
/**
 * エラーページ（404・405・500 など）
 *
 * @var string $message 利用者に表示するメッセージ（エラーの詳細は書かない）
 */
?>
<h1>エラーが発生しました</h1>
<p id="error-message" role="alert"><?=escape($message)?></p>
<p><a href="address">住所検索へ</a> ／ <a href="postcode">郵便番号検索へ</a></p>
