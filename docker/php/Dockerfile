# 本番（さくらのレンタルサーバ）と同じ PHP 8.3 を使う
FROM php:8.3-apache

# PDO(MySQL) と OPcache を有効化
RUN docker-php-ext-install pdo_mysql opcache

# .htaccess で使う Apache のモジュールを有効化（ヘッダーの付与・リダイレクト）
RUN a2enmod headers rewrite

# 開発用の php.ini
COPY php.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
