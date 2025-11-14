# Setup & Installation Files

Tento adresář obsahuje instalační skripty a databázové migrace pro WGS Service.

## 📁 Struktura

### Install Skripty (PHP)
- `install_*.php` - Instalační skripty pro různé moduly
- Spouštět přes web (vyžaduje admin přihlášení)

### Database Migrace (SQL)
- `migration_*.sql` - Databázové migrace
- `update_*.sql` - Update skripty
- `add_*.sql` - Přidání nových struktur

## 🚀 Jak Používat

### Install Skripty
```bash
# Web přístup (doporučeno)
https://your-domain.com/setup/install_admin_control_center.php

# Nebo CLI
php setup/install_admin_control_center.php
```

### Database Migrace
```bash
# Import do MySQL
mysql -u username -p database_name < setup/migration_name.sql

# Nebo přes PHPMyAdmin
```

## ⚠️  Bezpečnost

1. **PROD Warning**: V produkci ODSTRANIT nebo ZABEZPEČIT tento adresář!
2. Přidat do `.htaccess`:
   ```apache
   <Directory "setup">
       Require all denied
   </Directory>
   ```
3. Nebo přesunout mimo web root po instalaci

## 📋 Checklist Po Instalaci

- [ ] Spustit všechny install_*.php skripty
- [ ] Aplikovat potřebné migrace
- [ ] Otestovat funkcionalitu
- [ ] Zabezpečit nebo odstranit setup/ adresář
- [ ] Zkontrolovat logy

## 📝 Historie

- 2025-11-14: Organizace setup souborů (MEDIUM priority cleanup)