# =========================
# Builder Stage
# =========================
FROM php:8.3-fpm AS builder

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libpq-dev \
    default-libmysqlclient-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# Allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy composer files first
COPY composer.json composer.lock ./

# Install dependencies without scripts
RUN composer install \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader

# Copy application source
COPY . .

# Install Redis Messenger without scripts
RUN composer require symfony/redis-messenger \
    --no-interaction \
    --no-scripts

# Optimize Composer
RUN composer install \
    --no-interaction \
    --optimize-autoloader \
    --no-ansi

# Warm Symfony cache safely
RUN APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup --no-debug || true


# =========================
# Runtime Stage
# =========================
FROM php:8.3-fpm AS runtime

# Set working directory
WORKDIR /app

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    build-essential \
    default-libmysqlclient-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# Copy app from builder
COPY --from=builder /app /app

# Copy PHP extensions/config
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Set permissions
RUN mkdir -p /app/var && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var

# Configure nginx
COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* \
    /etc/nginx/sites-enabled \
    /etc/nginx/sites-available

COPY nginx.conf /etc/nginx/conf.d/symfony.conf

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Healthcheck
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port
EXPOSE 80

# Start container
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]