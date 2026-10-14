# ---- Etapa 1: baixar as bibliotecas (Composer) ----
FROM composer:2 AS dependencias
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---- Etapa 2: o sistema rodando no Apache + PHP ----
FROM php:8.3-apache

# Driver do MySQL para o PDO + reescrita de URL (/api → api.php) + cabeçalhos de segurança
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/loja.ini

WORKDIR /var/www/html
COPY public ./public
COPY src ./src
COPY scripts ./scripts
COPY --from=dependencias /app/vendor ./vendor

EXPOSE 80
