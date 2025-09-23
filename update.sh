#!/bin/bash
# Quick update script for 501c3PO plugin

echo "🤖 501c3PO Update Script"
echo "========================"

# Check if we're in the right directory
if [ ! -f "501c3po.php" ]; then
    echo "❌ Error: This script must be run from the 501c3PO plugin directory"
    exit 1
fi

# Check if git is available
if ! command -v git &> /dev/null; then
    echo "❌ Error: Git is not installed"
    exit 1
fi

# Check if this is a git repository
if [ ! -d ".git" ]; then
    echo "❌ Error: This plugin was not installed via git"
    echo "💡 Tip: Use WordPress admin panel to update instead"
    exit 1
fi

echo "📡 Fetching latest updates from GitHub..."
git fetch origin main

# Check if there are updates
if git status -uno | grep -q "Your branch is up to date"; then
    echo "✅ Plugin is already up to date!"
    exit 0
fi

echo "📥 Downloading updates..."
git pull origin main

if [ $? -eq 0 ]; then
    echo "✅ Plugin updated successfully!"
    echo "🔄 Please refresh your WordPress admin panel"
else
    echo "❌ Error updating plugin. Please check your git configuration"
    exit 1
fi