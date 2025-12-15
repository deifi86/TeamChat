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
