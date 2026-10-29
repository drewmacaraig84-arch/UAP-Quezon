# ==============================================================================
# Multi-Stage Dockerfile for UAP Quezon Province Chapter (PHP Backend + React Frontend)
# Optimized for Railway, Render, Fly.io, or standard Docker deployments
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Build the React 18 + Vite Frontend
# ------------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder
WORKDIR /app/website

# Copy package descriptors first to leverage Docker layer caching
COPY website/package*.json ./
RUN npm install

# Copy frontend source and compile production bundle
COPY website/ ./
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 2: Production PHP + Apache Web Server
# ------------------------------------------------------------------------------
FROM php:8.2-apache

# Install system dependencies and PHP extensions (PDO, MySQL, GD, Zip)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd zip \
    && a2enmod rewrite headers \
    && a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork 2>/dev/null || true \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP upload limits, memory limits, and execution timeouts
RUN printf "upload_max_filesize = 64M\npost_max_size = 64M\nmemory_limit = 256M\nmax_execution_time = 300\nmax_input_time = 300\n" > /usr/local/etc/php/conf.d/uploads.ini

# Configure Apache VirtualHost with AllowOverride All, ServerName, and DirectoryIndex index.html
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "DirectoryIndex index.html index.php" >> /etc/apache2/apache2.conf \
    && sed -ri -e 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n    DirectoryIndex index.html index.php\n</Directory>\n' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

WORKDIR /var/www/html

# Copy the entire PHP application into the container
COPY . /var/www/html/

# Copy compiled React frontend assets over the web root (index.html, assets, logo)
COPY --from=frontend-builder /app/website/dist/ /var/www/html/

# Ensure includes/config.php exists and setup permissions & persistent seed backups
RUN if [ ! -f /var/www/html/includes/config.php ] && [ -f /var/www/html/includes/config.example.php ]; then \
        cp /var/www/html/includes/config.example.php /var/www/html/includes/config.php; \
    fi \
    && sed -i -e 's/\r$//' /var/www/html/docker-entrypoint.sh \
    && chmod 755 /var/www/html/docker-entrypoint.sh \
    && mkdir -p /var/www/html/uploads/qr_codes /var/www/html/uploads/avatars /var/www/html/uploads/members /var/www/html/uploads/sponsors /var/www/html/uploads/proofs /var/www/html/uploads/receipts /var/www/html/uploads/news /var/www/html/uploads/home_images /var/www/html/uploads/header_badges /var/www/html/receipts /var/www/html/public /var/www/html/seed_assets \
    && if [ -d /var/www/html/uploads ]; then cp -r /var/www/html/uploads /var/www/html/seed_assets/ 2>/dev/null || true; fi \
    && if [ -d /var/www/html/public ]; then cp -r /var/www/html/public /var/www/html/seed_assets/ 2>/dev/null || true; fi \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/receipts /var/www/html/seed_assets /var/www/html/public

# Expose default HTTP port
EXPOSE 80

# Run entrypoint script for dynamic Railway port binding & database setup
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
