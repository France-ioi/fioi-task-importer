FROM php:5.6-fpm

# Use archived Debian Stretch repositories
RUN sed -i 's/http:\/\/deb.debian.org\/debian/http:\/\/archive.debian.org\/debian/g' /etc/apt/sources.list && \
    sed -i 's/http:\/\/security.debian.org\/debian-security/http:\/\/archive.debian.org\/debian-security/g' /etc/apt/sources.list && \
    sed -i '/stretch-updates/d' /etc/apt/sources.list && \
    apt-get -o Acquire::Check-Valid-Until=false update --allow-unauthenticated

# Install system dependencies and PHP extensions
RUN apt-get update
RUN apt-get install -y \
        git \
        unzip \
        curl \
        subversion \
        php5.6 \
        libxml2-dev \
        libcurl4-openssl-dev \
        libzip-dev \
        zlib1g-dev \
        libssl-dev \
        pkg-config \
        libapr1-dev \
        libaprutil1-dev \
        build-essential \
        apt-transport-https \
        lsb-release \
        gnupg
RUN docker-php-ext-install \
        xml \
        mbstring \
        curl \
        mysql \
        pdo_mysql \
        zip

RUN echo "date.timezone=UTC" > /usr/local/etc/php/conf.d/timezone.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js
RUN curl -s https://deb.nodesource.com/gpgkey/nodesource.gpg.key | gpg --dearmor | tee /usr/share/keyrings/nodesource.gpg >/dev/null \
    && echo 'deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_14.x stretch main' > /etc/apt/sources.list.d/nodesource.list \
    && echo 'deb-src [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_14.x stretch main' >> /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs

# Install PHP-SVN extension
RUN apt-get install libsvn-dev \
    && pecl install svn \
    && echo "extension=svn.so" > /usr/local/etc/php/conf.d/svn.ini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/fioi-task-importer

# Create entrypoint
COPY ./php-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# Give write permissions
ARG UID
ARG GID

RUN groupadd -g $GID wwwgroup && \
    useradd -u $UID -g $GID -m wwwuser
USER wwwuser

# Expose port 9000 and start PHP-FPM
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
