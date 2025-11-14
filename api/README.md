# API Endpoints

Tento adresář obsahuje všechny API endpointy pro WGS Service.

## 📁 Struktura

### Core APIs
- `control_center_api.php` - Hlavní API pro Admin Control Center
- `admin_api.php` - API pro správu registračních klíčů
- `notification_api.php` - API pro notifikace

### Feature-specific APIs
- `protokol_api.php` - API pro servisní protokoly
- `backup_api.php` - API pro databázové zálohy
- `github_webhook.php` - GitHub webhook handler
- `customer_api.php` - API pro správu zákazníků

## 🔒 Bezpečnost

Všechny API endpointy mají:
- ✅ Admin/User authentication check
- ✅ CSRF protection (pro POST/PUT/DELETE)
- ✅ Rate limiting (admin endpointy)
- ✅ Input validation

## 📊 Standardizovaný Formát

Nové API by měly používat `ApiResponse` helper:

```php
require_once __DIR__ . '/../includes/api_response.php';

// Success
ApiResponse::success($data, $message);

// Error
ApiResponse::error($message, $httpCode);

// Validation error
ApiResponse::validationError($errors);
```

Viz `docs/API_STANDARDIZATION_GUIDE.md` pro detaily.

## 🔧 Development

### Přidání nového API endpointu

1. Vytvořit nový soubor `my_api.php`
2. Přidat security checks:
```php
<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['is_admin'])) {
    ApiResponse::unauthorized();
}

// CSRF check pro POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        ApiResponse::forbidden('Invalid CSRF token');
    }
}

// Rate limiting (optional pro admin API)
require_once __DIR__ . '/../includes/rate_limiter.php';
$rateLimiter = new RateLimiter(getDbConnection());
// ... rate limit check ...

// Business logic here
```

3. Dokumentovat v tomto README

## 📝 API Endpoints Seznam

| Endpoint | Metoda | Auth | Popis |
|----------|--------|------|-------|
| `/api/control_center_api.php` | GET/POST | Admin | Control Center funkce |
| `/api/admin_api.php` | GET/POST | Admin | Správa klíčů |
| `/api/protokol_api.php` | GET/POST | User/Admin | Servisní protokoly |
| `/api/backup_api.php` | GET/POST | Admin | DB zálohy |
| `/api/github_webhook.php` | POST | Webhook | GitHub události |

## 🐛 Debugging

Pro debugging API:
1. Zkontrolovat PHP error log
2. Použít browser DevTools Network tab
3. Zkontrolovat response format
4. Ověřit CSRF token

## 📚 Dokumentace

- API Standardization Guide: `/docs/API_STANDARDIZATION_GUIDE.md`
- Security Best Practices: `/REFACTORING_REPORT.md`
- Complete Audit Summary: `/FINAL_AUDIT_SUMMARY.md`
