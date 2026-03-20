FROM php:7.4-apache

RUN a2enmod rewrite headers

RUN apt-get update && apt-get install -y \
    libzip-dev \
    curl \
    gcc \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .

RUN mkdir -p /var/www/html/uploads/avatars \
    && mkdir -p /var/www/html/uploads/media \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

RUN find /var/www/html -type f -name "*.php" -exec chmod 444 {} \;

COPY readflag.c /tmp/readflag.c
RUN gcc -o /readflag /tmp/readflag.c \
    && chmod 4755 /readflag \
    && rm /tmp/readflag.c


ARG FLAG
RUN echo $FLAG > /flag.txt \
    && chown root:root /flag.txt \
    && chmod 400 /flag.txt

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
