# API Response Standardization Guide

**Status:** HIGH PRIORITY
**Created:** 2025-11-14
**Version:** 1.0

## Problem

Aktuálně projekt používá nekonzistentní formáty API responses:

```php
// Formát 1 (admin_api.php)
{
    "status": "success",
    "data": {...}
}

// Formát 2 (control_center_api.php)
{
    "success": true,
    "result": {...}
}

// Formát 3 (protokol_api.php)
{
    "status": "success",
    "reklamace": {...}
}
```

Tato nekonzistence způsobuje:
- Komplikované API konzumování
- Těžší error handling na frontendu
- Neintuitivní debugování
- Špatnou developer experience

## Solution

Nová `ApiResponse` třída poskytuje **jednotný formát** pro všechny API:

```php
// SUCCESS Response
{
    "status": "success",
    "data": {...},           // data payload
    "message": "...",        // optional human-readable message
    "meta": {...}            // optional metadata (pagination, etc.)
}

// ERROR Response
{
    "status": "error",
    "message": "...",        // user-friendly error message
    "error": {
        "code": "...",       // machine-readable error code
        "message": "...",    // same as root message
        "details": {...}     // optional error details
    }
}
```

## Migration

### Step 1: Include helper

```php
require_once __DIR__ . '/../includes/api_response.php';
```

### Step 2: Replace responses

**BEFORE:**
```php
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'data' => $results
]);
exit;
```

**AFTER:**
```php
ApiResponse::success($results);
```

---

**BEFORE:**
```php
http_response_code(400);
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid input'
]);
exit;
```

**AFTER:**
```php
ApiResponse::error('Invalid input', 400);
```

## API Methods

### Success Responses

```php
// Basic success
ApiResponse::success($data, $message, $meta);

// Created (201)
ApiResponse::created($data, $message, $location);

// Paginated
ApiResponse::paginated($items, $total, $page, $perPage, $message);

// No content (204)
ApiResponse::noContent();
```

### Error Responses

```php
// Generic error (400)
ApiResponse::error($message, $httpCode, $details, $errorCode);

// Not found (404)
ApiResponse::notFound('User', 123);

// Validation error (422)
ApiResponse::validationError([
    'email' => 'Email je povinný',
    'password' => 'Heslo musí mít alespoň 8 znaků'
]);

// Unauthorized (401)
ApiResponse::unauthorized();

// Forbidden (403)
ApiResponse::forbidden();

// Rate limit (429)
ApiResponse::rateLimitExceeded(60, 'Příliš mnoho pokusů');

// Server error (500)
ApiResponse::serverError($message, $debug);
```

## Usage Examples

### Example 1: Simple success

```php
<?php
require_once __DIR__ . '/includes/api_response.php';

// ... business logic ...

ApiResponse::success([
    'id' => 123,
    'name' => 'Jan Novák',
    'email' => 'jan@example.com'
], 'Uživatel načten úspěšně');
```

### Example 2: Validation errors

```php
<?php
require_once __DIR__ . '/includes/api_response.php';

$errors = [];

if (empty($_POST['email'])) {
    $errors['email'] = 'Email je povinný';
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

// ... continue if valid ...
```

### Example 3: Pagination

```php
<?php
require_once __DIR__ . '/includes/api_response.php';

$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$total = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, name, email FROM users LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

ApiResponse::paginated($users, $total, $page, $perPage);
```

### Example 4: Resource not found

```php
<?php
require_once __DIR__ . '/includes/api_response.php';

$userId = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    ApiResponse::notFound('User', $userId);
}

ApiResponse::success($user);
```

## Migration Checklist

Soubory k migraci (seřazeno podle priority):

### HIGH Priority (API endpoints)
- [ ] `api/control_center_api.php` (4 responses)
- [ ] `api/admin_api.php` (multiple responses)
- [ ] `admin_api.php` (root level)
- [ ] `api/protokol_api.php` (multiple responses)
- [ ] `api/backup_api.php` (multiple responses)
- [ ] `api/github_webhook.php`

### MEDIUM Priority
- [ ] `api/notification_list_direct.php`
- [ ] `api/notification_api.php`
- [ ] `api/customer_api.php`

### LOW Priority (internal)
- [ ] `app/controllers/*.php` (postupně)

## Testing

Po migraci každého API souboru:

1. **Unit test** - Zkontrolovat response formát
2. **Integration test** - Otestovat frontend integraci
3. **Error cases** - Otestovat všechny error scénáře
4. **Backward compatibility** - Zkontrolovat že staré klienty fungují

## Benefits

✅ **Konzistentní API** - Všechny endpointy používají stejný formát
✅ **Lepší DX** - Snadnější konzumace API
✅ **Type safety** - Frontend může mít typed responses
✅ **Debugovatelnost** - Machine-readable error codes
✅ **Dokumentace** - Jednotný standard pro API docs
✅ **Testování** - Snazší testování responses

## Version 2.0 Ideas

- JSON Schema validation
- OpenAPI/Swagger integration
- Automatic API documentation generation
- Response caching layer
- GraphQL compatibility layer
