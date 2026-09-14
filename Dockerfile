# ==============================================================================
# STAGE 1: Base PHP image with required system libraries & PHP extensions
# ==============================================================================
FROM php:8.3-fpm-alpine AS base

# Install system dependencies and runtime tools
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    su-exec \
    shadow \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Install redis extension via pecl
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configure OPcache for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Configure PHP-FPM tuning
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 25'; \
    echo 'pm.start_servers = 5'; \
    echo 'pm.min_spare_servers = 3'; \
    echo 'pm.max_spare_servers = 10'; \
    echo 'pm.max_requests = 500'; \
    echo 'pm.status_path = /status'; \
    echo 'ping.path = /ping'; \
} > /usr/local/etc/php-fpm.d/zz-docker-tuning.conf

WORKDIR /var/www

# ==============================================================================
# STAGE 2: Composer vendor installation
# ==============================================================================
FROM composer:2.7 AS composer

WORKDIR /var/www

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==============================================================================
# STAGE 3: Node.js frontend assets build
# ==============================================================================
FROM node:20-alpine AS node

WORKDIR /var/www

COPY package.json package-lock.json* ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build

# ==============================================================================
# STAGE 4: Final Production Image
# ==============================================================================
FROM base AS production

WORKDIR /var/www

# Copy application source code
COPY . /var/www

# Copy production vendor from composer stage
COPY --from=composer /var/www/vendor /var/www/vendor

# Copy compiled frontend assets from node stage
COPY --from=node /var/www/public/build /var/www/public/build

# Copy entrypoint script and ensure executable
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ensure storage and bootstrap/cache directories exist and have proper permissions
RUN mkdir -p \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
