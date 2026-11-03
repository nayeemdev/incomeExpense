FROM serversideup/php:8.5-fpm-nginx

USER root
RUN install-php-extensions bcmath intl

USER www-data
WORKDIR /var/www/html

COPY --chown=www-data:www-data api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY --chown=www-data:www-data api/ ./
RUN composer dump-autoload --optimize --no-dev
