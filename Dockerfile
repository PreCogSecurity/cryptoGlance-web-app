# cryptoGlance - self-hosted mining dashboard
# Runs the app on Apache + PHP 8.3 with the extensions the app requires.
FROM php:8.3-apache

# Required PHP extensions: sockets (miner API), curl (pool/wallet APIs),
# json is bundled. pdo is not needed.
RUN docker-php-ext-install sockets \
    && a2enmod rewrite headers

# Serve from the application root and allow .htaccess overrides.
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Install dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-interaction --no-progress --no-dev --prefer-dist \
    && apt-get purge -y git unzip \
    && rm -rf /var/lib/apt/lists/*

# Application source.
COPY . .

# Runtime state (settings, caches, logs) lives in a volume so it survives
# image upgrades. The web server user must own it.
RUN mkdir -p /var/www/html/user_data \
    && chown -R www-data:www-data /var/www/html/user_data

EXPOSE 80

# Basic container health check against the login page.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/ || exit 1
