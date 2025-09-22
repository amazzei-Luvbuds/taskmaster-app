#!/bin/bash

# Quick Screenshot Sharing Script
# Usage: ./share-screenshot.sh "description" /path/to/screenshot.png

echo "🏗️ Winston's Quick Image Sharing Helper"
echo "========================================"

if [ $# -eq 0 ]; then
    echo "📸 Available Methods:"
    echo ""
    echo "1. GitHub Issues (FASTEST - 2 seconds)"
    echo "   - Open: https://github.com/alexandermazzei/taskmaster-app/issues"
    echo "   - Drag & drop your image"
    echo "   - Copy auto-generated URL"
    echo ""
    echo "2. Google Drive (FAST - 10 seconds)"
    echo "   - Upload to shared folder"
    echo "   - Get shareable link"
    echo ""
    echo "3. Copy to project assets:"
    echo "   ./share-screenshot.sh 'description' /path/to/image.png"
    echo ""
    exit 0
fi

DESCRIPTION="$1"
IMAGE_PATH="$2"

if [ ! -f "$IMAGE_PATH" ]; then
    echo "❌ Image not found: $IMAGE_PATH"
    exit 1
fi

# Create descriptive filename
FILENAME=$(echo "$DESCRIPTION" | tr ' ' '-' | tr '[:upper:]' '[:lower:]').png
DEST_PATH="assets/screenshots/$FILENAME"

# Copy to project assets
cp "$IMAGE_PATH" "$DEST_PATH"

echo "✅ Image copied to: $DEST_PATH"
echo "🔗 Reference in markdown: ![${DESCRIPTION}](../assets/screenshots/${FILENAME})"
echo ""
echo "💡 For instant team sharing:"
echo "   1. Open GitHub Issues"
echo "   2. Drag & drop this file: $DEST_PATH"
echo "   3. Share the auto-generated URL"