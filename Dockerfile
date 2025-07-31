FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy files to Apache web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80
