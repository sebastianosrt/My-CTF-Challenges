FROM php:7.4-apache

RUN a2enmod rewrite headers

RUN apt-get update && apt-get install -y \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY apache.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json .
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN find /var/www/html -type f -name "*.php" -exec chmod 444 {} \;

EXPOSE 80
