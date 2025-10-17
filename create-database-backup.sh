#!/bin/bash
# Create database backup for 501c3PO

BACKUP_DIR="/home/swca/public_html/wp-content/plugins/501c3PO"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="database-backup-${TIMESTAMP}.sql.gz"

echo "=== Creating Database Backup ==="
echo "Database: swca_swca2019"
echo "Output: ${BACKUP_DIR}/${BACKUP_FILE}"
echo ""

cd "$BACKUP_DIR"

mysqldump -u swca_swca2019 -p'5Corners!' swca_swca2019 \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --skip-comments \
  --add-drop-table | gzip > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
  echo "✅ Backup created successfully!"
  echo ""
  ls -lh "$BACKUP_FILE"
  echo ""
  echo "File location: ${BACKUP_DIR}/${BACKUP_FILE}"

  # Get size
  SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
  echo "Compressed size: ${SIZE}"
else
  echo "❌ Backup failed!"
  exit 1
fi
