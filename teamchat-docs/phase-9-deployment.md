# Phase 9: Deployment (Woche 21-22)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Docker-Produktionskonfiguration
- CI/CD Pipeline mit GitHub Actions
- SSL/TLS Konfiguration
- Backup-Strategie
- Monitoring Setup
- Dokumentation für Server-Deployment

---

## 9.1 Docker Production Setup [INFRA]

### 9.1.1 Production Dockerfile für Backend
- [ ] **Erledigt**

→ *Abhängig von Phase 8 abgeschlossen*

**Datei:** `backend/Dockerfile`
```dockerfile
# Build Stage
FROM composer:2.6 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# Production Stage
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    redis

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        intl \
        zip \
        mbstring \
        opcache \
        pcntl \
        bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# PHP Configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Nginx Configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor Configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Application
WORKDIR /var/www/html
COPY --from=composer /app /var/www/html
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

---

### 9.1.2 PHP Konfiguration
- [ ] **Erledigt**

**Datei:** `backend/docker/php/php.ini`
```ini
[PHP]
; Limits
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 64M
upload_max_filesize = 50M
max_file_uploads = 20

; Error Handling
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Session
session.gc_maxlifetime = 7200
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict

; Timezone
date.timezone = Europe/Berlin

; Security
expose_php = Off
```

**Datei:** `backend/docker/php/opcache.ini`
```ini
[opcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

---

### 9.1.3 Nginx Konfiguration
- [ ] **Erledigt**

**Datei:** `backend/docker/nginx/nginx.conf`
```nginx
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    include /etc/nginx/http.d/*.conf;
}
```

**Datei:** `backend/docker/nginx/default.conf`
```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    charset utf-8;

    # Logs
    access_log /var/log/nginx/teamchat-access.log;
    error_log /var/log/nginx/teamchat-error.log;

    # Health Check Endpoint
    location /health {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Static Files
    location ~* \.(jpg|jpeg|gif|png|webp|svg|ico|css|js|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive files
    location ~* (composer\.json|composer\.lock|\.env|artisan) {
        deny all;
    }
}
```

---

### 9.1.4 Supervisor Konfiguration
- [ ] **Erledigt**

**Datei:** `backend/docker/supervisor/supervisord.conf`
```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/php-fpm.log
stderr_logfile=/var/log/supervisor/php-fpm-error.log

[program:nginx]
command=/usr/sbin/nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/nginx.log
stderr_logfile=/var/log/supervisor/nginx-error.log

[program:laravel-queue]
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/log/supervisor/queue.log
stderr_logfile=/var/log/supervisor/queue-error.log

[program:laravel-reverb]
command=php /var/www/html/artisan reverb:start
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/reverb.log
stderr_logfile=/var/log/supervisor/reverb-error.log

[program:laravel-scheduler]
command=sh -c "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction & sleep 60; done"
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/scheduler.log
stderr_logfile=/var/log/supervisor/scheduler-error.log
```

---

### 9.1.5 Docker Compose Production
- [ ] **Erledigt**

**Datei:** `docker-compose.prod.yml`
```yaml
version: '3.8'

services:
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: teamchat-app
    restart: unless-stopped
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    env_file:
      - ./backend/.env.production
    volumes:
      - app-storage:/var/www/html/storage/app/public
      - app-logs:/var/www/html/storage/logs
    networks:
      - teamchat-network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.teamchat-api.rule=Host(`api.teamchat.example.com`)"
      - "traefik.http.routers.teamchat-api.entrypoints=websecure"
      - "traefik.http.routers.teamchat-api.tls.certresolver=letsencrypt"
      - "traefik.http.services.teamchat-api.loadbalancer.server.port=80"

  mysql:
    image: mysql:8.0
    container_name: teamchat-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: teamchat
      MYSQL_USER: teamchat
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf
    networks:
      - teamchat-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: teamchat-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis-data:/data
    networks:
      - teamchat-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  traefik:
    image: traefik:v2.10
    container_name: teamchat-traefik
    restart: unless-stopped
    command:
      - "--api.dashboard=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--certificatesresolvers.letsencrypt.acme.email=${ACME_EMAIL}"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik-certs:/letsencrypt
    networks:
      - teamchat-network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.traefik.rule=Host(`traefik.teamchat.example.com`)"
      - "traefik.http.routers.traefik.service=api@internal"
      - "traefik.http.routers.traefik.middlewares=auth"
      - "traefik.http.middlewares.auth.basicauth.users=${TRAEFIK_USERS}"

volumes:
  mysql-data:
  redis-data:
  app-storage:
  app-logs:
  traefik-certs:

networks:
  teamchat-network:
    driver: bridge
```

---

### 9.1.6 MySQL Optimierung
- [ ] **Erledigt**

**Datei:** `docker/mysql/my.cnf`
```ini
[mysqld]
# Performance
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connections
max_connections = 200
wait_timeout = 600
interactive_timeout = 600

# Query Cache (disabled in MySQL 8, use ProxySQL if needed)
# query_cache_type = 0

# Slow Query Log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Security
local_infile = 0

[client]
default-character-set = utf8mb4
```

---

## 9.2 Environment Configuration [INFRA]

### 9.2.1 Production Environment File
- [ ] **Erledigt**

**Datei:** `backend/.env.production.example`
```env
APP_NAME=TeamChat
APP_ENV=production
APP_KEY=base64:GENERATE_NEW_KEY
APP_DEBUG=false
APP_URL=https://api.teamchat.example.com

# Cipher Key für Nachrichtenverschlüsselung
APP_CIPHER_KEY=base64:GENERATE_NEW_KEY

LOG_CHANNEL=daily
LOG_LEVEL=warning

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=teamchat
DB_USERNAME=teamchat
DB_PASSWORD=SECURE_PASSWORD

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=SECURE_PASSWORD
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=redis

# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb WebSocket
REVERB_APP_ID=teamchat
REVERB_APP_KEY=GENERATE_SECURE_KEY
REVERB_APP_SECRET=GENERATE_SECURE_SECRET
REVERB_HOST=ws.teamchat.example.com
REVERB_PORT=443
REVERB_SCHEME=https

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@teamchat.example.com
MAIL_FROM_NAME="${APP_NAME}"

# TinyPNG
TINYPNG_API_KEY=YOUR_API_KEY

# Filesystem
FILESYSTEM_DISK=local

# CORS
CORS_ALLOWED_ORIGINS=https://teamchat.example.com,https://app.teamchat.example.com
```

---

### 9.2.2 Key Generation Script
- [ ] **Erledigt**

**Datei:** `scripts/generate-keys.sh`
```bash
#!/bin/bash

echo "Generating Laravel APP_KEY..."
APP_KEY=$(openssl rand -base64 32)
echo "APP_KEY=base64:$APP_KEY"

echo ""
echo "Generating APP_CIPHER_KEY..."
CIPHER_KEY=$(openssl rand -base64 32)
echo "APP_CIPHER_KEY=base64:$CIPHER_KEY"

echo ""
echo "Generating REVERB_APP_KEY..."
REVERB_KEY=$(openssl rand -hex 32)
echo "REVERB_APP_KEY=$REVERB_KEY"

echo ""
echo "Generating REVERB_APP_SECRET..."
REVERB_SECRET=$(openssl rand -hex 32)
echo "REVERB_APP_SECRET=$REVERB_SECRET"

echo ""
echo "Generating MySQL Password..."
MYSQL_PWD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
echo "MYSQL_PASSWORD=$MYSQL_PWD"

echo ""
echo "Generating Redis Password..."
REDIS_PWD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
echo "REDIS_PASSWORD=$REDIS_PWD"
```

---

## 9.3 CI/CD Pipeline [INFRA]

### 9.3.1 GitHub Actions Workflow
- [ ] **Erledigt**

**Datei:** `.github/workflows/ci.yml`
```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'

jobs:
  # Backend Tests
  backend-tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: teamchat_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, pdo_mysql, redis, gd, intl, zip
          coverage: xdebug

      - name: Get Composer Cache Directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT
        working-directory: backend

      - name: Cache Composer dependencies
        uses: actions/cache@v3
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('backend/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress
        working-directory: backend

      - name: Copy .env
        run: cp .env.example .env
        working-directory: backend

      - name: Generate key
        run: php artisan key:generate
        working-directory: backend

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --memory-limit=2G
        working-directory: backend
        continue-on-error: true

      - name: Run Tests
        run: php artisan test --coverage-clover coverage.xml
        working-directory: backend
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: teamchat_test
          DB_USERNAME: root
          DB_PASSWORD: root
          REDIS_HOST: 127.0.0.1

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: backend/coverage.xml
          flags: backend

  # Frontend Tests
  frontend-tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        run: npm ci
        working-directory: frontend

      - name: Run ESLint
        run: npm run lint
        working-directory: frontend

      - name: Run Type Check
        run: npm run type-check
        working-directory: frontend
        continue-on-error: true

      - name: Run Tests
        run: npm run test:unit -- --coverage
        working-directory: frontend

      - name: Build
        run: npm run build
        working-directory: frontend

  # Build Docker Image
  build:
    needs: [backend-tests, frontend-tests]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: ./backend
          push: true
          tags: |
            ghcr.io/${{ github.repository }}/api:latest
            ghcr.io/${{ github.repository }}/api:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  # Deploy to Production
  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment: production

    steps:
      - uses: actions/checkout@v4

      - name: Deploy to Server
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_KEY }}
          script: |
            cd /opt/teamchat
            docker compose -f docker-compose.prod.yml pull
            docker compose -f docker-compose.prod.yml up -d --remove-orphans
            docker exec teamchat-app php artisan migrate --force
            docker exec teamchat-app php artisan config:cache
            docker exec teamchat-app php artisan route:cache
            docker exec teamchat-app php artisan view:cache
            docker system prune -f
```

---

### 9.3.2 Deployment Script
- [ ] **Erledigt**

**Datei:** `scripts/deploy.sh`
```bash
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
```

---

## 9.4 Backup & Recovery [INFRA]

### 9.4.1 Automated Backup Script
- [ ] **Erledigt**

**Datei:** `scripts/backup.sh`
```bash
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
```

---

### 9.4.2 Recovery Script
- [ ] **Erledigt**

**Datei:** `scripts/restore.sh`
```bash
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
```

---

### 9.4.3 Backup Cron Job
- [ ] **Erledigt**

**Datei:** `/etc/cron.d/teamchat-backup`
```cron
# TeamChat Backup Schedule
# Daily backup at 3:00 AM
0 3 * * * root /opt/teamchat/scripts/backup.sh >> /var/log/teamchat-backup.log 2>&1

# Hourly database backup (for critical systems)
# 0 * * * * root /opt/teamchat/scripts/backup-db-only.sh >> /var/log/teamchat-backup.log 2>&1
```

---

## 9.5 Monitoring [INFRA]

### 9.5.1 Health Check Endpoint
- [ ] **Erledigt**

**Datei:** `backend/routes/api.php` ergänzen:
```php
// Health Check (ohne Auth)
Route::get('/health', function () {
    $checks = [];
    $healthy = true;

    // Database
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error: ' . $e->getMessage();
        $healthy = false;
    }

    // Redis
    try {
        Redis::ping();
        $checks['redis'] = 'ok';
    } catch (\Exception $e) {
        $checks['redis'] = 'error: ' . $e->getMessage();
        $healthy = false;
    }

    // Storage
    try {
        Storage::disk('public')->exists('.');
        $checks['storage'] = 'ok';
    } catch (\Exception $e) {
        $checks['storage'] = 'error: ' . $e->getMessage();
        $healthy = false;
    }

    // Queue
    try {
        $queueSize = Redis::llen('queues:default');
        $checks['queue'] = "ok (pending: $queueSize)";
    } catch (\Exception $e) {
        $checks['queue'] = 'error: ' . $e->getMessage();
    }

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
        'version' => config('app.version', '1.0.0'),
    ], $healthy ? 200 : 503);
});
```

---

### 9.5.2 Prometheus Metrics (Optional)
- [ ] **Erledigt**

**Installation:**
```bash
composer require promphp/prometheus_client_php
```

**Datei:** `backend/app/Http/Controllers/MetricsController.php`
```php
<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis as RedisStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function index()
    {
        $registry = new CollectorRegistry(new RedisStorage([
            'host' => config('database.redis.default.host'),
            'port' => config('database.redis.default.port'),
            'password' => config('database.redis.default.password'),
        ]));

        // Gauge: Active Users
        $activeUsers = $registry->getOrRegisterGauge(
            'teamchat',
            'active_users',
            'Number of active users'
        );
        $activeUsers->set(DB::table('users')->where('status', '!=', 'offline')->count());

        // Gauge: Total Messages (last 24h)
        $messagesGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'messages_24h',
            'Messages in last 24 hours'
        );
        $messagesGauge->set(DB::table('messages')
            ->where('created_at', '>=', now()->subDay())
            ->count()
        );

        // Gauge: Queue Size
        $queueGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'queue_size',
            'Number of pending queue jobs'
        );
        $queueGauge->set(Redis::llen('queues:default'));

        // Gauge: Active Connections
        $connectionsGauge = $registry->getOrRegisterGauge(
            'teamchat',
            'websocket_connections',
            'Number of active WebSocket connections'
        );
        // Dies würde von Reverb kommen, placeholder
        $connectionsGauge->set(0);

        $renderer = new RenderTextFormat();
        return response($renderer->render($registry->getMetricFamilySamples()))
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
```

---

### 9.5.3 Logging Configuration
- [ ] **Erledigt**

**Datei:** `backend/config/logging.php` (relevante Teile):
```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'TeamChat',
        'emoji' => ':boom:',
        'level' => 'error',
    ],

    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

---

## 9.6 Security Hardening [INFRA]

### 9.6.1 Security Headers Middleware
- [ ] **Erledigt**

**Datei:** `backend/app/Http/Middleware/SecurityHeaders.php`
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```

---

### 9.6.2 Rate Limiting
- [ ] **Erledigt**

**Datei:** `backend/app/Providers/RouteServiceProvider.php` ergänzen:
```php
protected function configureRateLimiting(): void
{
    // Standard API Rate Limit
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Auth Endpoints (strenger)
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // Message Sending
    RateLimiter::for('messages', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });

    // File Uploads
    RateLimiter::for('uploads', function (Request $request) {
        return Limit::perHour(50)->by($request->user()?->id ?: $request->ip());
    });
}
```

---

### 9.6.3 Security Checklist
- [ ] **Erledigt**

**Datei:** `docs/SECURITY_CHECKLIST.md`
```markdown
# TeamChat Security Checklist

## Pre-Deployment

- [ ] APP_DEBUG=false in production
- [ ] APP_ENV=production
- [ ] Unique APP_KEY and APP_CIPHER_KEY generated
- [ ] Strong database passwords (min. 24 characters)
- [ ] Redis password set
- [ ] HTTPS enforced
- [ ] CORS properly configured

## Server Security

- [ ] Firewall configured (only ports 80, 443, 22)
- [ ] SSH key authentication only
- [ ] Fail2ban installed
- [ ] Automatic security updates enabled
- [ ] Non-root user for deployment

## Application Security

- [ ] All dependencies up to date
- [ ] No known vulnerabilities (npm audit, composer audit)
- [ ] Rate limiting enabled
- [ ] CSRF protection active
- [ ] SQL injection prevention (parameterized queries)
- [ ] XSS prevention (output encoding)
- [ ] File upload validation

## Data Security

- [ ] Messages encrypted at rest
- [ ] Passwords hashed with bcrypt
- [ ] Sensitive data not logged
- [ ] Database backups encrypted
- [ ] S3 bucket private

## Monitoring

- [ ] Error tracking configured
- [ ] Security logging enabled
- [ ] Alerts for suspicious activity
- [ ] Regular security audits scheduled

## Compliance

- [ ] Privacy policy published
- [ ] Terms of service published
- [ ] Data deletion process documented
- [ ] GDPR compliance (if applicable)
```

---

## 9.7 Documentation [DOCS]

### 9.7.1 Server Setup Guide
- [ ] **Erledigt**

**Datei:** `docs/SERVER_SETUP.md`
```markdown
# TeamChat Server Setup Guide

## Requirements

- Ubuntu 22.04 LTS (or similar)
- Docker 24.x
- Docker Compose 2.x
- 4GB RAM minimum (8GB recommended)
- 50GB SSD storage
- Domain with DNS configured

## Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo apt install docker-compose-plugin

# Create deployment user
sudo adduser deploy
sudo usermod -aG docker deploy

# Setup SSH key for deploy user
sudo -u deploy mkdir -p /home/deploy/.ssh
# Add your public key to /home/deploy/.ssh/authorized_keys
```

## Deployment

1. Clone repository:
```bash
sudo mkdir -p /opt/teamchat
sudo chown deploy:deploy /opt/teamchat
cd /opt/teamchat
git clone https://github.com/your-org/teamchat.git .
```

2. Configure environment:
```bash
cp backend/.env.production.example backend/.env.production
# Edit .env.production with your values
./scripts/generate-keys.sh
```

3. Start services:
```bash
docker compose -f docker-compose.prod.yml up -d
```

4. Run migrations:
```bash
docker exec teamchat-app php artisan migrate --force
```

5. Create admin user:
```bash
docker exec -it teamchat-app php artisan tinker
>>> User::create(['email'=>'admin@example.com','password'=>bcrypt('password'),'username'=>'Admin']);
```

## SSL Certificate

SSL is handled automatically by Traefik with Let's Encrypt.
Make sure your DNS is pointing to the server before starting.

## Maintenance

### View Logs
```bash
docker compose -f docker-compose.prod.yml logs -f app
```

### Restart Services
```bash
docker compose -f docker-compose.prod.yml restart
```

### Update Application
```bash
./scripts/deploy.sh
```
```

---

### 9.7.2 API Documentation
- [ ] **Erledigt**

**Installation:**
```bash
composer require dedoc/scramble
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider"
```

Die API-Dokumentation ist dann unter `/docs/api` verfügbar.

---

### 9.7.3 Git Commit & Tag
- [ ] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 9: Deployment - Docker, CI/CD, Security, Monitoring"
git tag v0.9.0
git tag v1.0.0
```

---

## Phase 9 Zusammenfassung

### Erstellte Dateien

**Docker:**
- `backend/Dockerfile` - Production Image
- `backend/docker/php/php.ini` - PHP Config
- `backend/docker/php/opcache.ini` - OPcache Config
- `backend/docker/nginx/nginx.conf` - Nginx Config
- `backend/docker/nginx/default.conf` - Site Config
- `backend/docker/supervisor/supervisord.conf` - Process Manager
- `docker-compose.prod.yml` - Production Stack
- `docker/mysql/my.cnf` - MySQL Optimization

**Scripts:**
- `scripts/generate-keys.sh` - Key Generation
- `scripts/deploy.sh` - Deployment
- `scripts/backup.sh` - Automated Backup
- `scripts/restore.sh` - Restore from Backup

**CI/CD:**
- `.github/workflows/ci.yml` - GitHub Actions

**Documentation:**
- `docs/SERVER_SETUP.md`
- `docs/SECURITY_CHECKLIST.md`

### Production Stack

| Service | Image | Port |
|---------|-------|------|
| App (Laravel + Reverb) | Custom | 80 |
| MySQL | mysql:8.0 | 3306 |
| Redis | redis:7-alpine | 6379 |
| Traefik | traefik:v2.10 | 80, 443 |

### CI/CD Pipeline

1. **Test Stage:**
   - PHPUnit Tests
   - PHPStan Analysis
   - Frontend Tests
   - ESLint

2. **Build Stage:**
   - Docker Image Build
   - Push to Registry

3. **Deploy Stage:**
   - SSH to Server
   - Pull Images
   - Run Migrations
   - Cache Config
   - Restart Services

### Backup Strategy

- **Daily:** Full database + storage backup
- **Retention:** 30 days local, S3 sync
- **Recovery:** Documented restore process

### Monitoring

- Health Check Endpoint (`/api/health`)
- Prometheus Metrics (optional)
- Slack Error Notifications
- Daily/Security Logs

---

## 🎉 Projekt abgeschlossen!

### Version 1.0.0 Features

✅ **Backend:**
- Laravel 12 mit PHP 8.3
- RESTful API mit 40+ Endpoints
- AES-256 Nachrichtenverschlüsselung
- WebSocket Real-Time mit Laravel Reverb
- TinyPNG Bildkomprimierung
- Sanctum Authentication

✅ **Frontend:**
- Electron Desktop App
- Vue 3 + TypeScript
- Pinia State Management
- TailwindCSS Styling
- Real-Time Updates
- Offline-Unterstützung (geplant)

✅ **Features:**
- Multi-Company Support
- Public/Private Channels
- Direct Messages mit Request-Flow
- Emoji Reactions
- File Sharing mit Thumbnails
- Online Status
- Typing Indicators
- Message Edit/Delete

✅ **Infrastruktur:**
- Docker Production Setup
- CI/CD mit GitHub Actions
- SSL via Traefik + Let's Encrypt
- Automated Backups
- Health Monitoring

### Nächste Schritte (Post v1.0)

- [ ] Mobile Apps (React Native)
- [ ] Video/Audio Calls (Jitsi Integration)
- [ ] Thread Replies
- [ ] Message Search
- [ ] Custom Emojis
- [ ] Bot/Integration API
- [ ] Enterprise SSO (SAML/LDAP)
