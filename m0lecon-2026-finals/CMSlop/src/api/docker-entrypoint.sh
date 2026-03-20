#!/bin/bash
set -e

# Ensure upload directories exist and are writable by Apache
mkdir -p /var/www/html/uploads/avatars /var/www/html/uploads/media
chown -R www-data:www-data /var/www/html/uploads
chmod -R 755 /var/www/html/uploads

exec apache2-foreground
