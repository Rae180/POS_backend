#!/bin/bash
export PORT=${PORT:-80}
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default
php artisan config:cache
php artisan route:cache
php artisan migrate --force
service nginx start
php-fpm