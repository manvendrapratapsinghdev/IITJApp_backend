#!/bin/bash
###############################################################################
# Quick Migration Deployment Script
# 
# Uploads migration files directly to production server
# Usage: ./deploy_migration.sh
###############################################################################

set -e

SERVER_USER="ubuntu"
SERVER_HOST="13.218.227.172"
SSH_KEY="/Users/d111879/Documents/Project/DEMO/Student/demo_api/docs/pem/gyantech.cer"
REMOTE_PATH="/var/www/html/api/migration"
LOCAL_MIGRATION="/Users/d111879/Documents/Project/DEMO/Student/demo_api/migration"

echo "========================================"
echo "   MIGRATION DEPLOYMENT"
echo "========================================"
echo ""

echo "[1/3] Creating migration directory on server..."
ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" "sudo mkdir -p $REMOTE_PATH && sudo chown ubuntu:ubuntu $REMOTE_PATH"

echo ""
echo "[2/3] Uploading migration files..."
scp -i "$SSH_KEY" \
    "$LOCAL_MIGRATION/user_migration.json" \
    "$LOCAL_MIGRATION/migrate_emails.php" \
    "$SERVER_USER@$SERVER_HOST:$REMOTE_PATH/"

echo ""
echo "[3/3] Setting permissions..."
ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" "sudo chmod 644 $REMOTE_PATH/user_migration.json && sudo chmod 755 $REMOTE_PATH/migrate_emails.php"

echo ""
echo "✅ Migration files deployed!"
echo ""
echo "To run migration:"
echo "  ssh -i $SSH_KEY $SERVER_USER@$SERVER_HOST"
echo "  cd $REMOTE_PATH"
echo "  php migrate_emails.php --preview"
echo "  php migrate_emails.php"
echo ""
