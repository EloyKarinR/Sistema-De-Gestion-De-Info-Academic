# ---- Etapa 1: compilar los assets de Vite (CSS/JS) ----
# node:22-bookworm-slim (Debian/glibc) en vez de alpine (musl) porque el
# proyecto fija binarios nativos de Rollup/Tailwind en su variante glibc
# (@rollup/rollup-linux-x64-gnu) — en Alpine no cargan y el build truena.
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---- Etapa 2: runtime (nginx + PHP-FPM) ----
FROM richarvey/nginx-php-fpm:3.1.6

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN sed -i 's/\r$//' /var/www/html/scripts/*.sh && chmod +x /var/www/html/scripts/*.sh

# Config de la imagen base
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV COMPOSER_ALLOW_SUPERUSER=1

# Config de Laravel
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

CMD ["/start.sh"]
