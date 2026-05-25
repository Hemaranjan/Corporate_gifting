#!/bin/bash
# Reset script — removes all data and reinstalls fresh
echo "WARNING: This will delete all data and reinstall Bagisto."
read -p "Are you sure? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

cd /Users/hemaranjan/Documents/Corporate_gifting
docker compose down -v
docker compose up -d
echo "Waiting for containers..."
sleep 15
docker compose exec app bash /var/www/scripts/install.sh
