# WGS Service - API Reference

Kompletní dokumentace všech API endpointů.

**Verze:** 1.0.0
**Poslední aktualizace:** 2025-12-02

---

## Obsah

1. [Autentizace](#1-autentizace)
2. [Reklamace](#2-reklamace)
3. [Protokoly](#3-protokoly)
4. [Poznámky](#4-poznámky)
5. [Analytics](#5-analytics)
6. [Admin](#6-admin)
7. [Ceník](#7-ceník)
8. [Notifikace](#8-notifikace)
9. [Ostatní](#9-ostatní)

---

## Obecné informace

### Base URL
```
https://www.wgs-service.cz/api/
```

### Autentizace
Většina endpointů vyžaduje přihlášení. Session cookie se automaticky posílá s každým požadavkem.

### CSRF Ochrana
Všechny POST požadavky vyžadují CSRF token:
- V těle požadavku: `csrf_token`
- Nebo v hlavičce: `X-CSRF-TOKEN`

### Formát odpovědi
```json
{
  "status": "success" | "error",
  "message": "Popis výsledku",
  "data": { ... }
}
```

### HTTP Status kódy
| Kód | Význam |
|-----|--------|
| 200 | Úspěch |
| 400 | Chybný požadavek |
| 401 | Nepřihlášen |
| 403 | Zakázáno (CSRF, oprávnění) |
| 404 | Nenalezeno |
| 429 | Příliš mnoho požadavků |
| 500 | Chyba serveru |

---

## 1. Autentizace

### POST /app/controllers/login_controller.php
Přihlášení uživatele.

**Požadavek:**
```json
{
  "email": "user@example.com",
  "password": "heslo123",
  "remember_me": true,
  "csrf_token": "..."
}
```

**Odpověď (úspěch):**
```json
{
  "status": "success",
  "message": "Přihlášení úspěšné",
  "redirect": "/seznam.php"
}
```

**Odpověď (chyba):**
```json
{
  "status": "error",
  "message": "Nesprávné přihlašovací údaje"
}
```

**Rate limit:** 5 pokusů / 15 minut

---

### POST /app/controllers/registration_controller.php
Registrace nového uživatele.

**Požadavek:**
```json
{
  "email": "novy@example.com",
  "password": "heslo123",
  "password_confirm": "heslo123",
  "registration_key": "ABC123",
  "csrf_token": "..."
}
```

**Odpověď (úspěch):**
```json
{
  "status": "success",
  "message": "Registrace úspěšná"
}
```

**Validace:**
- Email: platný formát, unikátní
- Heslo: min. 8 znaků
- Registrační klíč: platný a nevyčerpaný

---

### GET /app/controllers/get_csrf_token.php
Získání CSRF tokenu pro AJAX požadavky.

**Odpověď:**
```json
{
  "csrf_token": "a1b2c3d4e5f6..."
}
```

---

## 2. Reklamace

### POST /app/controllers/save.php
Vytvoření nebo aktualizace reklamace.

**Požadavek (nová reklamace):**
```json
{
  "action": "create",
  "jmeno": "Jan Novák",
  "telefon": "+420777123456",
  "email": "jan@example.com",
  "adresa": "Hlavní 123, Praha",
  "popis_problemu": "Poškozená sedačka",
  "stav": "ČEKÁ",
  "csrf_token": "..."
}
```

**Požadavek (aktualizace):**
```json
{
  "action": "update",
  "reklamace_id": "2024/001",
  "stav": "DOMLUVENÁ",
  "termin": "2024-12-15",
  "csrf_token": "..."
}
```

**Odpověď:**
```json
{
  "status": "success",
  "message": "Reklamace uložena",
  "reklamace_id": "2024/001"
}
```

**Mapování stavů:**
| Frontend (CZ) | Database (EN) |
|---------------|---------------|
| ČEKÁ | wait |
| DOMLUVENÁ | open |
| HOTOVO | done |

---

### GET /api/delete_reklamace.php
Smazání reklamace (soft delete).

**Parametry:**
- `id` - ID reklamace
- `csrf_token` - CSRF token

**Odpověď:**
```json
{
  "status": "success",
  "message": "Reklamace smazána"
}
```

**Požadavky:** Admin oprávnění

---

## 3. Protokoly

### POST /api/protokol_api.php
Správa servisních protokolů.

**Akce: load_reklamace**
```json
{
  "action": "load_reklamace",
  "reklamace_id": "2024/001"
}
```

**Akce: save_pdf_document**
```json
{
  "action": "save_pdf_document",
  "reklamace_id": "2024/001",
  "pdf_base64": "...",
  "filename": "protokol_2024001.pdf",
  "csrf_token": "..."
}
```

**Akce: save_kalkulace**
```json
{
  "action": "save_kalkulace",
  "reklamace_id": "2024/001",
  "kalkulace_data": {
    "sluzby": [...],
    "celkovaCena": 450.00,
    "vzdalenost": 25
  },
  "csrf_token": "..."
}
```

**Rate limit:** 10 požadavků / hodina

---

## 4. Poznámky

### POST /api/notes_api.php
CRUD operace pro poznámky k reklamacím.

**Akce: list**
```json
{
  "action": "list",
  "reklamace_id": "2024/001"
}
```

**Akce: create**
```json
{
  "action": "create",
  "reklamace_id": "2024/001",
  "text": "Zákazník volal, potvrdil termín.",
  "csrf_token": "..."
}
```

**Akce: update**
```json
{
  "action": "update",
  "note_id": 123,
  "text": "Aktualizovaný text",
  "csrf_token": "..."
}
```

**Akce: delete**
```json
{
  "action": "delete",
  "note_id": 123,
  "csrf_token": "..."
}
```

---

## 5. Analytics

### POST /api/track_pageview.php
Záznam zobrazení stránky.

**Požadavek:**
```json
{
  "page_url": "/cenik.php",
  "page_title": "Ceník služeb",
  "referrer": "https://google.com",
  "session_id": "abc123"
}
```

**Odpověď:**
```json
{
  "status": "success"
}
```

---

### POST /api/track_event.php
Záznam vlastní události.

**Požadavek:**
```json
{
  "event_name": "button_click",
  "event_category": "cta",
  "event_data": {
    "button_id": "contact-btn"
  },
  "session_id": "abc123"
}
```

---

### POST /api/track_heatmap.php
Záznam heatmap dat (kliknutí, scrolly).

**Požadavek:**
```json
{
  "page_url": "/cenik.php",
  "clicks": [
    {"x": 150, "y": 300, "element": "button.cta"},
    {"x": 200, "y": 450, "element": "a.link"}
  ],
  "scroll_depth": 75,
  "session_id": "abc123"
}
```

---

### GET /api/analytics_api.php
Získání analytics dat.

**Parametry:**
- `action`: `dashboard`, `pageviews`, `events`, `sources`
- `date_from`: YYYY-MM-DD
- `date_to`: YYYY-MM-DD

**Odpověď:**
```json
{
  "status": "success",
  "data": {
    "total_pageviews": 1234,
    "unique_visitors": 456,
    "top_pages": [...]
  }
}
```

**Požadavky:** Admin oprávnění

---

### GET /api/analytics_heatmap.php
Heatmap data pro vizualizaci.

**Parametry:**
- `page_url`: URL stránky
- `type`: `clicks`, `scroll`, `movement`

---

### GET /api/analytics_replay.php
Session replay data.

**Parametry:**
- `session_id`: ID session

---

## 6. Admin

### POST /api/admin_api.php
Centrální admin API.

**Akce: get_registration_keys**
```json
{
  "action": "get_registration_keys",
  "csrf_token": "..."
}
```

**Akce: create_registration_key**
```json
{
  "action": "create_registration_key",
  "key_type": "standard",
  "max_usage": 5,
  "csrf_token": "..."
}
```

**Akce: deactivate_key**
```json
{
  "action": "deactivate_key",
  "key_id": 123,
  "csrf_token": "..."
}
```

---

### POST /api/admin_users_api.php
Správa uživatelů.

**Akce: list**
```json
{
  "action": "list",
  "csrf_token": "..."
}
```

**Akce: update_role**
```json
{
  "action": "update_role",
  "user_id": 123,
  "role": "technician",
  "csrf_token": "..."
}
```

**Akce: deactivate**
```json
{
  "action": "deactivate",
  "user_id": 123,
  "csrf_token": "..."
}
```

**Role:** `admin`, `technician`, `sales`, `partner`, `user`

---

### GET /api/admin_stats_api.php
Administrační statistiky.

**Odpověď:**
```json
{
  "status": "success",
  "data": {
    "total_reklamace": 150,
    "reklamace_ceka": 25,
    "reklamace_domluvena": 45,
    "reklamace_hotovo": 80,
    "total_users": 12
  }
}
```

---

### POST /api/backup_api.php
Záloha databáze.

**Akce: create_backup**
```json
{
  "action": "create_backup",
  "csrf_token": "..."
}
```

**Akce: list_backups**
```json
{
  "action": "list_backups",
  "csrf_token": "..."
}
```

**Požadavky:** Admin oprávnění

---

## 7. Ceník

### GET /api/pricing_api.php
Ceník služeb s překlady.

**Parametry:**
- `action`: `list`, `categories`
- `lang`: `cs`, `en`, `it`
- `category`: (volitelné) filtr kategorie

**Odpověď:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "service_name": "Oprava čalounění",
      "service_name_en": "Upholstery repair",
      "service_name_it": "Riparazione tappezzeria",
      "price_from": 190,
      "price_to": null,
      "price_unit": "€",
      "category": "Čalounění",
      "category_en": "Upholstery",
      "category_it": "Tappezzeria"
    }
  ]
}
```

---

## 8. Notifikace

### POST /api/notification_api.php
Správa notifikací.

**Akce: list**
```json
{
  "action": "list",
  "limit": 20,
  "offset": 0
}
```

**Akce: mark_read**
```json
{
  "action": "mark_read",
  "notification_id": 123,
  "csrf_token": "..."
}
```

**Akce: mark_all_read**
```json
{
  "action": "mark_all_read",
  "csrf_token": "..."
}
```

---

### POST /api/push_subscription_api.php
Web Push subscriptions.

**Akce: subscribe**
```json
{
  "action": "subscribe",
  "subscription": {
    "endpoint": "https://...",
    "keys": {
      "p256dh": "...",
      "auth": "..."
    }
  },
  "csrf_token": "..."
}
```

**Akce: unsubscribe**
```json
{
  "action": "unsubscribe",
  "endpoint": "https://...",
  "csrf_token": "..."
}
```

---

## 9. Ostatní

### POST /api/geocode_proxy.php
Proxy pro Geoapify geocoding (CORS workaround).

**Požadavek:**
```json
{
  "address": "Hlavní 123, Praha"
}
```

**Odpověď:**
```json
{
  "lat": 50.0755,
  "lon": 14.4378,
  "formatted": "Hlavní 123, 110 00 Praha 1, Czechia"
}
```

---

### POST /api/log_js_error.php
Logování JavaScript chyb z frontendu.

**Požadavek:**
```json
{
  "message": "TypeError: Cannot read property...",
  "stack": "at function...",
  "url": "/seznam.php",
  "line": 123,
  "column": 45,
  "user_agent": "Mozilla/5.0..."
}
```

---

### GET /api/get_photos_api.php
Získání fotografií k reklamaci.

**Parametry:**
- `reklamace_id`: ID reklamace

**Odpověď:**
```json
{
  "status": "success",
  "photos": [
    {
      "id": 1,
      "filename": "photo_001.jpg",
      "url": "/uploads/2024/001/photo_001.jpg",
      "uploaded_at": "2024-12-01 10:30:00"
    }
  ]
}
```

---

### POST /api/delete_photo.php
Smazání fotografie.

**Požadavek:**
```json
{
  "photo_id": 123,
  "csrf_token": "..."
}
```

---

### POST /api/gdpr_api.php
GDPR operace.

**Akce: export_data**
```json
{
  "action": "export_data",
  "email": "user@example.com",
  "csrf_token": "..."
}
```

**Akce: delete_data**
```json
{
  "action": "delete_data",
  "email": "user@example.com",
  "confirm": true,
  "csrf_token": "..."
}
```

**Požadavky:** Admin oprávnění

---

## Chybové odpovědi

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Neautorizovaný přístup. Přihlaste se prosím."
}
```

### 403 Forbidden (CSRF)
```json
{
  "status": "error",
  "message": "Neplatný CSRF token. Obnovte stránku a zkuste znovu."
}
```

### 429 Too Many Requests
```json
{
  "status": "error",
  "message": "Příliš mnoho pokusů. Zkuste to za 15 minut.",
  "retry_after": 900
}
```

### 500 Server Error
```json
{
  "status": "error",
  "message": "Chyba při zpracování požadavku"
}
```

---

## Changelog

### v1.0.0 (2025-12-02)
- Počáteční verze API dokumentace
- Dokumentace pro 56 API endpointů
- Přidány příklady požadavků a odpovědí

---

**Autor:** Claude Code
**Projekt:** WGS Service
