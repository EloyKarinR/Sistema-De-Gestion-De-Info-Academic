# Los assets de Vite (public/build) se compilan localmente y se commitean
# al repo — el plan gratuito de Render no tiene memoria suficiente para
# correr "npm run build" (Vite + Tailwind) durante el build de la imagen.
# Para regenerarlos: npm run build && git add public/build

FROM richarvey/nginx-php-fpm:3.1.6

COPY . .

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
