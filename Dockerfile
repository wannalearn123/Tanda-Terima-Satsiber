# Satu image: PHP 8.3 CLI + gd (wajib untuk embed gambar di PDF) + pdo_sqlite bawaan.
# Dependensi PHP di-build dari composer.lock agar reproducible (supply-chain pin).
FROM php:8.3-cli-alpine

RUN apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd \
 && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress \
 && rm -rf /tmp/* /root/.composer /root/.cache
COPY . .
RUN mkdir -p storage/pdf/tmp storage/logs storage/tandatangan database \
 && chmod 0755 storage/pdf/tmp storage/logs storage/tandatangan database

WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8090", "public/router.php"]
