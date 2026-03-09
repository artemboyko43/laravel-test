#!/bin/sh

set -e

echo "Starting Laravel application setup..."

# Update .env file for Docker environment
if [ -f .env ]; then
    echo "Updating .env for Docker environment..."
    sed -i 's/^DB_HOST=.*/DB_HOST=db/' .env || true
    sed -i 's/^DB_USERNAME=.*/DB_USERNAME=laravel/' .env || true
    sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=root/' .env || true
    sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' .env || true
fi

# Install composer dependencies (only in dev)
if [ "$APP_ENV" != "production" ] && [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Generate application key if not exists
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force || true
fi

# Wait for database to be ready
echo "Waiting for database to be ready..."
until php -r "try { \$pdo = new PDO('mysql:host=db;port=3306', 'laravel', 'root'); echo 'Database is ready!\n'; exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

# Run migrations (only in dev, production should run manually)
if [ "$APP_ENV" != "production" ]; then
    echo "Running migrations..."
    php artisan migrate --force || true
fi

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Clear and cache config
php artisan config:clear || true

echo "Starting PHP-FPM..."
exec php-fpm
