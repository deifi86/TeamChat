#!/bin/bash

set -e

# Configuration
BACKUP_DIR="/opt/backups/teamchat"
S3_BUCKET="s3://teamchat-backups"
RETENTION_DAYS=30
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Create backup directory
mkdir -p $BACKUP_DIR/{db,files,config}

log "Starting backup..."

# Database Backup
log "Backing up database..."
docker exec teamchat-mysql mysqldump \
    -u root \
    -p$MYSQL_ROOT_PASSWORD \
    --single-transaction \
    --routines \
    --triggers \
    teamchat > $BACKUP_DIR/db/teamchat_$TIMESTAMP.sql

gzip $BACKUP_DIR/db/teamchat_$TIMESTAMP.sql

# Files Backup (Storage)
log "Backing up storage files..."
docker cp teamchat-app:/var/www/html/storage/app/public $BACKUP_DIR/files/storage_$TIMESTAMP
tar -czf $BACKUP_DIR/files/storage_$TIMESTAMP.tar.gz -C $BACKUP_DIR/files storage_$TIMESTAMP
rm -rf $BACKUP_DIR/files/storage_$TIMESTAMP

# Config Backup
log "Backing up configuration..."
cp /opt/teamchat/.env $BACKUP_DIR/config/env_$TIMESTAMP
cp /opt/teamchat/docker-compose.prod.yml $BACKUP_DIR/config/docker-compose_$TIMESTAMP.yml

# Upload to S3 (optional)
if command -v aws &> /dev/null && [ -n "$S3_BUCKET" ]; then
    log "Uploading to S3..."
    aws s3 sync $BACKUP_DIR $S3_BUCKET --delete
fi

# Cleanup old backups
log "Cleaning up old backups..."
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -type d -empty -delete

log "Backup completed successfully!"

# Create backup report
cat << EOF > $BACKUP_DIR/last_backup.txt
Backup Report
=============
Timestamp: $TIMESTAMP
Database: teamchat_$TIMESTAMP.sql.gz
Storage: storage_$TIMESTAMP.tar.gz
Config: env_$TIMESTAMP, docker-compose_$TIMESTAMP.yml

Sizes:
$(du -sh $BACKUP_DIR/db/teamchat_$TIMESTAMP.sql.gz)
$(du -sh $BACKUP_DIR/files/storage_$TIMESTAMP.tar.gz)
EOF

cat $BACKUP_DIR/last_backup.txt
