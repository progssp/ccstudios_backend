FROM php:8.5-fpm

# install system dependencies and nginx
RUN apt-get update && apt-get install -y dos2unix nginx supervisor zip unzip git libpng-dev libjpeg-dev libfreetype6-dev libzip-dev && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo pdo_mysql gd zip

# get composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#set working dir
WORKDIR /var/www/html

COPY . /var/www/html

# install composer dependencies
RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p /var/log/supervisor /var/run /var/log/nginx && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public /var/lib/nginx /var/log/nginx

# copy nginx conf
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisor.conf /etc/supervisor/conf.d/supervisord.conf

COPY start.sh /usr/local/bin/start.sh
RUN dos2unix /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# expose port 80
EXPOSE 80

ENTRYPOINT ["sh","/usr/local/bin/start.sh"]