# Setup & Installation Files

Tento adresář obsahuje instalační skripty, databázové migrace a produkční úkoly pro WGS Service.

## 🚀 QUICK START - Produkční úkoly

**Chceš přidat 3 produkční úkoly do Control Center? Jednoduše:**

1. Otevři v prohlížeči: `https://your-domain.com/setup/add_production_tasks.php`
2. Script automaticky přidá 3 úkoly (Databázové indexy, Foreign Keys, Setup security)
3. Jdi do **Control Center → Akce & Úkoly**
4. Spusť úkoly jedním kliknutím
5. ✅ Hotovo!

**Poznámka:** Musíš být přihlášený jako admin!

📖 Detailní návod: viz `PRODUCTION_TASKS_HOWTO.md`

---

## 📁 Struktura

### Produkční Úkoly (NOVÉ! 🎉)
- **`add_production_tasks.php`** - Přidá 3 úkoly do Control Center (spusť v prohlížeči)
- `add_pending_actions_production.sql` - SQL verze (pokud preferuješ phpMyAdmin)
- `cleanup_now.sql` - Vyčistí dokončené úkoly (jednorázově)
- `auto_cleanup_completed_actions.sql` - Automatický cleanup (MySQL EVENT)
- `PRODUCTION_TASKS_HOWTO.md` - Kompletní návod

### Install Skripty (PHP)
- `install_*.php` - Instalační skripty pro různé moduly
- Spouštět přes web (vyžaduje admin přihlášení)

### Database Migrace (SQL)
- `migration_*.sql` - Databázové migrace
- `update_*.sql` - Update skripty
- `add_*.sql` - Přidání nových struktur

### Security
- `.htaccess.localhost` - Development config (allow localhost only)
- `.htaccess.production` - Production config (block all access)

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