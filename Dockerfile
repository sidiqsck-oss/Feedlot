# Citra untuk Render.
#
# Sengaja memakai nginx + php-fpm, bukan runtime yang lebih ringkas: susunannya
# sama dengan cPanel, jadi apa yang jalan di trial ini jalan juga nanti di
# hosting. Trial yang tidak mewakili tujuan akhirnya tidak ada gunanya.
#
# PHP 8.3 dipilih karena itu yang paling umum tersedia di hosting Indonesia.
# Kalau cPanel nanti menawarkan 8.4, angka di sini tinggal diganti.

# ── Tahap 1: PHP beserta ekstensinya ────────────────────────────────
# Dipakai bersama oleh tahap pemasangan dependensi dan citra jalan. Dengan
# begini `composer install` memeriksa syarat ekstensi terhadap PHP yang
# benar-benar dipakai nanti — bukan terhadap citra composer yang ekstensinya
# berbeda dan menyembunyikan masalah sampai saat rilis.
FROM php:8.3-fpm-alpine AS php-dasar

RUN apk add --no-cache --virtual .bangun \
        icu-dev oniguruma-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd opcache \
    && apk del .bangun \
    && apk add --no-cache icu-libs libzip libpng freetype libjpeg-turbo

# ── Tahap 2: bangun aset ────────────────────────────────────────────
FROM node:22-alpine AS aset

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Tahap 3: pasang dependensi PHP ──────────────────────────────────
FROM php-dasar AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# composer.json ikut disalin duluan supaya lapisan ini tidak dibangun ulang
# tiap kali kode aplikasi berubah.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ── Tahap 4: citra jalan ────────────────────────────────────────────
FROM php-dasar

# gettext dipasang untuk envsubst: nginx tidak bisa membaca $PORT dari
# environment sendiri, padahal Render yang menentukan portnya.
RUN apk add --no-cache nginx supervisor gettext

WORKDIR /var/www

COPY --from=vendor /app /var/www
COPY --from=aset /app/public/build /var/www/public/build

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/aplikasi.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/masuk.sh /usr/local/bin/masuk.sh

RUN chmod +x /usr/local/bin/masuk.sh \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Render mengarahkan trafik ke PORT; nginx.conf membacanya lewat envsubst.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/masuk.sh"]
