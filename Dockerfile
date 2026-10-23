# Los assets de Vite (public/build) se compilan localmente y se commitean
# al repo — correr "npm run build" (Vite + Tailwind) durante el build de la
# imagen consume más memoria de la que conviene gastar en el servidor.
# Para regenerarlos: npm run build && git add public/build

# Versión de parche fijada a propósito: con el tag flotante (8.4-cli-bookworm)
# un "docker compose up --build" reutiliza la imagen ya descargada y nunca
# recibe los parches de seguridad. 8.4.27 corrige PHP2Shell
# (CVE-2026-109618 / CVE-2026-109619, RCE sin autenticación).
FROM php:8.4.27-cli-bookworm

RUN apt-get update && apt-get install -y \
        libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd zip intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN sed -i 's/\r$//' docker-entrypoint.sh && chmod +x docker-entrypoint.sh

# Config de Laravel
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

EXPOSE 10000

CMD ["/var/www/html/docker-entrypoint.sh"]
