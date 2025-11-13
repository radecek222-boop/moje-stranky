# 🔐 WGS Service - Automatické Zálohování Databáze

## 📋 Přehled

Automatický zálohovací systém s rotací záloh podle osvědčených postupů:
- **7 denních záloh** (každý den v 2:00)
- **4 týdenní zálohy** (každou neděli)
- **12 měsíčních záloh** (první den každého měsíce)

## 🚀 Instalace Cron Jobu

### 1. Otevřít crontab
```bash
crontab -e
```

### 2. Přidat tento řádek
```bash
# WGS Service - Daily Database Backup (2:00 AM)
0 2 * * * /home/user/moje-stranky/scripts/backup-database.sh >> /home/user/moje-stranky/logs/backup.log 2>&1
```

### 3. Uložit a zavřít (Ctrl+O, Enter, Ctrl+X)

### 4. Ověřit instalaci
```bash
crontab -l
```

## 🔧 Manuální Spuštění

```bash
cd /home/user/moje-stranky
./scripts/backup-database.sh
```

## 📁 Struktura Záloh

```
backups/
├── daily/          # 7 denních záloh (automaticky rotuje)
├── weekly/         # 4 týdenní zálohy (každou neděli)
├── monthly/        # 12 měsíčních záloh (každý 1. den měsíce)
└── .htaccess       # Bezpečnostní ochrana (Deny from all)
```

## 🔍 Kontrola Stavu

### Zobrazit počet záloh
```bash
echo "Daily: $(ls -1 backups/daily/*.sql.gz 2>/dev/null | wc -l)/7"
echo "Weekly: $(ls -1 backups/weekly/*.sql.gz 2>/dev/null | wc -l)/4"
echo "Monthly: $(ls -1 backups/monthly/*.sql.gz 2>/dev/null | wc -l)/12"
```

### Zobrazit velikosti
```bash
du -h backups/daily/ backups/weekly/ backups/monthly/
```

### Zobrazit poslední zálohu
```bash
ls -lth backups/daily/ | head -2
```

## 📦 Obnovení ze Zálohy

### 1. Vybrat zálohu
```bash
ls -lh backups/daily/
```

### 2. Obnovit databázi
```bash
# Rozbalit a importovat
gunzip < backups/daily/backup_wgs_service_2025-11-13_02-00-00.sql.gz | mysql -u USER -p DATABASE_NAME

# NEBO v jednom kroku
zcat backups/daily/backup_wgs_service_2025-11-13_02-00-00.sql.gz | mysql -u USER -p DATABASE_NAME
```

### 3. Ověřit obnovení
```bash
mysql -u USER -p -e "SHOW TABLES;" DATABASE_NAME
```

## 🛡️ Bezpečnost

- ✅ `.htaccess` blokuje přímý HTTP přístup ke zálohám
- ✅ Zálohy jsou komprimované (gzip)
- ✅ Credentials načítány z `.env` (nikdy v plaintext)
- ✅ Audit log zaznamenává každou zálohu
- ✅ Single transaction (konzistentní data bez zamykání)

## ⚡ Výkon

- **Single transaction**: Databáze není zamčená během zálohy
- **Quick mode**: Rychlejší načítání dat
- **Gzip komprese**: 10-20x menší soubory
- **Typická doba**: < 10 sekund pro malou databázi (< 100 MB)

## 🔔 Monitoring

### Kontrola backup logů
```bash
tail -f logs/backup.log
```

### Kontrola cron logů
```bash
grep CRON /var/log/syslog | grep backup-database
```

### Email notifikace při selhání
Přidat do crontabu `MAILTO`:
```bash
MAILTO=admin@example.com
0 2 * * * /home/user/moje-stranky/scripts/backup-database.sh
```

## 🚨 Troubleshooting

### Chyba: "mysqldump: command not found"
```bash
# Debian/Ubuntu
sudo apt-get install mysql-client

# CentOS/RHEL
sudo yum install mysql
```

### Chyba: "Permission denied"
```bash
chmod +x scripts/backup-database.sh
```

### Chyba: ".env file not found"
```bash
# Zkontrolovat že .env existuje v root adresáři projektu
ls -la .env
```

### Zálohy se nevytvářejí
```bash
# 1. Spustit manuálně pro debugging
./scripts/backup-database.sh

# 2. Zkontrolovat cron
crontab -l

# 3. Zkontrolovat logy
cat logs/backup.log
```

## 📊 Best Practices

1. **Testovat obnovu minimálně 1× měsíčně**
2. **Ukládat měsíční zálohy na externí úložiště** (např. AWS S3, Backblaze)
3. **Monitorovat velikost backups složky** (disk space)
4. **Uchovávat offline kopii kritických záloh**
5. **Dokumentovat recovery procedury**

## 🔗 Související

- Admin rozhraní: Backup API v Developer Console
- Manual backup: `/api/backup_api.php?action=create_backup` (vyžaduje admin přihlášení)
- Audit log: Všechny zálohy jsou logovány v `wgs_audit_log`
