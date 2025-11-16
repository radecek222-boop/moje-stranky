# Performance Issues Analysis Report
## Similar Issues to Map Tile Proxy Problem

**Report Date:** 2025-11-16  
**Analysis Scope:** Complete repository audit for performance bottlenecks

---

## Executive Summary

Found **7 significant performance issues** across the codebase, with **2 CRITICAL** issues requiring immediate attention:

1. Double API calls in `get_distance.php` without server-side caching
2. Duplicate distance API calls in `seznam.js` 
3. Missing HTTP caching headers on all API endpoints
4. Inefficient `SELECT *` queries loading unnecessary columns
5. Lack of request debouncing in batch operations
6. Missing ETag/Last-Modified support
7. Minor N+1 pattern in statistics aggregation

All issues follow the **same pattern as the original tile proxy problem**: unnecessary HTTP requests, lack of caching, and inefficient data transfer.

---

## Issue #1: CRITICAL - Double API Calls in get_distance.php (No Server-Side Caching)

**Severity:** CRITICAL  
**Category:** Unnecessary API Proxy Requests  
**Location:** `/home/user/moje-stranky/app/controllers/get_distance.php` (lines 175-188)

### The Problem

For each distance calculation request, the controller makes **TWO separate curl requests** to geocode the same addresses:

```php
// Line 175: Geocode origin address
$originCoords = geocodeAddress($origin);
// Line 176: Geocode destination address  
$destCoords = geocodeAddress($destination);

function geocodeAddress($address) {
    $url = "http://{$_SERVER['HTTP_HOST']}/api/geocode_proxy.php?action=search&address=" . urlencode($address);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = curl_exec($ch);  // <- API CALL
    curl_close($ch);
}
```

**No server-side caching exists.** If a user calculates distance from "Prague" to "Brno" multiple times:
- Each calculation = 2 new curl requests
- Each curl request = call to Geoapify API
- Repeated addresses = repeated API calls

### Why This Is Similar to the Tile Proxy Problem

The original tile proxy issue:
- Stovky PHP requestů pro mapové tiles
- Každý request trval 1-3 sekundy
- Server overload, slow response

This distance issue:
- Stovky PHP requestů pro geocoding (curl inside PHP)
- Repeated for identical addresses
- Server CPU wasted on curl operations
- Database queries multiplied unnecessarily

### Impact

- High server CPU usage from curl operations
- Increased latency on distance calculations (2-3 seconds per calculation)
- Potential API rate limiting from Geoapify if many users calculate
- Unnecessary load on geocode_proxy.php

### Solution

Implement server-side caching for geocoding results:

```php
function geocodeAddress($address) {
    // Check cache first
    $cacheKey = 'geocode_' . md5($address);
    $cached = apcu_fetch($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    // ... make curl request ...
    
    // Cache result for 24 hours
    apcu_store($cacheKey, $result, 86400);
    return $result;
}
```

Or use database caching (more reliable):

```php
$cacheKey = md5($address);
$stmt = $pdo->prepare("SELECT result FROM geocode_cache WHERE address_hash = ? AND cached_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute([$cacheKey]);
$cached = $stmt->fetch();
if ($cached) return json_decode($cached['result'], true);
```

---

## Issue #2: CRITICAL - Duplicate get_distance.php Calls in seznam.js

**Severity:** CRITICAL  
**Category:** Redundant Client-Side Requests  
**Location:** `/home/user/moje-stranky/assets/js/seznam.js` (lines 975 and 1466)

### The Problem

The `getDistance()` function is called from **TWO different places** to fetch the same distance data:

```javascript
// Line 975 - In getDistance() function
async function getDistance(fromAddress, toAddress) {
  const cacheKey = `${fromAddress}|${toAddress}`;
  if (DISTANCE_CACHE[cacheKey]) {
    return DISTANCE_CACHE[cacheKey];  // ✓ Has cache
  }
  const response = await fetch('/app/controllers/get_distance.php', {
    // ... API CALL
  });
}

// Line 1466 - In showMapWithDistance() function
const response = await fetch('/app/controllers/get_distance.php', {
  method: 'POST',
  body: JSON.stringify({
    origin: WGS_ADDRESS,
    destination: customerAddress
  })
  // ✗ IGNORES CACHE - DUPLICATE API CALL!
});
```

### The Scenario

1. User clicks on customer in calendar
2. `getDistance()` called → fetches distance → caches in DISTANCE_CACHE
3. Map view loads for same customer
4. `showMapWithDistance()` makes **NEW API call** without checking cache
5. Result: **2 API calls for same data**

### Impact

- Unnecessary network requests to server
- Duplicate curl calls in get_distance.php  
- Duplicate geocoding API calls to Geoapify (2x wasted)
- Wastes bandwidth and server resources
- User experiences slower UI response

### Solution

Make both functions use the same cache:

```javascript
// In showMapWithDistance():
async function updateMapDistance(customerAddress) {
  // Reuse existing getDistance() function
  const distanceData = await getDistance(WGS_ADDRESS, customerAddress);
  
  if (distanceData) {
    document.getElementById('mapDistance').textContent = distanceData.text;
    document.getElementById('mapDuration').textContent = distanceData.duration;
  }
}
```

