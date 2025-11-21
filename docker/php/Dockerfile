FROM php:8.5-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Configuración de desarrollo
COPY php.ini /usr/local/etc/php/php.ini

# Exponer directorio de trabajo
WORKDIR /var/www/html
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
