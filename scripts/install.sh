#!/bin/bash
set -e

echo "============================================"
echo "  Bagisto Corporate Gifting Platform Setup  "
echo "============================================"

BAGISTO_DIR="/var/www/html"

# Check if Bagisto is already installed
if [ -f "$BAGISTO_DIR/artisan" ]; then
    echo "[SKIP] Bagisto already installed."
else
    echo "[1/6] Downloading Bagisto..."
    composer create-project bagisto/bagisto:^2.2 $BAGISTO_DIR --no-interaction --prefer-dist
fi

cd $BAGISTO_DIR

echo "[2/6] Configuring environment..."
cat > .env << 'ENVEOF'
APP_NAME="Corporate Gifting Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bagisto_corporate
DB_USERNAME=bagisto
DB_PASSWORD=bagisto123

CACHE_DRIVER=redis
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
ENVEOF

echo "[3/6] Generating app key..."
php artisan key:generate --force

echo "[4/6] Running database migrations..."
php artisan migrate:fresh --force

echo "[5/6] Seeding default Bagisto data..."
php artisan db:seed --force

echo "[6/6] Seeding sample corporate gifting data..."
php /var/www/scripts/seed_data.php

echo ""
echo "[DONE] Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo ""
echo "============================================"
echo "  Installation Complete!"
echo "  Storefront : http://localhost:8080"
echo "  Admin Panel: http://localhost:8080/admin"
echo "  Admin User : admin@corporate.com"
echo "  Admin Pass : Admin@123"
echo "============================================"
