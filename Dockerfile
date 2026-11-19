FROM php:8.2-apache

# Install PostgreSQL client dev headers so PHP can build the pdo_pgsql extension
RUN apt-get update && apt-get install -y libpq-dev curl && rm -rf /var/lib/apt/lists/*

# Install PostgreSQL PDO extension so PHP can talk to Postgres
RUN docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Point Apache document root at the app folder (index.php is at the root)
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy application source into the container
COPY src/ /var/www/html/
