FROM php:8.4.7-fpm-alpine

# Install PHPize packages
RUN apk add --no-cache --virtual .phpize $PHPIZE_DEPS

# Install Source Packages
ENV SRC_DEPS="gmp-dev icu-dev"
RUN apk add --no-cache --virtual .source $SRC_DEPS

# Install Binary Packages
ENV BIN_DEPS="gmp git icu nginx"
RUN apk add --no-cache --virtual .binary $BIN_DEPS

# Install PHP Extensions
RUN pecl install redis

RUN docker-php-ext-enable redis

RUN docker-php-ext-install bcmath
RUN docker-php-ext-install gmp
RUN docker-php-ext-install opcache
RUN docker-php-ext-install pdo

# Delete PHPize packages
RUN apk del --no-network --no-cache --purge .phpize

# Delete Source packages
RUN apk del --no-network --no-cache --purge .source

# Remove files
RUN rm -rf /tmp/pear
RUN rm -rf ~/.pearrc
RUN rm -rf /var/cache/apk/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy files
WORKDIR /var/www/html
COPY . .
RUN mv nginx.conf /etc/nginx/http.d/default.conf

# Install project using Composer
RUN --mount=type=cache,target=/root/.composer composer install --no-interaction --optimize-autoloader --no-dev

# Change permissions
RUN chown -R www-data:www-data storage/

# Setup Process Manager
RUN echo "pm = ondemand" >> /usr/local/etc/php-fpm.d/zz-docker.conf
RUN echo "pm.process_idle_timeout = 10s" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Setup PHP
RUN mv php.ini /usr/local/etc/php/conf.d/

# Setup Opcache
RUN mv opcache.ini /usr/local/etc/php/conf.d/

VOLUME /var/www/html/storage/framework/cache/data

# Cache project and Start PHP-FPM and NGINX
CMD php artisan optimize; php artisan event:cache; php artisan view:cache; sh -c "php artisan queue:work &"; nginx -g "daemon off;"
