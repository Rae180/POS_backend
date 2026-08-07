#!/bin/bash
export PORT=${PORT:-80}
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=ProductSeeder --force
php artisan db:seed --class=CustomerSeeder --force
php artisan db:seed --class=SettingsSeeder --force
service nginx start
php-fpm