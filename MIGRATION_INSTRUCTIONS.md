# Oprava SMTP - Databázová Migrace

## Problém
Chybí tabulka `wgs_system_config` v databázi, což způsobuje selhání SMTP konfigurace.

## Řešení

### Možnost 1: Otevřít v prohlížeči (DOPORUČENO)

Otevři tuto stránku v prohlížeči:

```
https://wgs-service.cz/run_migration_simple.php?key=wgs-migration-2025
```

Stránka:
1. ✅ Zkontroluje, které tabulky chybí
2. ✅ Nabídne tlačítko "Run Migration"
3. ✅ Spustí migraci a vytvoří všech 6 tabulek
4. ✅ Zobrazí výsledek

### Možnost 2: Přes existující Admin Panel

Pokud již jsi přihlášen jako admin, otevři:

```
https://wgs-service.cz/install_admin_control_center.php
```

### Co se vytvoří

Migrace vytvoří tyto tabulky:
- ✅ `wgs_theme_settings` - Barvy, fonty, logo
- ✅ `wgs_content_texts` - Editovatelné texty
- ✅ **`wgs_system_config`** - SMTP a konfigurace (DŮLEŽITÉ!)
- ✅ `wgs_pending_actions` - Systém úkolů
- ✅ `wgs_action_history` - Historie akcí
- ✅ `wgs_github_webhooks` - GitHub integrace

## Po migraci

1. SMTP konfigurace bude dostupná v Admin Panel
2. Selhavší úkol zmizí z historie
3. Developer Console bude plně funkční

## Soubory

- `run_migration_simple.php` - Jednoduchý webový interface
- `install_admin_control_center.php` - Plnohodnotný instalátor
- `migration_admin_control_center.sql` - SQL migrace
- `api/migration_executor.php` - API pro spouštění migrací
