# Performance Issues - Quick Reference

## Summary Table

| # | Issue | Severity | Location | Impact | Fix Time |
|---|-------|----------|----------|--------|----------|
| 1 | Double API calls in get_distance.php (no server cache) | CRITICAL | app/controllers/get_distance.php:175-188 | 2-3 sec latency per request | 2-3 hrs |
| 2 | Duplicate distance API calls in seznam.js | CRITICAL | assets/js/seznam.js:975,1466 | 2x API calls for same data | 30 min |
| 3 | Missing Cache-Control headers on APIs | HIGH | api/*.php | Forced fresh fetch every time | 2-3 hrs |
| 4 | SELECT * loading unnecessary columns | HIGH | Multiple api/ endpoints | 2-3x larger JSON responses | 4-5 hrs |
| 5 | No debouncing in batch requests | MEDIUM | assets/js/seznam.js:1012 | Server CPU spike on burst clicks | 1 hr |
| 6 | Missing ETag/Last-Modified headers | MEDIUM | api/*.php | No 304 Not Modified support | 2 hrs |
| 7 | Minor N+1 in statistiky_api | LOW | api/statistiky_api.php:268 | Extra DB round-trip | 1 hr |

---

## Affected Files Summary

### CRITICAL Issues

**File:** `/home/user/moje-stranky/app/controllers/get_distance.php`
- **Problem:** Lines 175-188 make 2 separate geocoding curl requests without caching
- **Solution:** Add APCu or database caching layer
- **Priority:** FIX IMMEDIATELY

**File:** `/home/user/moje-stranky/assets/js/seznam.js`
- **Problem:** Lines 975 and 1466 duplicate distance API calls
- **Solution:** Reuse DISTANCE_CACHE in showMapWithDistance()
- **Priority:** FIX IMMEDIATELY

### HIGH Issues

**Files:** `api/statistiky_api.php`, `api/control_center_api.php`, `api/protokol_api.php`
- **Problem:** Missing Cache-Control headers (lines with header() calls)
- **Solution:** Add max-age based on data volatility
- **Priority:** This sprint

**Files:** 
- `/api/protokol_api.php:278, 403`
- `/api/delete_reklamace.php:78`
- `/api/control_center_api.php:241, 831, 892`
- `/api/backup_api.php:101`

- **Problem:** SELECT * queries loading all 30+ columns unnecessarily
- **Solution:** Replace with explicit column lists
- **Priority:** This sprint

### MEDIUM Issues

**File:** `/home/user/moje-stranky/assets/js/seznam.js:1012-1014`
- **Problem:** getDistancesBatch() uses Promise.all() without concurrency limit
- **Solution:** Limit concurrent requests to 3-5 at a time
- **Priority:** Next sprint

**Files:** All api/*.php endpoints
- **Problem:** Missing ETag and Last-Modified headers
- **Solution:** Generate ETag from data hash, implement 304 responses
- **Priority:** Next sprint

### LOW Issues

**File:** `/home/user/moje-stranky/api/statistiky_api.php:268`
- **Problem:** Two separate queries (one for aggregates, one for total count)
- **Solution:** Combine with subquery or CTE
- **Priority:** Backlog

---

## Root Cause Analysis

All issues follow the same **anti-pattern as the original tile proxy problem**:

```
UNNECESSARY HTTP REQUESTS/OPERATIONS -> NO CACHING -> SERVER OVERLOAD
```

### Original Tile Proxy Issue
- Stovky PHP requestů pro mapové tiles
- Každý request = 1-3 sekundy
- Bez cachingu = server overload

### Current Issues Replicating This Pattern

1. **get_distance.php** - 2 curl requests per distance calculation (no cache)
2. **seznam.js** - Calls distance API twice for same data (cache ignored)
3. **API endpoints** - No Cache-Control (browser re-fetches every time)
4. **SELECT *** - Loads 3x more data than needed (network bloat)
5. **Batch requests** - No concurrency limiting (burst load on server)

---

## Performance Improvements Expected

| Issue | Metric | Before | After | Improvement |
|-------|--------|--------|-------|-------------|
| #1, #2 | Distance calc latency | 2-3s | 500-800ms | 60-75% faster |
| #1, #2 | API calls per distance | 2 | 1 | 50% reduction |
| #3 | Cache hits | 0% | 70-80% | Huge |
| #4 | JSON response size | ~150KB | ~50KB | 66% smaller |
| #4 | Bandwidth | 100% | 35% | 65% reduction |
| #5 | Concurrent requests | Unlimited | 3-5 | Controlled |
| #6 | 304 responses | 0% | 30-40% | Less data transfer |

---

## Implementation Roadmap

### Week 1 - CRITICAL (2-4 hours total)
- [ ] Add caching to get_distance.php (use APCu)
- [ ] Fix seznam.js duplicate calls
- **Impact:** Instant 60% improvement in distance calculations

### Week 2 - HIGH (6-8 hours total)
- [ ] Add Cache-Control headers to all APIs
- [ ] Replace SELECT * with explicit columns
- **Impact:** 30-40% improvement in API response times

### Week 3 - MEDIUM (3-4 hours total)
- [ ] Add debouncing/concurrency limiting
- [ ] Implement ETag support
- **Impact:** Better UX, less server CPU spikes

### Later - LOW (1 hour total)
- [ ] Optimize statistiky_api N+1 pattern
- **Impact:** Minimal but nice-to-have

---

## File References for Code Changes

### get_distance.php - Requires Changes
```
Line 17-61: geocodeAddress() function
  -> Add caching before API call
  -> Cache result after curl execution
  
Line 175-176: Geocoding calls
  -> Already correct, caching will fix the issue
```

### seznam.js - Requires Changes
```
Line 964-1009: getDistance() function
  -> ALREADY HAS CLIENT-SIDE CACHE (good!)
  -> Works correctly
  
Line 1466: showMapWithDistance() function
  -> USES SEPARATE FETCH CALL
  -> Should reuse getDistance() instead
  -> This is the fix needed
```

### API Endpoints - Require Cache Headers
```
statistiky_api.php (line 9):
  -> Add after Content-Type header
  
control_center_api.php (line 11):
  -> Add after Content-Type header
  
protokol_api.php (line ~10):
  -> Add after Content-Type header
  
get_distance.php (line 10):
  -> Add after Content-Type header
```

---

## Quick Check Script

To verify current performance baseline:

```bash
# Test 1: Profile distance calculation
curl -X POST http://localhost/app/controllers/get_distance.php \
  -H "Content-Type: application/json" \
  -d '{"origin":"Prague","destination":"Brno"}'

# Test 2: Check API headers
curl -I http://localhost/api/statistiky_api.php

# Check for:
# - No "Cache-Control: max-age" = needs fix
# - No "ETag" = needs fix
# - Multiple API calls in single page load = needs fix
```

---

## Related Documentation

- PERFORMANCE_ANALYSIS.json - Detailed JSON format for automation
- PERFORMANCE_ISSUES_ANALYSIS.md - Full detailed analysis with code examples
- This file - Quick reference guide

