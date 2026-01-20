#!/bin/bash
###############################################################################
# Server Path Finder
# Helps locate the correct code directory on production server
###############################################################################

SSH_KEY="/Users/d111879/Documents/Project/DEMO/Student/demo_api/docs/pem/gyantech.cer"
SERVER="ubuntu@13.218.227.172"

echo "========================================"
echo "   FINDING CODE LOCATION ON SERVER"
echo "========================================"
echo ""

echo "Searching common locations..."
echo ""

ssh -i "$SSH_KEY" "$SERVER" << 'ENDSSH'
echo "=== Common Web Directories ==="
for dir in /var/www/html /var/www/html/public /var/www/public /home/ubuntu; do
    if [ -d "$dir" ]; then
        echo "✓ Found: $dir"
        if [ -f "$dir/api.php" ] || [ -f "$dir/index.php" ]; then
            echo "  → Contains PHP files (likely the correct path)"
        fi
        if [ -d "$dir/.git" ]; then
            echo "  → Contains .git directory"
        fi
    fi
done

echo ""
echo "=== Looking for api.php or config directories ==="
find /var/www /home/ubuntu -name "api.php" -o -name "config.php" 2>/dev/null | head -20

echo ""
echo "=== Current directory structure in /var/www/html ==="
ls -la /var/www/html/ 2>/dev/null || echo "Directory not accessible"
ENDSSH

echo ""
echo "========================================"
echo "Based on the output above, update REMOTE_PATH in:"
echo "  - deploy.sh"
echo "  - deploy_migration.sh"
echo "========================================"
