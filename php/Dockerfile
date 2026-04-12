FROM php:8.3-fpm

# Install system dependencies (all in one layer for cache efficiency)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libicu-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype=/usr/include/ \
        --with-jpeg=/usr/include/ \
        --with-webp=/usr/include/ \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl \
        calendar

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# PHP tuning
RUN echo "upload_max_filesize = 64M"  >> /usr/local/etc/php/conf.d/bagisto.ini \
    && echo "post_max_size = 64M"     >> /usr/local/etc/php/conf.d/bagisto.ini \
    && echo "memory_limit = 512M"     >> /usr/local/etc/php/conf.d/bagisto.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/bagisto.ini

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
