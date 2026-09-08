# Satu image: PHP 8.3 CLI + gd (wajib untuk embed gambar di PDF) + pdo_sqlite bawaan.
FROM php:8.3-cli-alpine

RUN apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd \
 && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8090", "public/router.php"]
