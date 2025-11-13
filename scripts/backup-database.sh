#!/bin/bash
#
# WGS Service - Automated Database Backup Script
# Vytváří komprimované zálohy databáze s rotací (7 denních + 4 týdenní + 12 měsíčních)
#

set -euo pipefail

# Barvy pro output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Načíst konfiguraci z .env
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}ERROR: .env file not found at $ENV_FILE${NC}"
    exit 1
fi

# Načíst DB credentials z .env
DB_HOST=$(grep "^DB_HOST=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
DB_NAME=$(grep "^DB_NAME=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
DB_USER=$(grep "^DB_USER=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
DB_PASS=$(grep "^DB_PASS=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")

if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo -e "${RED}ERROR: Missing database credentials in .env${NC}"
    exit 1
fi

# Adresáře pro zálohy
BACKUP_DIR="$PROJECT_ROOT/backups"
DAILY_DIR="$BACKUP_DIR/daily"
WEEKLY_DIR="$BACKUP_DIR/weekly"
MONTHLY_DIR="$BACKUP_DIR/monthly"

# Vytvořit adresáře pokud neexistují
mkdir -p "$DAILY_DIR" "$WEEKLY_DIR" "$MONTHLY_DIR"

# Timestamp
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
DATE=$(date '+%Y-%m-%d')
DAY_OF_WEEK=$(date '+%u')  # 1 = Monday, 7 = Sunday
DAY_OF_MONTH=$(date '+%d')

# Názvy souborů
BACKUP_FILE="backup_${DB_NAME}_${TIMESTAMP}.sql.gz"
DAILY_BACKUP="$DAILY_DIR/$BACKUP_FILE"

echo "=========================================="
echo "WGS Service - Database Backup"
echo "=========================================="
echo "Database: $DB_NAME"
echo "Timestamp: $TIMESTAMP"
echo ""

# Vytvoření zálohy pomocí mysqldump
echo -n "Creating backup... "
if [ -n "$DB_PASS" ]; then
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --routines \
        --triggers \
        --events \
        "$DB_NAME" | gzip > "$DAILY_BACKUP"
else
    mysqldump -h "$DB_HOST" -u "$DB_USER" \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --routines \
        --triggers \
        --events \
        "$DB_NAME" | gzip > "$DAILY_BACKUP"
fi

if [ -f "$DAILY_BACKUP" ]; then
    BACKUP_SIZE=$(du -h "$DAILY_BACKUP" | cut -f1)
    echo -e "${GREEN}✓ Done${NC}"
    echo "Size: $BACKUP_SIZE"
    echo "Location: $DAILY_BACKUP"
else
    echo -e "${RED}✗ Failed${NC}"
    exit 1
fi

# Nedělní záloha = týdenní backup
if [ "$DAY_OF_WEEK" -eq 7 ]; then
    WEEKLY_BACKUP="$WEEKLY_DIR/weekly_${DATE}.sql.gz"
    cp "$DAILY_BACKUP" "$WEEKLY_BACKUP"
    echo -e "${GREEN}✓ Weekly backup created${NC}"
fi

# První den měsíce = měsíční backup
if [ "$DAY_OF_MONTH" -eq 01 ]; then
    MONTHLY_BACKUP="$MONTHLY_DIR/monthly_${DATE}.sql.gz"
    cp "$DAILY_BACKUP" "$MONTHLY_BACKUP"
    echo -e "${GREEN}✓ Monthly backup created${NC}"
fi

echo ""
echo "Cleaning old backups..."

# Rotace denních záloh (ponechat 7 posledních)
DAILY_COUNT=$(find "$DAILY_DIR" -name "*.sql.gz" -type f | wc -l)
if [ "$DAILY_COUNT" -gt 7 ]; then
    DAILY_TO_DELETE=$((DAILY_COUNT - 7))
    find "$DAILY_DIR" -name "*.sql.gz" -type f -printf '%T+ %p\n' | sort | head -n "$DAILY_TO_DELETE" | cut -d' ' -f2- | xargs rm -f
    echo -e "${YELLOW}Deleted $DAILY_TO_DELETE old daily backup(s)${NC}"
fi

# Rotace týdenních záloh (ponechat 4 poslední)
WEEKLY_COUNT=$(find "$WEEKLY_DIR" -name "*.sql.gz" -type f | wc -l)
if [ "$WEEKLY_COUNT" -gt 4 ]; then
    WEEKLY_TO_DELETE=$((WEEKLY_COUNT - 4))
    find "$WEEKLY_DIR" -name "*.sql.gz" -type f -printf '%T+ %p\n' | sort | head -n "$WEEKLY_TO_DELETE" | cut -d' ' -f2- | xargs rm -f
    echo -e "${YELLOW}Deleted $WEEKLY_TO_DELETE old weekly backup(s)${NC}"
fi

# Rotace měsíčních záloh (ponechat 12 posledních)
MONTHLY_COUNT=$(find "$MONTHLY_DIR" -name "*.sql.gz" -type f | wc -l)
if [ "$MONTHLY_COUNT" -gt 12 ]; then
    MONTHLY_TO_DELETE=$((MONTHLY_COUNT - 12))
    find "$MONTHLY_DIR" -name "*.sql.gz" -type f -printf '%T+ %p\n' | sort | head -n "$MONTHLY_TO_DELETE" | cut -d' ' -f2- | xargs rm -f
    echo -e "${YELLOW}Deleted $MONTHLY_TO_DELETE old monthly backup(s)${NC}"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}Backup completed successfully!${NC}"
echo "=========================================="
echo "Current backup counts:"
echo "  Daily: $(find "$DAILY_DIR" -name "*.sql.gz" -type f | wc -l)/7"
echo "  Weekly: $(find "$WEEKLY_DIR" -name "*.sql.gz" -type f | wc -l)/4"
echo "  Monthly: $(find "$MONTHLY_DIR" -name "*.sql.gz" -type f | wc -l)/12"
echo ""

# Logovat do audit logu (pokud existuje tabulka)
if command -v php &> /dev/null; then
    php -r "
    require_once '$PROJECT_ROOT/init.php';
    try {
        \$pdo = new PDO('mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4', '$DB_USER', '$DB_PASS');
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        \$stmt = \$pdo->prepare('INSERT INTO wgs_audit_log (action, details, ip_address, timestamp) VALUES (?, ?, ?, NOW())');
        \$stmt->execute(['backup_created', 'Automated daily backup: $BACKUP_FILE ($BACKUP_SIZE)', 'cron']);
    } catch (Exception \$e) {
        // Tichý fail - audit log není kritický
    }
    " 2>/dev/null || true
fi

exit 0
