FROM php:8.3-apache

# pdo_sqlite pro PDO/SQLite vrstvu (App\Core\Database)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot na public/, front controller přes FallbackResource
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