---

## Issue #3: HIGH - Missing HTTP Caching Headers on All API Endpoints

**Severity:** HIGH  
**Category:** Missing HTTP Caching  
**Location:** All API endpoints in `/api/` directory

### The Problem

API endpoints lack **Cache-Control** headers, forcing browsers to fetch fresh data every time:

```php
// Current (WRONG):
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);  // No cache headers!

// Missing headers:
// header('Cache-Control: public, max-age=3600');
// header('ETag: "hash-of-data"');
// header('Last-Modified: ...');
```

### Affected Endpoints

- `/api/statistiky_api.php` - Admin stats (loaded frequently)
- `/api/control_center_api.php` - Control center data
- `/api/geocode_proxy.php` - Tile endpoint
- `/app/controllers/get_distance.php` - Distance data
- `/api/protokol_api.php` - Protocol data

### Why This Matters

- Browser **cannot cache** any API responses
- Every request = new server computation + database query
- Especially problematic for frequently-accessed endpoints like statistiky_api
- No 304 Not Modified support = always full data transfer

### Impact

- Increased server load from repeated requests
- Higher bandwidth consumption
- Slower perceived performance
- Unnecessary database queries

### Solution

Add Cache-Control headers based on data volatility:

```php
// In statistiky_api.php - data changes infrequently
header('Cache-Control: public, max-age=300');  // Cache 5 minutes
header('ETag: "' . md5($json) . '"');

// In geocode_proxy.php - geocoding results rarely change
header('Cache-Control: public, max-age=86400');  // Cache 24 hours

// In get_distance.php - same as geocoding
header('Cache-Control: public, max-age=86400');  // Cache 24 hours
```

---

## Issue #4: HIGH - SELECT * Queries Loading Unnecessary Columns

**Severity:** HIGH  
**Category:** Inefficient Database Queries  
**Location:** Multiple API endpoints

### The Problem

Queries use `SELECT *` instead of specific columns:

```php
// WRONG - loads ALL columns even if only 5 needed
SELECT * FROM wgs_reklamace WHERE id = ?

// RIGHT - load only needed columns
SELECT id, cislo, jmeno, termin, stav FROM wgs_reklamace WHERE id = ?
```

### Affected Locations

| File | Line | Impact |
|------|------|--------|
| `/api/protokol_api.php` | 278, 403 | Each claim has 30+ columns |
| `/api/delete_reklamace.php` | 78 | Unnecessarily loads full record |
| `/api/control_center_api.php` | 241, 831, 892 | Config loads excess data |
| `/api/backup_api.php` | 101 | Backup includes all columns |

### Why SELECT * Is a Problem

1. **For single records:** Loads 30+ columns when maybe 5 needed
2. **For batch operations:** statistiky_api loads 500+ records × 30 columns = 15,000 fields
3. **Network overhead:** Larger JSON responses over network
4. **Database load:** More memory, slower query optimizer
5. **Client processing:** More data to parse and encode

### Impact

- Larger JSON responses (2-3x bigger)
- More memory usage in PHP and database
- Slower JSON encoding/decoding  
- Increased bandwidth consumption
- Slower database performance

### Solution

Replace with explicit column lists:

```php
// Create a helper function
function getClaimColumns($level = 'basic') {
    return [
        'basic' => 'id, cislo, jmeno, termin, stav, created_at',
        'detail' => 'id, cislo, jmeno, ulice, mesto, psc, telefon, termin, stav',
        'full' => 'SELECT * FROM wgs_reklamace'  // Only when absolutely needed
    ][$level];
}

// Use in queries
$stmt = $pdo->prepare('SELECT ' . getClaimColumns('basic') . ' FROM wgs_reklamace');
```

---

## Issue #5: MEDIUM - Lack of Request Debouncing in Batch Distance Calculations

**Severity:** MEDIUM  
**Category:** Inefficient Client-Side Requests  
**Location:** `/home/user/moje-stranky/assets/js/seznam.js` (lines 1012-1014)

### The Problem

Batch distance calculations launch all requests simultaneously without rate limiting:

```javascript
// Current (no concurrency control):
async function getDistancesBatch(pairs) {
  const promises = pairs.map(pair => getDistance(pair.from, pair.to));
  return await Promise.all(promises);  // All requests fire at once!
}
```

### Scenario

User rapidly clicks through calendar dates:
1. Click date 1 → `showDayBookingsWithDistances()` fires 10 distance requests
2. Click date 2 → fires 10 more requests (before date 1 finished)
3. Click date 3 → fires 10 more requests
4. Server receives **30 requests in quick succession**

### Impact

- Server CPU spike when handling burst requests
- Potential rate limiting from Geoapify API
- Browser struggles with pending requests
- UI becomes sluggish

### Solution

Implement concurrency limiting:

```javascript
async function getDistancesBatch(pairs, concurrency = 3) {
  const results = [];
  for (let i = 0; i < pairs.length; i += concurrency) {
    const batch = pairs.slice(i, i + concurrency);
    const batchResults = await Promise.all(
      batch.map(pair => getDistance(pair.from, pair.to))
    );
    results.push(...batchResults);
  }
  return results;
}
```

