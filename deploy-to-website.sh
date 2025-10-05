#!/bin/bash
# Deploy plugin from master development folder to website

SOURCE_DIR="/home/swca/scripts/501c3PO"
DEST_DIR="/home/swca/public_html/wp-content/plugins/501c3PO"

echo "🚀 Deploying 501c3PO plugin to website..."

# Copy main plugin file
echo "  → Copying 501c3po.php"
cp "$SOURCE_DIR/501c3po.php" "$DEST_DIR/"

# Copy readme files
echo "  → Copying readme files"
cp "$SOURCE_DIR/README.md" "$DEST_DIR/" 2>/dev/null || true
cp "$SOURCE_DIR/readme.txt" "$DEST_DIR/" 2>/dev/null || true

# Copy includes directory
echo "  → Copying includes/"
cp -r "$SOURCE_DIR/includes/core" "$DEST_DIR/includes/" 2>/dev/null || true
cp -r "$SOURCE_DIR/includes/features" "$DEST_DIR/includes/" 2>/dev/null || true

echo "✓ Deployment complete!"
echo ""
echo "Plugin files have been copied from master development folder to website."
echo "The changes are now live at: https://southwilliamstown.org"
