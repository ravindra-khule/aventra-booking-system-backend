# PHP Backend Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    pkg-config \
    zip \
    unzip \
    curl \
    wget \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    zip \
    bcmath \
    opcache \
    xml \
    mbstring \
    curl \
    iconv

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache - set DocumentRoot to public directory and enable .htaccess
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && sed -i '/<\/VirtualHost>/i \
        <Directory /var/www/html/public>\n            Options Indexes FollowSymLinks\n            AllowOverride All\n            Require all granted\n        </Directory>' /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create storage directory for logs
RUN mkdir -p storage/logs && chmod 777 storage/logs

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/storage

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
