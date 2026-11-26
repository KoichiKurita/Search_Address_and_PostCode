# 本番（さくらのクラウド）と同じ PHP 8.3 を使う
FROM php:8.3-apache

# PDO(MySQL) と OPcache を有効化
RUN docker-php-ext-install pdo_mysql opcache

# 開発用の php.ini
COPY php.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
