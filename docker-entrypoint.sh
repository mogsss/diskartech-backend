#!/bin/bash
set -e

# Generate app storage symlink if not already created
php artisan storage:link || true

# Run database migrations automatically on deployment
php artisan migrate --force || true

# Cache Laravel configurations, routes, and views for maximum performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start Apache in the foreground
exec apache2-foreground
