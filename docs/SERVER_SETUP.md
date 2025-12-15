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

## Troubleshooting

### Check Container Status
```bash
docker ps
docker compose -f docker-compose.prod.yml ps
```

### Check Logs
```bash
docker logs teamchat-app
docker logs teamchat-mysql
docker logs teamchat-redis
```

### Access Container Shell
```bash
docker exec -it teamchat-app sh
```

### Clear Cache
```bash
docker exec teamchat-app php artisan cache:clear
docker exec teamchat-app php artisan config:clear
```

## Backup & Recovery

### Create Backup
```bash
./scripts/backup.sh
```

### Restore from Backup
```bash
./scripts/restore.sh 20241215_143000
```

## Monitoring

### Health Check
```bash
curl https://api.teamchat.example.com/api/health
```

### Metrics (Prometheus)
```bash
curl https://api.teamchat.example.com/api/metrics
```

## Security

- Keep system and Docker updated
- Use strong passwords
- Enable firewall (ufw)
- Regular backups
- Monitor logs for suspicious activity

## Support

For issues, check:
1. Application logs: `/var/www/html/storage/logs/`
2. Container logs: `docker logs teamchat-app`
3. GitHub Issues: https://github.com/your-org/teamchat/issues
