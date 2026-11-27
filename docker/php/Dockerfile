# 本番（さくらのレンタルサーバ）と同じ PHP 8.3 を使う
FROM php:8.3-apache

# PDO(MySQL) と OPcache を有効化
RUN docker-php-ext-install pdo_mysql opcache

# .htaccess で使う Apache のモジュールを有効化（ヘッダーの付与・リダイレクト）
RUN a2enmod headers rewrite

# Composer（PHP のパッケージ管理）と、Composer がパッケージを展開するのに使う unzip・git を入れる
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 開発用の php.ini
COPY php.ini /usr/local/etc/php/conf.d/zz-dev.ini

# 公開ルートを public/ にする（本番と同じく、src/ や config.php などは URL から見えない）
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html
