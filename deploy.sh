#!/bin/bash
###############################################################################
# Git Deployment Script for Production Server
# 
# This script helps deploy code from Git repository to production server
# Usage: ./deploy.sh
###############################################################################

set -e  # Exit on error

# Configuration
SERVER_USER="ubuntu"
SERVER_HOST="13.218.227.172"
SSH_KEY="/Users/d111879/Documents/Project/DEMO/Student/demo_api/docs/pem/gyantech.cer"
REMOTE_PATH="/var/www/html/api"
GIT_REPO="https://github.com/manvendrapratapsinghdev/IITJApp_backend.git"
GIT_BRANCH="main"  # or master

echo "========================================"
echo "   GIT DEPLOYMENT SCRIPT"
echo "========================================"
echo ""

# Function to run commands on server
run_remote() {
    ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" "$1"
}

echo "[1/6] Connecting to server..."
run_remote "echo 'Connected successfully'"

echo ""
echo "[2/6] Checking if Git repository exists..."
if run_remote "[ -d $REMOTE_PATH/.git ] && echo 'yes' || echo 'no'" | grep -q "yes"; then
    echo "      ✓ Git repository found"
    
    echo ""
    echo "[3/6] Configuring Git and fixing permissions..."
    run_remote "sudo git config --system --add safe.directory $REMOTE_PATH || true"
    run_remote "sudo chown -R www-data:www-data $REMOTE_PATH/.git"
    
    echo ""
    echo "[4/6] Stashing local changes and pulling latest..."
    run_remote "cd $REMOTE_PATH && sudo -u www-data git stash"
    
    echo "      Checking remote configuration..."
    if ! run_remote "cd $REMOTE_PATH && sudo -u www-data git remote get-url origin" > /dev/null 2>&1; then
        echo "      Adding remote origin..."
        run_remote "cd $REMOTE_PATH && sudo -u www-data git remote add origin $GIT_REPO"
    fi
    
    run_remote "cd $REMOTE_PATH && sudo -u www-data git config pull.rebase false"
    run_remote "cd $REMOTE_PATH && sudo -u www-data git pull origin $GIT_BRANCH"
    
    echo "      Restoring stashed changes..."
    run_remote "cd $REMOTE_PATH && sudo -u www-data git stash pop || true"
    
else
    echo "      ✗ No Git repository found"
    echo ""
    echo "[3/6] Cloning repository..."
    run_remote "sudo rm -rf $REMOTE_PATH && sudo git clone -b $GIT_BRANCH $GIT_REPO $REMOTE_PATH"
    
    echo ""
    echo "[4/6] Configuring Git..."
    run_remote "sudo git config --system --add safe.directory $REMOTE_PATH || true"
fi

echo ""
echo "[5/6] Setting correct permissions..."
run_remote "sudo chown -R www-data:www-data $REMOTE_PATH"
run_remote "sudo chmod -R 755 $REMOTE_PATH"

echo ""
echo "[6/6] Checking for composer dependencies..."
if run_remote "[ -f $REMOTE_PATH/composer.json ] && echo 'yes' || echo 'no'" | grep -q "yes"; then
    echo "      ✓ composer.json found, installing dependencies..."
    run_remote "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader || true"
else
    echo "      ⊘ No composer.json found, skipping"
fi

echo ""
echo "[7/7] Restarting services..."
run_remote "sudo systemctl restart apache2 || sudo systemctl restart nginx || true"
run_remote "sudo systemctl restart php-fpm || sudo systemctl restart php8.1-fpm || sudo systemctl restart php7.4-fpm || true"

echo ""
echo "========================================"
echo "✅ DEPLOYMENT COMPLETE!"
echo "========================================"
echo ""
echo "Server: $SERVER_HOST"
echo "Path: $REMOTE_PATH"
echo "Branch: $GIT_BRANCH"
echo ""