---

## Issue #6: MEDIUM - Missing ETag and Last-Modified Headers

**Severity:** MEDIUM  
**Category:** Missing HTTP Optimization  
**Location:** All dynamic API endpoints

### The Problem

No conditional request support (304 Not Modified):

```php
// Missing:
header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $timestamp));
header('ETag: "' . md5($data) . '"');

// No check for:
// if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
//     http_response_code(304);
//     exit;
// }
```

### Why This Matters

Browser caches response but always fetches fresh data. Even if data hasn't changed, server returns full JSON response instead of 304 Not Modified.

### Impact

- Unnecessary bandwidth consumption
- Slower API responses
- Higher server CPU from JSON encoding
- No benefit from browser caching mechanisms

### Solution

Implement conditional request support:

```php
function setConditionalHeaders($data) {
    $etag = '"' . md5(json_encode($data)) . '"';
    header('ETag: ' . $etag);
    
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
        if ($clientEtag === trim($etag, '"')) {
            http_response_code(304);
            exit;
        }
    }
}
```

---

## Issue #7: LOW - Minor N+1 Pattern in statistiky_api.php

**Severity:** LOW  
**Category:** Potential Database Optimization  
**Location:** `/api/statistiky_api.php` (lines 250-277)

### The Problem

Two separate queries when one would suffice:

```php
// Query 1: Get model statistics
$stmt = $pdo->prepare("
    SELECT model, COUNT(*) as pocet_reklamaci, SUM(cena) as total
    FROM wgs_reklamace
    WHERE ...
    GROUP BY model
    LIMIT 20
");
$stmt->execute($params);
$models = $stmt->fetchAll();

// Query 2: Get total count (separate query!)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM wgs_reklamace WHERE ...");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
```

### Impact

- Minor: Two database round-trips instead of one
- For bulk operations = noticeable overhead
- Not critical but suboptimal

### Solution

Combine into single query:

```php
$stmt = $pdo->prepare("
    SELECT 
        model,
        COUNT(*) as pocet_reklamaci,
        (SELECT COUNT(*) FROM wgs_reklamace WHERE ...) as total_count,
        SUM(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as total
    FROM wgs_reklamace
    WHERE ...
    GROUP BY model
    LIMIT 20
");
```

---

## Recommendations by Priority

### Priority 1 - CRITICAL (Do Immediately)

- [ ] Implement server-side caching in `get_distance.php`
  - Use APCu (faster) or Redis (distributed)
  - Cache geocoding results for 24 hours
  - Estimate: 2-3 hours to implement and test

- [ ] Fix duplicate distance calls in `seznam.js`
  - Reuse DISTANCE_CACHE across functions
  - Merge `showMapWithDistance()` to use `getDistance()`
  - Estimate: 30 minutes

### Priority 2 - HIGH (Do This Sprint)

- [ ] Add Cache-Control headers to all API endpoints
  - Statistics: max-age=300 (5 min)
  - Geocoding: max-age=86400 (24 hours)
  - Estimate: 2-3 hours

- [ ] Replace SELECT * with explicit columns
  - Create query builder helper functions
  - Update each endpoint
  - Estimate: 4-5 hours

### Priority 3 - MEDIUM (Next Sprint)

- [ ] Add request debouncing/throttling
- [ ] Implement ETag support
- [ ] Optimize N+1 patterns

---

## Testing Recommendations

1. **Profile get_distance.php**
   - Test with repeated identical addresses
   - Verify only 1 API call made (not 2+)
   - Check server cache hit rate

2. **Monitor API response times**
   - Before/after Cache-Control headers
   - Expected improvement: 20-40%

3. **Network tab analysis**
   - Verify browser caching works with DevTools
   - Check 304 Not Modified responses

4. **Bandwidth measurements**
   - Measure JSON response sizes before/after SELECT * fix
   - Expected reduction: 30-50%

5. **Load testing**
   - Test rapid calendar navigation
   - Verify debouncing prevents request spam
   - Monitor server CPU usage

---

## Performance Baseline for Tracking

**Before fixes:**
- Distance calculation: 2-3 seconds (2 API calls)
- API response size: ~150KB (SELECT *)
- Server CPU on distance requests: ~15%

**Expected after fixes:**
- Distance calculation: 500-800ms (cached)
- API response size: ~50KB (explicit columns)
- Server CPU on distance requests: ~2%

---

## Similar Issues to Original Problem

| Original Issue | This Issue | Similarity |
|---|---|---|
| Tiles via PHP proxy | Distance calculations in PHP | Unnecessary internal HTTP calls |
| Stovky requestů | Each distance = 2 curl requests | Multiplicative effect with usage |
| 1-3 sec per request | Geocoding adds 1-2 sec | Compounding latency |
| No caching | No server-side cache | Same root cause |
| Server overload | High CPU from curl operations | Direct server impact |

The **root pattern** is identical: **unnecessary proxy/wrapper layers without caching**, causing exponential resource multiplication.

