FROM php:8.2-fpm-alpine

ARG UID=1000
ARG GID=1000

ENV UID=${UID}
ENV GID=${GID}

# Instala dependências do sistema
RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libpq \
    libpq-dev \
    $PHPIZE_DEPS \
    git \
    curl \
    unzip \
    zip

# Configura extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Instala extensões PHP
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    bcmath \
    exif \
    gd \
    opcache \
    pcntl \
    zip

# Instala Redis via PECL (mais estável)
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Configura usuário e permissões
RUN delgroup dialout 2>/dev/null || true
RUN addgroup -g ${GID} --system laravel
RUN adduser -G laravel --system -D -s /bin/sh -u ${UID} laravel

# Configura PHP-FPM
RUN sed -i "s/user = www-data/user = laravel/g" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i "s/group = www-data/group = laravel/g" /usr/local/etc/php-fpm.d/www.conf && \
    echo "php_admin_flag[log_errors] = on" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Cria diretório da aplicação e configura permissões
RUN mkdir -p /var/www/html && \
    chown -R laravel:laravel /var/www/html && \
    chmod -R 755 /var/www/html

WORKDIR /var/www/html

USER laravel

# Configuração de memória para o PHP
ENV PHP_MEMORY_LIMIT=256M
ENV PHP_UPLOAD_MAX_FILESIZE=64M
ENV PHP_POST_MAX_SIZE=64M

# Comando padrão
CMD ["php-fpm", "-y", "/usr/local/etc/php-fpm.conf", "-R"]
