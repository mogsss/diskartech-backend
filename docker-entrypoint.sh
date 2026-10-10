#!/bin/bash
set -e

# Ensure storage directories exist and have proper permissions
mkdir -p /var/www/html/storage/app/public/student/profile_pictures \
         /var/www/html/storage/app/public/student/school_id \
         /var/www/html/storage/app/public/student/coe \
         /var/www/html/storage/app/public/student/resume \
         /var/www/html/storage/app/public/employers/avatar \
         /var/www/html/storage/app/public/household/avatar || true

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Generate app storage symlink if not already created
php artisan storage:link || true

# Job posting requires this column; fail startup if its migration cannot complete.
php artisan migrate --path=database/migrations/2026_10_10_120000_add_salary_type_to_available_jobs_table.php --force --no-interaction

# Run database migrations automatically on deployment
php artisan migrate --force || true

# Online interview scheduling/closure depends on this field; fail startup if unavailable.
php artisan migrate --path=database/migrations/2026_10_10_160000_add_interview_ended_at_to_job_applications.php --force --no-interaction

# Cache Laravel configurations, routes, and views for maximum performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start background queue worker loop for automated background tasks and AI analysis
(while true; do php artisan queue:work --sleep=3 --tries=3 --max-time=3600; sleep 5; done) &

# Start Apache in the foreground
exec apache2-foreground
