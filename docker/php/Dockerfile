FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev libonig-dev unzip git \
    && docker-php-ext-install pdo_pgsql bcmath pcntl intl mbstring zip opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer
USER www-data
CMD ["php-fpm"]
