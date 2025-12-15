#!/bin/bash

set -e

# Usage: ./restore.sh [backup_timestamp]
# Example: ./restore.sh 20241215_143000

BACKUP_DIR="/opt/backups/teamchat"
TIMESTAMP=$1

if [ -z "$TIMESTAMP" ]; then
    echo "Usage: $0 <backup_timestamp>"
    echo "Available backups:"
    ls -la $BACKUP_DIR/db/*.sql.gz | awk '{print $NF}' | xargs -n1 basename | sed 's/teamchat_//' | sed 's/.sql.gz//'
    exit 1
fi

DB_BACKUP="$BACKUP_DIR/db/teamchat_$TIMESTAMP.sql.gz"
STORAGE_BACKUP="$BACKUP_DIR/files/storage_$TIMESTAMP.tar.gz"

if [ ! -f "$DB_BACKUP" ]; then
    echo "Database backup not found: $DB_BACKUP"
    exit 1
fi

echo "⚠️  This will restore the database from backup: $TIMESTAMP"
echo "⚠️  All current data will be LOST!"
read -p "Are you sure? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

# Enable maintenance mode
echo "🔧 Enabling maintenance mode..."
docker exec teamchat-app php artisan down

# Restore database
echo "📊 Restoring database..."
gunzip -c $DB_BACKUP | docker exec -i teamchat-mysql mysql -u root -p$MYSQL_ROOT_PASSWORD teamchat

# Restore storage files (if backup exists)
if [ -f "$STORAGE_BACKUP" ]; then
    echo "📁 Restoring storage files..."
    tar -xzf $STORAGE_BACKUP -C /tmp
    docker cp /tmp/storage_$TIMESTAMP/. teamchat-app:/var/www/html/storage/app/public/
    rm -rf /tmp/storage_$TIMESTAMP
fi

# Clear caches
echo "🗑️ Clearing caches..."
docker exec teamchat-app php artisan cache:clear
docker exec teamchat-app php artisan config:cache

# Disable maintenance mode
echo "✅ Disabling maintenance mode..."
docker exec teamchat-app php artisan up

echo "✨ Restore completed!"
