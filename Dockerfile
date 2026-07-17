# Los assets de Vite (public/build) se compilan localmente y se commitean
# al repo — el plan gratuito de Render no tiene memoria suficiente para
# correr "npm run build" (Vite + Tailwind) durante el build de la imagen.
# Para regenerarlos: npm run build && git add public/build

FROM richarvey/nginx-php-fpm:3.1.6

COPY . .

# Composer corre aquí, en el build, para garantizar que vendor/ exista en la
# imagen sin depender de que el script de arranque se ejecute correctamente.
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

RUN sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh && chmod +x /var/www/html/docker-entrypoint.sh

# Config de la imagen base
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV REAL_IP_HEADER=1

# Config de Laravel
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

CMD ["/var/www/html/docker-entrypoint.sh"]
