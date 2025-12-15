#!/bin/bash

set -e

echo "🚀 Starting deployment..."

# Variables
DEPLOY_DIR="/opt/teamchat"
BACKUP_DIR="/opt/backups/teamchat"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

cd $DEPLOY_DIR

# Create backup
echo "📦 Creating backup..."
mkdir -p $BACKUP_DIR
docker exec teamchat-mysql mysqldump -u root -p$MYSQL_ROOT_PASSWORD teamchat > $BACKUP_DIR/db_$TIMESTAMP.sql
gzip $BACKUP_DIR/db_$TIMESTAMP.sql

# Keep only last 7 backups
ls -t $BACKUP_DIR/db_*.sql.gz | tail -n +8 | xargs -r rm

# Pull latest images
echo "📥 Pulling latest images..."
docker compose -f docker-compose.prod.yml pull

# Maintenance mode
echo "🔧 Enabling maintenance mode..."
docker exec teamchat-app php artisan down --render="errors::503" --retry=60

# Update containers
echo "🔄 Updating containers..."
docker compose -f docker-compose.prod.yml up -d --remove-orphans

# Wait for containers to be healthy
echo "⏳ Waiting for containers..."
sleep 10

# Run migrations
echo "📊 Running migrations..."
docker exec teamchat-app php artisan migrate --force

# Clear and rebuild caches
echo "🗑️ Clearing caches..."
docker exec teamchat-app php artisan config:cache
docker exec teamchat-app php artisan route:cache
docker exec teamchat-app php artisan view:cache
docker exec teamchat-app php artisan event:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
docker exec teamchat-app php artisan queue:restart

# Disable maintenance mode
echo "✅ Disabling maintenance mode..."
docker exec teamchat-app php artisan up

# Cleanup
echo "🧹 Cleaning up..."
docker system prune -f

echo "✨ Deployment completed successfully!"
