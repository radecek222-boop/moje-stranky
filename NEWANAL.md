# NEWANAL – Enterprise Analytics System Documentation

**Version:** 1.7.0
**Last Updated:** 2025-11-23
**Project:** WGS Enterprise Analytics System
**Status:** Modules #1-8 Complete, Modules #9-13 Pending

---

## 📋 TABLE OF CONTENTS

1. [Executive Summary](#executive-summary)
2. [Architecture Overview](#architecture-overview)
3. [Database Layer - ERD & Structure](#database-layer)
4. [API Layer - Endpoints & Contracts](#api-layer)
5. [Frontend Tracking Architecture](#frontend-tracking-architecture)
6. [Backend Business Logic Layer](#backend-business-logic-layer)
7. [Admin UI Modules](#admin-ui-modules)
8. [Security & GDPR Compliance](#security-gdpr-compliance)
9. [Performance & Optimization](#performance-optimization)
10. [Deployment & Cron Jobs](#deployment-cron-jobs)
11. [Module Implementation Plan](#module-implementation-plan)
12. [Workflow & Rules](#workflow-rules)
13. [Backward Compatibility](#backward-compatibility)
14. [Testing Strategy](#testing-strategy)
15. [Project Status](#project-status)

---

## 1. EXECUTIVE SUMMARY

### Purpose

The **Enterprise Analytics System** is a full-scale web analytics platform comparable to Google Analytics 4, Matomo, Microsoft Clarity, Hotjar, and Plausible. It provides:

- Device fingerprinting for cross-session tracking
- Bot detection with AI heuristics
- Real-time visitor tracking
- Session replay with mouse/click/scroll recording
- Click and scroll heatmaps
- UTM campaign tracking and attribution
- Conversion funnel analysis
- User engagement and frustration scoring
- Geolocation with IP-based city/country detection
- GDPR-compliant data management
- AI-generated daily/weekly reports

### Key Features

| Feature | Status | Module |
|---------|--------|--------|
| Device Fingerprinting | ✅ Complete | Module #1 |
| Advanced Session Tracking | ✅ Complete | Module #2 |
| Bot Detection & Security | ✅ Complete | Module #3 |
| Geolocation Engine | ✅ Complete | Module #4 |
| Event Tracking | ✅ Complete | Module #5 |
| Heatmaps (Click & Scroll) | ✅ Complete | Module #6 |
| Session Replay | ✅ Complete | Module #7 |
| UTM Campaign Tracking | ✅ Complete | Module #8 |
| Conversion Funnels | ⏳ Pending | Module #9 |
| User Interest AI Scoring | ⏳ Pending | Module #10 |
| Real-time Dashboard | ⏳ Pending | Module #11 |
| AI Reports Engine | ⏳ Pending | Module #12 |
| GDPR Compliance Tools | ⏳ Pending | Module #13 |

### Technology Stack

- **Backend:** PHP 8.4+, PDO, MariaDB 10.11+
- **Frontend:** Vanilla JavaScript (ES6+), no frameworks
- **Database:** MariaDB with InnoDB, JSON columns
- **Server:** Nginx 1.26+ (with Apache fallback)
- **Tracking:** Beacon API, Fetch API
- **Visualization:** Chart.js for graphs
- **Security:** CSRF tokens, rate limiting, prepared statements
- **GDPR:** SHA-256 hashing, pseudonymization, consent management

---

## 2. ARCHITECTURE OVERVIEW

### System Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN UI LAYER (Czech Labels)                │
│  - Real-time Dashboard                                          │
│  - Heatmap Viewer                                               │
│  - Session Replay Player                                        │
│  - Reports & Analytics                                          │
│  - Bot Detection Console                                        │
│  - GDPR Compliance Panel                                        │
└─────────────────────────────────────────────────────────────────┘
                               ↕
┌─────────────────────────────────────────────────────────────────┐
│                    API LAYER (RESTful PHP)                      │
│  - Track V2 API (pageviews + sessions + fingerprints)          │
│  - Event API (clicks, scroll, rage, copy/paste)                │
│  - Replay API (session recording frames)                       │
│  - Heatmap API (click/scroll data aggregation)                 │
│  - Analytics API (read queries for dashboards)                 │
│  - Reports API (AI-generated insights)                         │
│  - GDPR API (consent, export, delete)                          │
└─────────────────────────────────────────────────────────────────┘
                               ↕
┌─────────────────────────────────────────────────────────────────┐
│                   BUSINESS LOGIC LAYER (PHP)                    │
│  - FingerprintEngine (device identification)                   │
│  - BotDetector (AI heuristics + ML scoring)                    │
│  - GeolocationService (IP → Location with caching)             │
│  - SessionMerger (cross-session stitching)                     │
│  - UserScoreCalculator (engagement/frustration/interest)       │
│  - CampaignAttribution (UTM multi-touch attribution)           │
│  - ConversionFunnel (goal tracking)                            │
│  - AIReportGenerator (automated insights)                      │
└─────────────────────────────────────────────────────────────────┘
                               ↕
┌─────────────────────────────────────────────────────────────────┐
│                    DATA LAYER (MariaDB)                         │
│  - 14 database tables                                           │
│  - JSON columns for flexibility                                │
│  - 50+ indexes for performance                                 │
│  - Auto-cleanup with TTL policies                              │
└─────────────────────────────────────────────────────────────────┘
                               ↕
┌─────────────────────────────────────────────────────────────────┐
│                   CLIENT TRACKING LAYER (JS)                    │
│  - tracker-v2.js (main orchestrator)                           │
│  - fingerprint-module.js (device fingerprinting)               │
│  - event-tracker.js (user interactions)                        │
│  - replay-recorder.js (session recording)                      │
│  - GDPR consent manager                                        │
│  - LocalStorage / SessionStorage                               │
│  - Beacon API / Fetch API                                      │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
User Visit → tracker-v2.js loads
          ↓
Generate Fingerprint (canvas, WebGL, audio)
          ↓
Send to /api/fingerprint_store.php
          ↓
FingerprintEngine stores in DB
          ↓
Track pageview → /api/track_v2.php
          ↓
Track events (clicks, scroll) → /api/track_event.php
          ↓
Record session replay → /api/track_replay.php
          ↓
Send heatmap data → /api/track_heatmap.php
          ↓
Store in wgs_analytics_* tables
          ↓
Admin views dashboards → /api/analytics_*.php
          ↓
Display real-time metrics, heatmaps, replays
```

---

## 3. DATABASE LAYER

### Entity-Relationship Diagram (ERD)

```
┌──────────────────────────┐
│  wgs_pageviews          │──────┐
│  (Enhanced existing)    │      │
├──────────────────────────┤      │
│ PK: id                  │      │
│ UK: -                   │      │
│ FK: fingerprint_id      │──┐   │
│ + session_id            │  │   │
│ + engagement_score      │  │   │
│ + frustration_score     │  │   │
│ + scroll_depth          │  │   │
│ + click_count           │  │   │
│ + utm_*                 │  │   │
│ + entry_page, exit_page │  │   │
└──────────────────────────┘  │   │
                              │   │
                              │   │ 1:N
                              │   ↓
┌──────────────────────────┐  │ ┌──────────────────────────┐
│ wgs_analytics_          │  │ │ wgs_analytics_sessions  │
│ fingerprints            │←─┘ ├──────────────────────────┤
├──────────────────────────┤    │ PK: id                  │
│ PK: id                  │    │ UK: session_id          │
│ UK: fingerprint_id      │    │ FK: fingerprint_id      │
│ + canvas_hash           │    │ + entry_page, exit_page │
│ + webgl_vendor/renderer │    │ + engagement_score      │
│ + audio_hash            │    │ + is_bot, bot_score     │
│ + timezone, screen_*    │    │ + utm_* (campaign)      │
│ + first_seen, last_seen │    │ + conversion tracking   │
│ + session_count         │    └──────────────────────────┘
│ + device_map (JSON)     │
└──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│ wgs_analytics_events    │    │ wgs_analytics_          │
├──────────────────────────┤    │ heatmap_clicks          │
│ PK: id (BIGINT)         │    ├──────────────────────────┤
│ FK: session_id          │    │ PK: id                  │
│ + event_type            │    │ + page_url              │
│ + x, y position         │    │ + x_percent, y_percent  │
│ + element_selector      │    │ + viewport dimensions   │
│ + timestamp (ms)        │    │ + device_type           │
│ + event_data (JSON)     │    └──────────────────────────┘
└──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│ wgs_analytics_          │    │ wgs_analytics_          │
│ heatmap_scroll          │    │ replay_frames           │
├──────────────────────────┤    ├──────────────────────────┤
│ PK: id                  │    │ PK: id (BIGINT)         │
│ + page_url              │    │ FK: session_id          │
│ + scroll_depth_percent  │    │ + frame_index           │
│ + page_height           │    │ + timestamp_offset (ms) │
│ + device_type           │    │ + event_type            │
└──────────────────────────┘    │ + data (JSON)           │
                               └──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│ wgs_analytics_          │    │ wgs_analytics_          │
│ utm_campaigns           │    │ conversions             │
├──────────────────────────┤    ├──────────────────────────┤
│ PK: id                  │    │ PK: id                  │
│ UK: (source, medium,    │    │ FK: session_id          │
│     campaign, ...)      │    │ + conversion_type       │
│ + visit_count           │    │ + conversion_value      │
│ + conversion_count      │    │ + conversion_path (JSON)│
│ + conversion_rate       │    │ + utm_*                 │
│ + total_revenue         │    │ + time_to_conversion    │
└──────────────────────────┘    └──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│ wgs_analytics_          │    │ wgs_analytics_          │
│ bot_detections          │    │ geolocation_cache       │
├──────────────────────────┤    ├──────────────────────────┤
│ PK: id                  │    │ PK: id                  │
│ FK: session_id          │    │ UK: ip_address          │
│ + ip_address            │    │ + country, city         │
│ + bot_score (0-100)     │    │ + lat, lng              │
│ + bot_type              │    │ + isp, asn              │
│ + threat_level (enum)   │    │ + is_vpn, is_datacenter │
│ + detection_reasons     │    │ + expires_at (3-day TTL)│
│ + is_vpn, is_proxy,     │    └──────────────────────────┘
│   is_tor, is_datacenter │
│ + anomaly_flags (JSON)  │
└──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│ wgs_analytics_          │    │ wgs_analytics_realtime  │
│ user_scores             │    ├──────────────────────────┤
├──────────────────────────┤    │ PK: id                  │
│ PK: id                  │    │ UK: session_id          │
│ FK: session_id          │    │ + page_url              │
│ + engagement_score      │    │ + is_bot                │
│ + frustration_score     │    │ + country, city         │
│ + interest_score        │    │ + last_activity         │
│ + reading_time          │    │ + expires_at (5min TTL) │
│ + click_quality         │    └──────────────────────────┘
│ + scroll_quality        │
└──────────────────────────┘

┌──────────────────────────┐
│ wgs_analytics_reports   │
├──────────────────────────┤
│ PK: id                  │
│ UK: (report_type, date) │
│ + summary (TEXT)        │
│ + metrics (JSON)        │
│ + trends (JSON)         │
│ + anomalies (JSON)      │
│ + predictions (JSON)    │
│ + generated_at          │
└──────────────────────────┘
```

### Database Tables Reference

| # | Table Name | Purpose | Estimated Rows/Day | TTL Policy |
|---|------------|---------|-------------------|------------|
| 1 | `wgs_pageviews` | Enhanced pageviews (existing + new columns) | 1K-10K | None |
| 2 | `wgs_analytics_fingerprints` | Device fingerprints (canvas, WebGL, audio) | 50-500 unique | None |
| 3 | `wgs_analytics_sessions` | Advanced session tracking | 500-5K | None |
| 4 | `wgs_analytics_events` | User events (click, scroll, rage, copy/paste) | 10K-100K | 90 days → aggregate |
| 5 | `wgs_analytics_heatmap_clicks` | Click heatmap coordinates | 5K-50K | None |
| 6 | `wgs_analytics_heatmap_scroll` | Scroll depth data | 1K-10K | None |
| 7 | `wgs_analytics_replay_frames` | Session replay frames | 50K-500K | 30 days |
| 8 | `wgs_analytics_utm_campaigns` | UTM campaign aggregation | 10-100 unique | None |
| 9 | `wgs_analytics_conversions` | Conversion tracking | 10-1K | None |
| 10 | `wgs_analytics_bot_detections` | Bot detection logs | 100-1K | None |
| 11 | `wgs_analytics_geolocation_cache` | IP geolocation cache | 100-1K unique | 3 days |
| 12 | `wgs_analytics_user_scores` | AI engagement/frustration scores | 1K-10K | None |
| 13 | `wgs_analytics_realtime` | Real-time active sessions | 10-100 active | 5 minutes |
| 14 | `wgs_analytics_reports` | AI-generated reports | 1/day | None |

### Schema Details

See `migrace_module1_fingerprinting.php` for Module #1 schema example.

All tables use:
- **Engine:** InnoDB
- **Charset:** utf8mb4
- **Collation:** utf8mb4_unicode_ci
- **Auto-increment:** PRIMARY KEY on `id`
- **Timestamps:** `created_at`, `last_seen`, or `timestamp`
- **JSON columns:** For flexible data storage (device_map, event_data, etc.)
- **Indexes:** On all foreign keys, timestamps, and frequently queried columns

---

## 4. API LAYER

### Tracking APIs (Write Operations)

| Endpoint | Method | Purpose | Payload | Response |
|----------|--------|---------|---------|----------|
| `/api/fingerprint_store.php` | POST | Store device fingerprint | `{session_id, fingerprint_components, user_agent}` | `{fingerprint_id, is_new, session_count}` |
| `/api/track_v2.php` | POST | Pageview + session tracking | `{session_id, fingerprint_id, page_url, utm_*, device_type, geolocation}` | `{status: success}` |
| `/api/track_event.php` | POST | Event tracking (click, scroll, rage) | `{session_id, event_type, x, y, element_selector, event_data}` | `{status: success}` |
| `/api/track_replay.php` | POST | Session replay frame batch | `{session_id, frames[]}` | `{status: success}` |
| `/api/track_heatmap.php` | POST | Heatmap click/scroll data | `{session_id, page_url, x_percent, y_percent, scroll_depth}` | `{status: success}` |
| `/api/track_conversion.php` | POST | Conversion tracking | `{session_id, conversion_type, conversion_value, utm_*}` | `{status: success}` |

### Analytics APIs (Read Operations)

| Endpoint | Method | Purpose | Query Params | Response |
|----------|--------|---------|--------------|----------|
| `/api/analytics_dashboard.php` | GET | Real-time dashboard data | `period` (today/week/month) | `{stats, topPages, referrers, timeline}` |
| `/api/analytics_sessions.php` | GET | Session list & filters | `date_from, date_to, is_bot, country, device_type` | `{sessions[], count}` |
| `/api/analytics_heatmap.php` | GET | Heatmap data for page | `page_url, device_type, date_range` | `{clicks[], scrollDepth[]}` |
| `/api/analytics_replay.php` | GET | Session replay frames | `session_id` | `{frames[], duration}` |
| `/api/analytics_events.php` | GET | Event list & filtering | `session_id, event_type, page_url` | `{events[], count}` |
| `/api/analytics_bot_activity.php` | GET | Bot detection summary | `period, threat_level` | `{bots[], total_bot_score}` |
| `/api/analytics_campaigns.php` | GET | UTM campaign performance | `utm_campaign, date_range` | `{campaigns[], conversion_rate}` |
| `/api/analytics_conversions.php` | GET | Conversion funnel data | `conversion_type, date_range` | `{funnel[], drop_off}` |
| `/api/analytics_geolocation.php` | GET | Geographic distribution | `period` | `{countries[], cities[]}` |
| `/api/analytics_reports.php` | GET | AI-generated reports | `report_type, report_date` | `{summary, metrics, trends, anomalies}` |

### Admin APIs

| Endpoint | Method | Purpose | Params |
|----------|--------|---------|--------|
| `/api/admin_bot_whitelist.php` | POST | Add/remove bot whitelist | `{bot_signature, action}` |
| `/api/admin_ignored_ips.php` | POST | Manage ignored IPs | `{ip_address, action}` |
| `/api/admin_gdpr_export.php` | GET | Export user data (GDPR) | `fingerprint_id` or `session_id` |
| `/api/admin_gdpr_delete.php` | DELETE | Delete user data (GDPR) | `fingerprint_id` or `session_id` |
| `/api/admin_generate_report.php` | POST | Manually trigger AI report | `{report_type, date}` |

### GDPR APIs

| Endpoint | Method | Purpose | Params |
|----------|--------|---------|--------|
| `/api/gdpr_consent.php` | POST | Store consent decision | `{consent}` (granted/revoked) |
| `/api/gdpr_export_my_data.php` | GET | Export own data | `fingerprint_id` (from cookie) |
| `/api/gdpr_delete_my_data.php` | DELETE | Request data deletion | `fingerprint_id` (from cookie) |

### API Security

All APIs implement:
- ✅ **CSRF Token Validation** - Required on all POST/PUT/DELETE
- ✅ **Rate Limiting** - Max requests per hour per session/IP
- ✅ **Input Validation** - Strict type checking and sanitization
- ✅ **PDO Prepared Statements** - SQL injection prevention
- ✅ **JSON-only Responses** - No HTML output
- ✅ **HTTP Status Codes** - 200, 400, 403, 429, 500
- ✅ **Error Logging** - All errors logged to `/logs/`

---

## 5. FRONTEND TRACKING ARCHITECTURE

### Main Tracker: `tracker-v2.js`

**Purpose:** Orchestrates all tracking modules.

**Responsibilities:**
- Initialize session
- Load fingerprint module
- Track pageviews
- Track events (clicks, scroll, etc.)
- Send data to APIs
- Manage GDPR consent
- Handle errors gracefully

**Integration:**
```html
<script src="/assets/js/logger.js" defer></script>
<script src="/assets/js/tracker-v2.js" defer></script>
```

### Module: `fingerprint-module.js`

**Status:** ✅ Complete (Module #1)

**Features:**
- Canvas fingerprinting
- WebGL fingerprinting
- Audio fingerprinting
- Screen/timezone/fonts/plugins detection
- SHA-256 hashing
- LocalStorage caching
- Server communication

**Usage:**
```javascript
const fingerprint = await FingerprintModule.generateFingerprint();
// Returns: {fingerprintId, components}
```

### Module: `event-tracker.js`

**Status:** ⏳ Pending (Module #5)

**Features:**
- Click tracking
- Scroll tracking with depth calculation
- Rage click detection (3+ clicks in 1s within 50px)
- Copy/paste detection
- Form interaction tracking
- Idle/active state detection

### Module: `replay-recorder.js`

**Status:** ⏳ Pending (Module #7)

**Features:**
- Mouse movement recording (throttled 100ms)
- Scroll position recording
- Click position recording
- Viewport resize recording
- Frame batching (every 50 frames)
- Compression for storage efficiency

### Module: `gdpr-consent.js`

**Status:** ⏳ Pending (Module #13)

**Features:**
- Cookie consent banner
- LocalStorage consent management
- Opt-in/opt-out handling
- Consent revocation
- Data export request UI
- Data deletion request UI

### Data Storage (Client-Side)

**LocalStorage:**
- `wgs_session_id` - Session identifier
- `wgs_fingerprint_id` - Device fingerprint
- `wgs_analytics_consent` - GDPR consent status (granted/revoked)
- `wgs_utm_params` - Last seen UTM parameters

**SessionStorage:**
- `wgs_session_start` - Session start timestamp
- `wgs_entry_page` - Entry page URL

---

## 6. BACKEND BUSINESS LOGIC LAYER

### Class: `FingerprintEngine`

**File:** `includes/FingerprintEngine.php`
**Status:** ✅ Complete (Module #1)

**Methods:**
- `storeFingerprint(array $components): array` - Store or update fingerprint
- `getFingerprint(string $fingerprintId): ?array` - Retrieve fingerprint
- `updateLastSeen(string $fingerprintId): bool` - Update timestamp
- `linkToSession(string $fingerprintId, string $sessionId): bool` - Link to session
- `findSimilarFingerprints(array $components, float $threshold): array` - Similarity detection
- `getFingerprintStats(string $fingerprintId): array` - Get statistics

**Algorithm:**
- SHA-256 fingerprint ID calculation
- Weighted similarity scoring (canvas 30%, WebGL 25%, audio 20%, screen 15%, other 10%)
- Device mapping (JSON storage of multiple UAs per fingerprint)

### Class: `BotDetector`

**File:** `includes/BotDetector.php`
**Status:** ✅ Complete (Module #3)

**Methods:**
- `detekujBota(string $sessionId, string $fingerprintId, array $requestData): array` - Main bot detection
- `vypocitejBotScore(array $signals): int` - Calculate 0-100 score
- `vypocitejUaScore(string $userAgent): int` - User-Agent score (0-30)
- `vypocitejBehavioralScore(array $signals): int` - Behavioral score (0-40)
- `vypocitejFingerprintScore(string $fingerprintId): int` - Fingerprint score (0-20)
- `vypocitejNetworkScore(string $ipAddress): int` - Network score (0-10)
- `urcThreatLevel(int $botScore): string` - Map score to threat level
- `jeNaWhitelistu(string $userAgent, string $ipAddress): bool` - Whitelist check
- `ulozDetekci(string $sessionId, string $fingerprintId, array $detectionData): bool` - Store detection
- `nactiDetekceRelace(string $sessionId): array` - Get session detections
- `nactiStatistiky(string $from, string $to, array $filters): array` - Get bot activity stats
- `ipInCidr(string $ip, string $cidr): bool` - IP range check (private method)

**Detection Signals:**
- User agent patterns (bot keywords: bot, crawler, spider, curl, wget, selenium, puppeteer)
- Webdriver detection (navigator.webdriver, window.callPhantom)
- Headless browser detection (HeadlessChrome, missing plugins, no sidebar)
- Automation detection (PhantomJS, window.phantom, window.Buffer)
- Mouse movement entropy (0-1, low = bot)
- Keyboard timing variance (0-1, low = bot)
- Pageview speed (< 500ms = suspicious)
- Fingerprint stability (high session count = bot)
- Network analysis (data center IP ranges)

**Threat Level Classification:**
- none: 0-20 (pravděpodobně člověk)
- low: 21-40 (možný bot)
- medium: 41-60 (pravděpodobný bot)
- high: 61-80 (skoro jistě bot)
- critical: 81-100 (100% bot)

### Class: `GeolocationService`

**File:** `includes/GeolocationService.php`
**Status:** ⏳ Pending (Module #4)

**Methods:**
- `getLocationFromIP(string $ipAddress): array` - Get location data
- `getCachedLocation(string $ipAddress): ?array` - Check cache first
- `storeInCache(string $ipAddress, array $data): void` - Store with 3-day TTL
- `cleanExpiredCache(): int` - Remove expired entries

**API Integration:**
- Uses `ipapi.co` or `ip-api.com` (free tier)
- 3-day caching to reduce API calls
- Fallback to default location on failure

**Data Returned:**
- Country code & name
- City
- Latitude & longitude
- ISP & ASN
- VPN/proxy/datacenter flags

### Class: `SessionMerger`

**File:** `includes/SessionMerger.php`
**Status:** ⏳ Pending (Module #2)

**Methods:**
- `mergeSessions(string $fingerprintId): array` - Merge sessions by fingerprint
- `stitchSessionPath(array $sessions): array` - Create chronological path
- `updateEngagementScores(string $sessionId): void` - Calculate scores

### Class: `UserScoreCalculator`

**File:** `includes/UserScoreCalculator.php`
**Status:** ⏳ Pending (Module #10)

**Methods:**
- `calculateEngagementScore(array $sessionData): float` - 0-100 score
- `calculateFrustrationScore(array $eventData): float` - Rage clicks, erratic behavior
- `calculateInterestScore(array $sessionData): float` - Reading time, scroll quality

**Algorithm:**
- Engagement = f(click_count, scroll_depth, duration, mouse_activity)
- Frustration = f(rage_clicks, hesitation_time, erratic_scrolling)
- Interest = f(reading_time, focus_on_content, return_visits)

### Class: `CampaignAttribution`

**File:** `includes/CampaignAttribution.php`
**Status:** ⏳ Pending (Module #8)

**Methods:**
- `attributeConversion(string $sessionId, array $conversion): void`
- `getMultiTouchAttribution(string $fingerprintId): array`
- `updateCampaignStats(array $utmParams): void`

**Models:**
- Last-click attribution
- First-click attribution
- Linear attribution (all touchpoints equal)

### Class: `ConversionFunnel`

**File:** `includes/ConversionFunnel.php`
**Status:** ⏳ Pending (Module #9)

**Methods:**
- `trackGoal(string $sessionId, string $goalType, float $value): void`
- `calculateFunnel(array $steps, string $dateRange): array`
- `getDropOffAnalysis(array $funnel): array`

### Class: `AIReportGenerator`

**File:** `includes/AIReportGenerator.php`
**Status:** ⏳ Pending (Module #12)

**Methods:**
- `generateDailyReport(string $date): array`
- `generateWeeklyReport(string $startDate): array`
- `detectAnomalies(array $metrics, array $historical): array`
- `generatePredictions(array $historical): array`

**Algorithm:**
- Trend analysis (7-day, 30-day moving averages)
- Anomaly detection (standard deviation method)
- Predictions (linear regression for next 7 days)

---

## 7. ADMIN UI MODULES

### Main Dashboard: `analytics-v2.php`

**Status:** ⏳ Pending (Module #11)

**Tabs:**

1. **Přehled (Overview)**
   - Real-time metrics (active visitors, events, conversions)
   - Today vs. yesterday comparison
   - Active sessions count (humans vs. bots)

2. **Relace (Sessions)**
   - Session list table with filters
   - Filters: date range, country, device type, bot status
   - Detail view: full session timeline

3. **Události (Events)**
   - Event timeline view
   - Filters: event type, page, session
   - Event details (timestamp, position, element)

4. **Heatmapy (Heatmaps)**
   - Click heatmap overlay
   - Scroll heatmap with drop-off
   - Device-specific filtering
   - Page selector

5. **Přehrávání (Session Replay)**
   - Video-style playback
   - Timeline scrubber
   - Speed controls (0.5x, 1x, 2x)
   - Next/previous page navigation

6. **Kampaně (Campaigns)**
   - UTM campaign performance table
   - Conversion rate per campaign
   - ROI calculation
   - Attribution model selector

7. **Konverze (Conversions)**
   - Funnel visualization
   - Drop-off analysis
   - Conversion paths
   - Goal performance

8. **Boti (Bot Detection)**
   - Bot list with threat levels
   - Whitelist management
   - Detection rule configuration
   - Bot traffic trends

9. **Geolokace (Geolocation)**
   - World map visualization
   - Top countries table
   - Top cities table
   - ISP analysis

10. **Reporty (AI Reports)**
    - Daily/weekly/monthly reports archive
    - Trend charts
    - Anomaly highlights
    - Predictions

11. **Uživatelé (User Scores)**
    - Engagement score distribution
    - Frustration score analysis
    - Interest heatmap

12. **GDPR**
    - Export requests log
    - Delete requests log
    - Consent status
    - Data retention settings

### Standalone Pages

| Page | URL | Purpose |
|------|-----|---------|
| `analytics-v2.php` | `/analytics-v2.php` | Main dashboard (all tabs) |
| `analytics-heatmap.php` | `/analytics-heatmap.php?page=X` | Dedicated heatmap viewer with overlay |
| `analytics-replay.php` | `/analytics-replay.php?session=X` | Dedicated replay player |
| `analytics-reports.php` | `/analytics-reports.php` | AI reports archive |
| `analytics-settings.php` | `/analytics-settings.php` | System settings (ignored IPs, bot rules, GDPR) |

---

## 8. SECURITY & GDPR COMPLIANCE

### Security Measures

#### CSRF Protection
- All POST/PUT/DELETE requests require valid CSRF token
- Token validation in every API endpoint
- Token generation: `generateCSRFToken()` from `csrf_helper.php`
- Token injection: `<meta name="csrf-token" content="...">`

#### Rate Limiting
- 100 requests/hour per session for fingerprint API
- 1000 requests/hour per IP for tracking APIs
- 20 requests/hour per session for analytics APIs
- File-based rate limit storage in `/logs/rate_limit_*.txt`

#### SQL Injection Prevention
- All queries use PDO prepared statements
- No string concatenation in SQL
- Parameterized queries only

#### XSS Prevention
- All output escaped with `htmlspecialchars()`
- JSON-only API responses (no HTML)
- CSP headers in `includes/security_headers.php`

#### Input Validation
- Type checking (integers, floats, strings)
- Range validation (e.g., screen_width > 0)
- Required field validation
- Sanitization of all user inputs

#### IP Anonymization
- Last octet of IPv4 masked (e.g., 192.168.1.xxx → 192.168.1.0)
- Last 80 bits of IPv6 masked
- Configurable in GDPR settings

### GDPR Compliance

#### Lawful Basis
- **Legitimate Interest** (Article 6(1)(f)) for analytics
- **Consent** (Article 6(1)(a)) optional via cookie banner
- Purpose limitation: analytics only, no advertising

#### Pseudonymization (Article 4(5))
- Fingerprint IDs are SHA-256 hashes (irreversible)
- Cannot identify natural person without additional data
- Not classified as Personal Data in isolation

#### Transparency
- Privacy policy disclosure of fingerprinting
- Clear explanation in GDPR consent banner
- Cookie banner with opt-in/opt-out

#### Right to Access (Article 15)
- User can export all their data
- API: `/api/gdpr_export_my_data.php`
- JSON format with all fingerprint components, sessions, events

#### Right to Erasure (Article 17)
- User can request deletion
- API: `/api/gdpr_delete_my_data.php`
- Deletes fingerprint + anonymizes linked sessions/events

#### Data Minimization (Article 5(1)(c))
- Only essential components collected
- No unnecessary personal data
- User agent stored for device mapping only

#### Storage Limitation (Article 5(1)(e))
- Replay frames: 30-day TTL (auto-delete)
- Events: 90-day aggregation into reports
- Geolocation cache: 3-day TTL
- Real-time data: 5-minute TTL

#### Data Portability (Article 20)
- Fingerprint data exportable in JSON
- Structured, machine-readable format
- Includes all components and timestamps

#### Privacy by Design (Article 25)
- Fingerprint generated client-side (user visibility)
- SHA-256 hashing prevents raw data storage
- No third-party sharing
- Consent-first approach (optional)

### Consent Management

**Cookie Banner:**
```javascript
if (localStorage.getItem('wgs_analytics_consent') !== 'granted') {
    // Show banner
    // On accept: localStorage.setItem('wgs_analytics_consent', 'granted')
    // On reject: localStorage.setItem('wgs_analytics_consent', 'revoked')
}
```

**Consent Check:**
```javascript
function checkGDPRConsent() {
    const consent = localStorage.getItem('wgs_analytics_consent');
    return consent === 'granted';
}
```

---

## 9. PERFORMANCE & OPTIMIZATION

### Database Optimization

#### Indexes
- All tables have indexes on:
  - Primary keys (auto-indexed)
  - Foreign keys (session_id, fingerprint_id)
  - Timestamps (created_at, last_seen)
  - Frequently filtered columns (page_url, event_type, device_type, country_code)

**Example:**
```sql
INDEX idx_fingerprint (fingerprint_id),
INDEX idx_session (session_id),
INDEX idx_page (page_url(100)),
INDEX idx_timestamp (timestamp),
INDEX idx_device (device_type)
```

#### Query Optimization
- Use `LIMIT` on all large queries
- Use `EXPLAIN` to verify index usage
- Avoid `SELECT *` (specify columns)
- Use `JOIN` instead of subqueries where possible

#### Data Cleanup

| Data Type | TTL Policy | Cleanup Method |
|-----------|------------|----------------|
| Replay frames | 30 days | Cron: `cleanup_old_replay_frames.php` |
| Raw events | 90 days | Cron: `cleanup_old_events.php` (aggregate into reports) |
| Real-time sessions | 5 minutes | Auto-expire with `expires_at` column |
| Geolocation cache | 3 days | Cron: `cleanup_geo_cache.php` |

### Caching Strategy

#### Database Query Caching
- **Geolocation cache:** 3 days TTL in `wgs_analytics_geolocation_cache`
- **Analytics summary cache:** 5 minutes TTL (in-memory or Redis)
- **Reports cache:** 1 day TTL (regenerate daily at 6 AM)

#### Client-Side Caching
- **Fingerprint:** Stored in localStorage (persistent across sessions)
- **Session ID:** Stored in sessionStorage (cleared on browser close)
- **UTM params:** Stored in sessionStorage for attribution

### Frontend Optimization

#### Throttling & Debouncing
- Mouse move events: throttled to 100ms
- Scroll events: throttled to 150ms
- Resize events: debounced to 200ms

#### Batching
- Replay frames: sent in batches of 50 frames
- Events: sent immediately for critical events (rage clicks, conversions)
- Heatmap data: sent on page unload

#### Async Loading
```html
<script src="/assets/js/logger.js" defer></script>
<script src="/assets/js/tracker-v2.js" defer></script>
```

---

## 10. DEPLOYMENT & CRON JOBS

### ⚠️ KRITICKÉ: Webcron Limit na Hostingu

**DŮLEŽITÉ:** Hosting má **LIMIT 5 WEBCRONŮ** (sdílený hosting). Je potřeba sjednotit/optimalizovat cron jobs na konci projektu.

**Řešení:**
1. Vytvořit **unified cleanup script** (`scripts/unified_cleanup.php`), který spustí všechny cleanup operace najednou
2. Sjednotit denní reporty do jednoho skriptu
3. Prioritizovat nejdůležitější crony

**POZNÁMKA:** Na konci implementace všech modulů je nutné zkontrolovat a upravit cron jobs, aby nepřekročily limit 5!

---

### Cron Jobs Schedule (PLÁNOVÁNO - před optimalizací)

| Job | File | Schedule | Purpose | Priority |
|-----|------|----------|---------|----------|
| Cleanup Geo Cache | `scripts/cleanup_geo_cache.php` | Daily 04:00 | Delete expired geolocation cache | ✅ HIGH |
| Cleanup Replay Frames | `scripts/cleanup_old_replay_frames.php` | Daily 02:00 | Delete frames older than 30 days | ✅ HIGH |
| Cleanup Old Events | `scripts/cleanup_old_events.php` | Daily 03:00 | Aggregate events older than 90 days | 🟡 MEDIUM |
| Cleanup Realtime Sessions | `scripts/cleanup_realtime_sessions.php` | Every 5 min | Delete expired real-time sessions | 🟡 MEDIUM |
| Daily Report | `scripts/generate_daily_report.php` | Daily 06:00 | Generate AI report for previous day | 🔵 LOW |
| Weekly Report | `scripts/generate_weekly_report.php` | Monday 07:00 | Generate AI report for previous week | 🔵 LOW |
| Update Campaign Stats | `scripts/update_campaign_stats.php` | Every hour | Aggregate UTM campaign data | 🟡 MEDIUM |

**AKTUÁLNĚ AKTIVNÍ (v rámci limitu 5):**
1. ✅ `scripts/cleanup_geo_cache.php` - Daily 04:00
2. ⏳ `scripts/cleanup_old_replay_frames.php` - Daily 02:00 (bude přidán po Modulu #7)
3. (Zbytek bude sjednocen na konci projektu)

**TODO po dokončení všech modulů:** Vytvořit `scripts/unified_cleanup.php` který spojí všechny cleanup operace

### Crontab Example

```cron
# Daily reports
0 6 * * * /usr/bin/php /path/to/scripts/generate_daily_report.php >> /path/to/logs/cron.log 2>&1

# Weekly reports (Monday 7 AM)
0 7 * * 1 /usr/bin/php /path/to/scripts/generate_weekly_report.php >> /path/to/logs/cron.log 2>&1

# Cleanup jobs (2-4 AM)
0 2 * * * /usr/bin/php /path/to/scripts/cleanup_old_replay_frames.php >> /path/to/logs/cron.log 2>&1
0 3 * * * /usr/bin/php /path/to/scripts/cleanup_old_events.php >> /path/to/logs/cron.log 2>&1
0 4 * * * /usr/bin/php /path/to/scripts/cleanup_geo_cache.php >> /path/to/logs/cron.log 2>&1

# Real-time cleanup (every 5 minutes)
*/5 * * * * /usr/bin/php /path/to/scripts/cleanup_realtime_sessions.php >> /path/to/logs/cron.log 2>&1

# Campaign stats (every hour)
0 * * * * /usr/bin/php /path/to/scripts/update_campaign_stats.php >> /path/to/logs/cron.log 2>&1
```

### Deployment Checklist

- [ ] Run database migrations for each module
- [ ] Verify all indexes created
- [ ] Configure cron jobs
- [ ] Set up log rotation (`/logs/*.log`)
- [ ] Configure SMTP for email notifications (if needed)
- [ ] Set correct file permissions (755 for PHP, 644 for assets)
- [ ] Create `/logs/rate_limit/` directory (writable)
- [ ] Verify `.env` configuration
- [ ] Test GDPR export/delete functions
- [ ] Verify CSRF token generation
- [ ] Test API rate limiting
- [ ] Configure security headers in Nginx/Apache

---

## 11. MODULE IMPLEMENTATION PLAN

### Implementation Order

Modules must be implemented **in sequential order**. Each module must be completed, tested, and approved before proceeding to the next.

---

### ✅ MODULE #1: FINGERPRINTING ENGINE

**Status:** ✅ **COMPLETE**
**Commit:** `75c52d4`
**Date Completed:** 2025-11-23

**Deliverables:**
- ✅ Database table: `wgs_analytics_fingerprints`
- ✅ PHP class: `includes/FingerprintEngine.php`
- ✅ API endpoint: `api/fingerprint_store.php`
- ✅ JS module: `assets/js/fingerprint-module.js`
- ✅ Migration script: `migrace_module1_fingerprinting.php`

**Features:**
- Canvas, WebGL, Audio fingerprinting
- SHA-256 fingerprint ID generation
- Similarity scoring (85% threshold)
- Device mapping (JSON storage of user agents)
- LocalStorage caching
- CSRF protection & rate limiting

**Testing:** ⏳ Pending user testing

**Next Steps:**
1. User runs migration: `migrace_module1_fingerprinting.php?execute=1`
2. User tests fingerprint generation in browser console
3. User verifies data in database
4. User approves Module #1 → proceed to Module #2

---

### ✅ MODULE #2: ADVANCED SESSION TRACKING

**Status:** ✅ **COMPLETE**
**Commit:** `481bd22`
**Date Completed:** 2025-11-23

**Deliverables:**
- ✅ Database table: `wgs_analytics_sessions` (33 sloupců, 11 indexů)
- ✅ Enhanced `wgs_pageviews` with new columns (`session_id`, `fingerprint_id`)
- ✅ PHP class: `includes/SessionMerger.php` (14 metod, 650 řádků)
- ✅ API endpoint: `api/track_v2.php` (280 řádků)
- ✅ JS module: `assets/js/tracker-v2.js` (450 řádků)
- ✅ Migration script: `migrace_module2_sessions.php` (400 řádků)

**Features:**
- Entry/exit page tracking
- Session lifecycle management (30-minute timeout)
- Pageview count per session
- Engagement score calculation (0-100)
- UTM parameter persistence (first-touch attribution)
- Device/browser/OS detection
- Cross-session stitching via fingerprint_id
- Session heartbeat (30s interval)
- CSRF protection & rate limiting (1000 req/hour)
- IP anonymization (last octet masked)
- Backward compatibility (nullable columns)

**Testing:** ⏳ Pending user testing

**Next Steps:**
1. User runs migration: `migrace_module2_sessions.php?execute=1`
2. User tests session tracking in browser console
3. User verifies database records
4. User approves Module #2 → proceed to Module #3

---

### ⏳ MODULE #3: BOT DETECTION & SECURITY ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 3-4 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_bot_detections`
- [ ] PHP class: `includes/BotDetector.php`
- [ ] API endpoint: `api/analytics_bot_activity.php`
- [ ] JS: Client-side bot signals in `tracker-v2.js`
- [ ] Admin UI: Bot detection console in `analytics-v2.php` (tab)
- [ ] Migration script: `migrace_module3_bot_detection.php`

**Features:**
- User agent bot pattern detection
- Webdriver detection
- Headless browser detection
- VPN/Proxy/TOR detection
- Datacenter IP detection
- Anomaly detection (zero mouse, zero scroll, too fast navigation)
- Bot score calculation (0-100)
- Threat level mapping (none, low, medium, high, critical)
- Bot whitelist (Googlebot, etc.)

**Acceptance Criteria:**
- [ ] Bot score calculated for all sessions
- [ ] Threat level assigned correctly
- [ ] Known bots whitelisted
- [ ] Admin can view bot list
- [ ] Admin can add/remove from whitelist
- [ ] Bot traffic separated from human traffic in dashboards

---

### ⏳ MODULE #4: GEOLOCATION ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 1-2 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_geolocation_cache`
- [ ] PHP class: `includes/GeolocationService.php`
- [ ] API integration: ipapi.co or ip-api.com
- [ ] Caching logic (3-day TTL)
- [ ] Cron job: `scripts/cleanup_geo_cache.php`
- [ ] Migration script: `migrace_module4_geolocation.php`

**Features:**
- IP → Location lookup (country, city, lat/lng)
- ISP & ASN detection
- VPN/proxy/datacenter flags
- 3-day caching to reduce API calls
- Fallback to default location on API failure
- Auto-cleanup of expired cache

**Acceptance Criteria:**
- [ ] IP addresses resolved to country/city
- [ ] Cache used before API call
- [ ] Expired cache cleaned up daily
- [ ] VPN/datacenter flags set correctly
- [ ] Admin can view geographic distribution

---

### ⏳ MODULE #5: EVENT TRACKING ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 2-3 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_events`
- [ ] API endpoint: `api/track_event.php`
- [ ] JS module: `assets/js/event-tracker.js`
- [ ] Integration: Add to `tracker-v2.js`
- [ ] Migration script: `migrace_module5_events.php`

**Features:**
- Click tracking (x, y, element selector, element text)
- Scroll tracking (scroll depth percentage)
- Rage click detection (3+ clicks in 1s within 50px)
- Copy/paste tracking
- Form interaction tracking
- Idle/active state detection
- Event batching for performance

**Acceptance Criteria:**
- [ ] All events stored with timestamp (ms precision)
- [ ] Rage clicks detected and logged
- [ ] Scroll depth calculated correctly
- [ ] Element selectors captured
- [ ] Admin can filter events by type/page/session

---

### ✅ MODULE #6: HEATMAP ENGINE

**Status:** ✅ **COMPLETE**
**Commit:** `e727f2b`
**Date Completed:** 2025-11-23

**Deliverables:**
- ✅ Database tables: `wgs_analytics_heatmap_clicks`, `wgs_analytics_heatmap_scroll`
- ✅ API endpoints: `api/track_heatmap.php`, `api/analytics_heatmap.php`
- ✅ JS module: `assets/js/heatmap-renderer.js`
- ✅ Admin UI: Heatmap viewer in `analytics-heatmap.php`
- ✅ Migration script: `migrace_module6_heatmaps.php`

**Features:**
- Click heatmap (x/y as % of viewport)
- Scroll heatmap (scroll depth buckets: 0, 10, 20, ..., 100)
- Device-specific heatmaps (desktop/mobile/tablet)
- Page-specific heatmaps
- Canvas-based heatmap rendering with HTML5
- Gradient visualization (blue → cyan → green → yellow → orange → red)
- UPSERT aggregation pattern (INSERT ON DUPLICATE KEY UPDATE)
- Running average for viewport dimensions
- URL normalization (removes query parameters)
- Export to PNG functionality

**Acceptance Criteria:**
- ✅ Click positions stored as percentages (0-100)
- ✅ Scroll depth aggregated into 10% buckets
- ✅ Heatmap overlay renders on admin page with Canvas
- ✅ Device filtering works (desktop/mobile/tablet)
- ✅ Color gradient shows intensity (blue to red)

**Testing:** ⏳ Pending user testing

**Next Steps:**
1. User runs migration: `migrace_module6_heatmaps.php?execute=1`
2. User opens heatmap viewer: `analytics-heatmap.php`
3. User selects page, device type, and heatmap type
4. User verifies heatmap visualization
5. User tests export PNG functionality
6. User verifies data in database tables
7. User approves Module #6 → proceed to Module #7

---

### ⏳ MODULE #7: SESSION REPLAY ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 4-5 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_replay_frames`
- [ ] API endpoints: `api/track_replay.php`, `api/analytics_replay.php`
- [ ] JS modules: `assets/js/replay-recorder.js`, `assets/js/replay-player.js`
- [ ] Admin UI: Replay player in `analytics-replay.php`
- [ ] Migration script: `migrace_module7_session_replay.php`

**Features:**
- Mouse movement recording (throttled 100ms)
- Scroll position recording
- Click position recording
- Viewport resize recording
- Frame batching (every 50 frames or 30s)
- Playback with timeline scrubber
- Speed controls (0.5x, 1x, 2x)
- Next/previous page navigation

**Acceptance Criteria:**
- [ ] Frames stored with millisecond timestamps
- [ ] Replay playback smooth and accurate
- [ ] Timeline scrubber works
- [ ] Speed controls functional
- [ ] Can navigate between pages in session
- [ ] Old replays auto-deleted after 30 days

---

### ⏳ MODULE #8: UTM CAMPAIGN ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 1-2 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_utm_campaigns`
- [ ] API endpoint: `api/analytics_campaigns.php`
- [ ] JS: UTM parser in `tracker-v2.js`
- [ ] Admin UI: Campaign performance in `analytics-v2.php` (tab)
- [ ] PHP class: `includes/CampaignAttribution.php`
- [ ] Migration script: `migrace_module8_utm_campaigns.php`

**Features:**
- UTM parameter parsing (source, medium, campaign, content, term)
- Campaign performance aggregation
- Conversion attribution
- Multi-touch attribution models (last-click, first-click, linear)
- ROI calculation

**Acceptance Criteria:**
- [ ] UTM params captured from URL
- [ ] Campaign stats aggregated hourly
- [ ] Conversions attributed to campaigns
- [ ] Admin can view campaign performance table
- [ ] Attribution model configurable

---

### ⏳ MODULE #9: CONVERSION FUNNEL ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 2-3 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_conversions`
- [ ] API endpoints: `api/track_conversion.php`, `api/analytics_conversions.php`
- [ ] JS: Conversion tracking API in `tracker-v2.js`
- [ ] Admin UI: Funnel visualization in `analytics-v2.php` (tab)
- [ ] PHP class: `includes/ConversionFunnel.php`
- [ ] Migration script: `migrace_module9_conversions.php`

**Features:**
- Goal tracking (form_submit, login, contact, purchase)
- Conversion value tracking
- Conversion path tracking (JSON array of pages)
- Time to conversion
- Funnel steps definition
- Drop-off analysis

**Acceptance Criteria:**
- [ ] Conversions tracked with value
- [ ] Conversion paths stored
- [ ] Time to conversion calculated
- [ ] Funnel visualization renders correctly
- [ ] Drop-off percentages accurate

---

### ⏳ MODULE #10: USER INTEREST AI ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 3-4 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_user_scores`
- [ ] PHP class: `includes/UserScoreCalculator.php`
- [ ] API endpoint: `api/analytics_user_scores.php`
- [ ] Admin UI: User scores in `analytics-v2.php` (tab)
- [ ] Migration script: `migrace_module10_user_scores.php`

**Features:**
- Engagement score (0-100) based on clicks, scroll, duration, mouse activity
- Frustration score (0-100) based on rage clicks, hesitation, erratic behavior
- Interest score (0-100) based on reading time, focus, return visits
- Reading time estimation
- Click quality analysis
- Scroll quality analysis

**Acceptance Criteria:**
- [ ] Scores calculated for all sessions
- [ ] Engagement score correlates with activity
- [ ] Frustration score detects rage clicks
- [ ] Interest score reflects content engagement
- [ ] Admin can view score distributions

---

### ⏳ MODULE #11: REAL-TIME DASHBOARD

**Status:** ⏳ Pending Approval

**Estimated Time:** 3-4 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_realtime`
- [ ] API endpoint: `api/analytics_dashboard.php` (enhanced)
- [ ] Admin UI: Real-time tab in `analytics-v2.php`
- [ ] JS: Live updates with polling (5s interval)
- [ ] Migration script: `migrace_module11_realtime.php`

**Features:**
- Active visitors count (humans vs. bots)
- Live event feed
- Live world map
- Live heatmap updates
- Session list with live status
- Auto-cleanup of inactive sessions (5min TTL)

**Acceptance Criteria:**
- [ ] Active visitor count updates every 5s
- [ ] Inactive sessions auto-removed
- [ ] Live events appear in real-time
- [ ] Map shows active visitor locations
- [ ] Performance optimized (no lag)

---

### ⏳ MODULE #12: AI REPORTS ENGINE

**Status:** ⏳ Pending Approval

**Estimated Time:** 3-4 hours

**Deliverables:**
- [ ] Database table: `wgs_analytics_reports`
- [ ] PHP class: `includes/AIReportGenerator.php`
- [ ] API endpoint: `api/analytics_reports.php`
- [ ] Admin UI: Reports archive in `analytics-reports.php`
- [ ] Cron jobs: `scripts/generate_daily_report.php`, `scripts/generate_weekly_report.php`
- [ ] Migration script: `migrace_module12_ai_reports.php`

**Features:**
- Daily report generation (6 AM)
- Weekly report generation (Monday 7 AM)
- Metrics summary (visits, conversions, bounce rate, etc.)
- Trend analysis (vs. previous period)
- Anomaly detection (unexpected spikes/drops)
- Predictions (next 7 days using linear regression)
- Bot activity summary

**Acceptance Criteria:**
- [ ] Reports generated daily at 6 AM
- [ ] Trends calculated correctly
- [ ] Anomalies detected
- [ ] Predictions reasonable
- [ ] Admin can view report archive

---

### ⏳ MODULE #13: GDPR COMPLIANCE

**Status:** ⏳ Pending Approval

**Estimated Time:** 2-3 hours

**Deliverables:**
- [ ] JS module: `assets/js/gdpr-consent.js`
- [ ] API endpoints: `api/gdpr_consent.php`, `api/gdpr_export_my_data.php`, `api/gdpr_delete_my_data.php`
- [ ] Admin UI: GDPR panel in `analytics-v2.php` (tab)
- [ ] Admin UI: Settings for IP anonymization, data retention

**Features:**
- Cookie consent banner (opt-in/opt-out)
- Consent storage in localStorage
- Data export (all fingerprint/session/event data as JSON)
- Data deletion (anonymize or delete)
- IP anonymization toggle
- Data retention policy configuration

**Acceptance Criteria:**
- [ ] Consent banner appears on first visit
- [ ] No tracking before consent granted
- [ ] Export returns complete JSON
- [ ] Delete removes all user data
- [ ] IP anonymization configurable
- [ ] Admin can view consent logs

---

## 12. WORKFLOW & RULES

### Critical Rules (MUST FOLLOW)

1. **Sequential Module Implementation**
   - Modules MUST be implemented in order (#1 → #2 → #3 → ... → #13)
   - NEVER skip modules
   - NEVER work on multiple modules simultaneously

2. **Approval Required**
   - WAIT for explicit user approval before starting each module
   - WAIT for testing/feedback after completing each module
   - NEVER assume approval

3. **Isolated Changes**
   - ONLY modify files related to current module
   - NEVER touch files from other modules
   - NEVER modify existing business logic unless explicitly required

4. **Code Before Commit**
   - Create implementation plan first
   - Wait for plan approval
   - THEN generate code
   - Commit only completed module

5. **Testing Protocol**
   - User tests each module before approval
   - User verifies acceptance criteria
   - User runs migration script
   - User tests in browser (if frontend changes)

6. **Documentation**
   - Update NEWANAL.md after each module completion
   - Mark module as ✅ Complete with commit hash
   - Document any deviations from plan

### Workflow for Each Module

```
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: Create Implementation Plan                         │
│  - Database schema                                          │
│  - PHP class structure                                      │
│  - API design                                               │
│  - JS architecture                                          │
│  - Acceptance criteria                                      │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 2: Wait for Plan Approval                             │
│  - User reviews plan                                        │
│  - User requests changes OR approves                        │
│  - If changes requested, revise plan and repeat            │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 3: Generate Code                                      │
│  - Create migration script                                  │
│  - Create PHP classes                                       │
│  - Create API endpoints                                     │
│  - Create JS modules                                        │
│  - Create admin UI (if applicable)                          │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 4: Commit & Push                                      │
│  - git add [files]                                          │
│  - git commit -m "Module #X: [Name] - Complete"            │
│  - git push                                                 │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 5: STOP and Wait for Testing                          │
│  - User runs migration                                      │
│  - User tests functionality                                 │
│  - User verifies acceptance criteria                        │
│  - User reports bugs OR approves                            │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 6: Fix Bugs (if needed)                               │
│  - Fix reported issues                                      │
│  - Commit fixes                                             │
│  - Repeat Step 5                                            │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 7: Module Approved → Proceed to Next Module           │
│  - Mark module as ✅ Complete in NEWANAL.md                │
│  - Update Project Status section                            │
│  - Start Step 1 for next module                             │
└─────────────────────────────────────────────────────────────┘
```

### Commit Message Format

```
Module #[N]: [Module Name] - [Status]

[Emoji] [Summary of changes]

✅ IMPLEMENTED:
- [Feature 1]
- [Feature 2]
- [Feature 3]

🔐 SECURITY:
- [Security measure 1]
- [Security measure 2]

📊 FEATURES:
- [Detailed feature description]

📝 FILES:
- [File 1] (purpose)
- [File 2] (purpose)

Module #[N] [status] and ready for [testing/production].
```

**Example:**
```
Module #1: Fingerprinting Engine - Complete implementation

✅ IMPLEMENTED:
- Database table wgs_analytics_fingerprints (24 columns)
- PHP class FingerprintEngine (store, get, merge, similarity)
- API endpoint /api/fingerprint_store.php (CSRF, rate limiting)
- JS module fingerprint-module.js (canvas, WebGL, audio, fonts, plugins)

🔐 SECURITY:
- CSRF token validation
- Rate limiting (100 req/hour)
- SHA-256 hashing

Module #1 complete and ready for testing.
```

---

## 13. BACKWARD COMPATIBILITY

### Protected Elements (NEVER MODIFY)

**Existing Tables:**
- `wgs_reklamace` - Main complaints table
- `wgs_users` - User accounts
- `wgs_registration_keys` - Registration keys
- `wgs_theme_settings` - UI customization
- `wgs_content_texts` - Page content
- `wgs_system_config` - System settings
- `wgs_pending_actions` - Action queue
- `wgs_email_queue` - Email queue

**Existing Files:**
- `config/config.php` - Main config
- `config/database.php` - Database singleton
- `init.php` - Bootstrap file
- `app/controllers/save.php` - Complaint saving (CRITICAL - never touch)
- All existing business logic in `app/controllers/`

**Existing APIs:**
- `/api/control_center_api.php` - Admin operations
- `/api/protokol_api.php` - Protocol CRUD
- `/api/statistiky_api.php` - Statistics (old)
- All other existing APIs

### Safe Modifications

**Tables:**
- ✅ ADD columns to `wgs_pageviews` (e.g., `fingerprint_id`)
- ❌ REMOVE or RENAME existing columns
- ✅ ADD indexes
- ❌ REMOVE existing indexes

**Files:**
- ✅ CREATE new files in `/api/`, `/includes/`, `/assets/js/`
- ❌ MODIFY existing files unless explicitly required for integration

**Pages:**
- ✅ CREATE new admin pages (e.g., `analytics-v2.php`)
- ❌ MODIFY existing pages (`analytics.php` remains as-is)
- ✅ ADD new menu items to navigation

### Migration Safety

All migrations must:
1. Check if table/column exists before creating
2. Be idempotent (safe to run multiple times)
3. Use transactions (BEGIN, COMMIT, ROLLBACK)
4. Log all changes
5. Provide rollback instructions

**Example:**
```php
// Safe migration
$stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_fingerprints'");
if ($stmt->rowCount() === 0) {
    // Create table
    $pdo->exec("CREATE TABLE ...");
} else {
    echo "Table already exists. Skipping.";
}
```

### Version Compatibility

- **PHP:** 8.0+ (existing codebase requirement)
- **MariaDB:** 10.11+ (existing codebase requirement)
- **Browser:** Modern browsers (ES6+ for tracker.js)
- **Fallbacks:** Provide fallbacks for older browsers where possible

---

## 14. TESTING STRATEGY

### Unit Testing

Each PHP class should have basic test coverage:

**Example: FingerprintEngine Test**
```php
// Test 1: Store new fingerprint
$engine = new FingerprintEngine($pdo);
$result = $engine->storeFingerprint($components);
assert($result['is_new'] === true);
assert($result['session_count'] === 1);

// Test 2: Update existing fingerprint
$result2 = $engine->storeFingerprint($components);
assert($result2['is_new'] === false);
assert($result2['session_count'] === 2);
assert($result2['fingerprint_id'] === $result['fingerprint_id']);

// Test 3: Similarity detection
$similar = $engine->findSimilarFingerprints($components, 0.85);
assert(count($similar) > 0);
assert($similar[0]['similarity'] >= 0.85);
```

### Integration Testing

Test scenarios for each module (see Module Implementation Plan for specific scenarios).

**General Test Flow:**
1. Clear test data from database
2. Run migration script
3. Test API endpoints with curl or Postman
4. Test frontend in browser console
5. Verify data in database
6. Test error cases (invalid input, missing fields)
7. Test CSRF protection
8. Test rate limiting

### Browser Testing

**Required Browsers:**
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile Safari (iOS)
- Mobile Chrome (Android)

**Test Cases:**
- Fingerprint generation
- Event tracking (click, scroll)
- Session replay recording
- LocalStorage persistence
- GDPR consent banner
- API communication

### Performance Testing

**Database Queries:**
- All queries < 100ms
- Use `EXPLAIN` to verify index usage
- Test with 1M+ rows to simulate production load

**Frontend:**
- Tracker.js load < 500ms
- Fingerprint generation < 500ms
- Event batching prevents blocking
- No memory leaks (test long sessions)

### Security Testing

**CSRF:**
- Test API without token → 403 Forbidden
- Test API with invalid token → 403 Forbidden
- Test API with valid token → 200 OK

**SQL Injection:**
- Test with `'; DROP TABLE users; --` in inputs
- Verify PDO prepared statements prevent injection

**XSS:**
- Test with `<script>alert('XSS')</script>` in inputs
- Verify htmlspecialchars() prevents execution

**Rate Limiting:**
- Send 150 requests in 30 minutes
- Verify 101-150 rejected with 429

---

## 15. PROJECT STATUS

### Current Status

| Component | Status | Completion | Notes |
|-----------|--------|------------|-------|
| **Module #1** | ✅ Complete | 100% | Committed: `75c52d4` |
| **Module #2** | ✅ Complete | 100% | Committed: `481bd22` |
| **Module #3** | ✅ Complete | 100% | Committed: `8ebd2bb` |
| **Module #4** | ✅ Complete | 100% | Committed: `bb4ce85` |
| **Module #5** | ✅ Complete | 100% | Committed: `c92c683` |
| **Module #6** | ✅ Complete | 100% | Committed: `e727f2b` |
| **Module #7** | ✅ Complete | 100% | Committed: `8b0f1c0` |
| **Module #8** | ✅ Complete | 100% | Committed: `591549b` |
| **Module #9** | ⏳ Pending | 0% | Awaiting approval |
| **Module #10** | ⏳ Pending | 0% | Awaiting approval |
| **Module #11** | ⏳ Pending | 0% | Awaiting approval |
| **Module #12** | ⏳ Pending | 0% | Awaiting approval |
| **Module #13** | ⏳ Pending | 0% | Awaiting approval |

### Overall Progress

```
[████████████████████████████] 61.5% (8/13 modules complete)
```

### Next Steps

1. **User Action Required:**
   - Test Module #8 (UTM Campaign Tracking)
   - Run migration: `migrace_module8_utm_campaigns.php?execute=1`
   - Test UTM tracking v prohlížeči:
     * Otevřít stránku s UTM parametry (např. `?utm_source=facebook&utm_medium=cpc&utm_campaign=test`)
     * Zkontrolovat konzoli - měly by se logovat UTM parameters
     * Zkontrolovat localStorage a sessionStorage (first-click, last-click, conversion_path)
     * Zkontrolovat tabulku `wgs_analytics_sessions` - UTM parametry by měly být uloženy
   - Test campaign dashboard v admin UI:
     * Otevřít `analytics-campaigns.php`
     * Kliknout "Načíst data"
     * Zkontrolovat campaign tabulku, stats cards, filtry
     * Testovat export CSV
   - Run aggregation cron job: `scripts/aggregate_campaign_stats.php`
     * Zkontrolovat tabulku `wgs_analytics_utm_campaigns`
     * Ověřit agregaci session metrik, conversion metrik
   - Approve Module #8 OR request fixes

2. **After Module #8 Approval:**
   - Create implementation plan for Module #9 (Conversion Funnels)
   - Wait for plan approval
   - Generate code for Module #9
   - Repeat workflow

### File Inventory

**Created Files (Module #1):**
- `migrace_module1_fingerprinting.php` (350 lines)
- `includes/FingerprintEngine.php` (570 lines)
- `api/fingerprint_store.php` (220 lines)
- `assets/js/fingerprint-module.js` (510 lines)

**Created Files (Module #2):**
- `migrace_module2_sessions.php` (400 lines)
- `includes/SessionMerger.php` (650 lines)
- `api/track_v2.php` (280 lines)
- `assets/js/tracker-v2.js` (450 lines)

**Created Files (Module #3):**
- `migrace_module3_bot_detection.php` (450 lines)
- `includes/BotDetector.php` (720 lines)
- `api/analytics_bot_activity.php` (280 lines)
- `api/admin_bot_whitelist.php` (370 lines)
- Updated: `assets/js/tracker-v2.js` (+210 lines bot detection)
- Updated: `api/track_v2.php` (+40 lines integration)

**Created Files (Module #4):**
- `migrace_module4_geolocation.php` (382 lines)
- `includes/GeolocationService.php` (503 lines)
- `scripts/cleanup_geo_cache.php` (42 lines)
- Updated: `api/track_v2.php` (+31 lines geolocation)
- Updated: `includes/SessionMerger.php` (+25 lines aktualizujGeoData method)

**Created Files (Module #5):**
- `migrace_module5_events.php` (401 lines)
- `api/track_event.php` (320 lines)
- `assets/js/event-tracker.js` (558 lines)
- Updated: `assets/js/tracker-v2.js` (+47 lines event tracking integration)

**Created Files (Module #6):**
- `migrace_module6_heatmaps.php` (420 lines)
- `api/track_heatmap.php` (280 lines)
- `api/analytics_heatmap.php` (236 lines)
- `assets/js/heatmap-renderer.js` (277 lines)
- `analytics-heatmap.php` (370 lines)

**Created Files (Module #7):**
- `migrace_module7_session_replay.php` (380 lines)
- `api/track_replay.php` (320 lines)
- `api/analytics_replay.php` (210 lines)
- `assets/js/replay-recorder.js` (470 lines)
- `assets/js/replay-player.js` (420 lines)
- `analytics-replay.php` (280 lines)
- `scripts/cleanup_old_replay_frames.php` (120 lines)
- Updated: `assets/js/tracker-v2.js` (+51 lines replay integration)
- Updated: `NEWANAL.md` (webcron limit poznámka)

**Created Files (Module #8):**
- `migrace_module8_utm_campaigns.php` (300 lines)
- `includes/CampaignAttribution.php` (400 lines)
- `api/analytics_campaigns.php` (350 lines)
- `analytics-campaigns.php` (400 lines)
- `scripts/aggregate_campaign_stats.php` (200 lines)
- Updated: `assets/js/tracker-v2.js` (+118 lines multi-touch attribution)

**Total New Code:** ~13,351 lines (Modules #1-8)

**Pending Files (Modules #9-13):** ~12+ files, estimated ~6,000+ lines

---

## 16. APPENDIX

### Glossary

| Term | Definition |
|------|------------|
| **Fingerprint** | Unique device identifier generated from browser/hardware characteristics |
| **Canvas Fingerprinting** | Technique using HTML5 canvas rendering differences across GPUs |
| **WebGL Fingerprinting** | Technique extracting GPU vendor/renderer information |
| **Audio Fingerprinting** | Technique using AudioContext oscillator variations |
| **Session** | Period of user activity from entry to exit (or inactivity) |
| **Pageview** | Single page load event |
| **Event** | User interaction (click, scroll, etc.) |
| **Heatmap** | Visual representation of click/scroll patterns |
| **Session Replay** | Recording of user session for playback |
| **UTM Parameters** | URL parameters for campaign tracking (utm_source, etc.) |
| **Conversion** | Desired user action (form submit, purchase, etc.) |
| **Funnel** | Multi-step conversion path |
| **Bot** | Automated visitor (search engine crawler, scraper, etc.) |
| **GDPR** | General Data Protection Regulation (EU privacy law) |
| **Pseudonymization** | Data processing technique making data non-identifiable without additional info |
| **CSRF** | Cross-Site Request Forgery (security vulnerability) |
| **Rate Limiting** | Restriction on number of requests per time period |
| **TTL** | Time To Live (data expiration time) |

### References

**Standards:**
- GDPR: https://gdpr.eu/
- WCAG 2.1: https://www.w3.org/WAI/WCAG21/quickref/
- OWASP Top 10: https://owasp.org/www-project-top-ten/

**Technologies:**
- PHP PDO: https://www.php.net/manual/en/book.pdo.php
- MariaDB: https://mariadb.org/documentation/
- Beacon API: https://developer.mozilla.org/en-US/docs/Web/API/Beacon_API
- Canvas API: https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API
- WebGL API: https://developer.mozilla.org/en-US/docs/Web/API/WebGL_API
- Web Audio API: https://developer.mozilla.org/en-US/docs/Web/API/Web_Audio_API

**Similar Products:**
- Google Analytics 4: https://analytics.google.com/
- Matomo: https://matomo.org/
- Microsoft Clarity: https://clarity.microsoft.com/
- Hotjar: https://www.hotjar.com/
- Plausible: https://plausible.io/

---

## 17. CHANGE LOG

| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2025-11-23 | 1.0.0 | Initial documentation created | Claude |
| 2025-11-23 | 1.0.0 | Module #1 completed and documented | Claude |
| 2025-11-23 | 1.1.0 | Module #2 (Advanced Session Tracking) completed - 4 soubory, 1780 řádků kódu | Claude |
| 2025-11-23 | 1.2.0 | Module #3 (Bot Detection Engine) completed - 6 souborů (4 nové + 2 upravené), 2070 řádků kódu | Claude |
| 2025-11-23 | 1.3.0 | Module #4 (Geolocation Service) completed - 5 souborů (3 nové + 2 upravené), 983 řádků kódu | Claude |
| 2025-11-23 | 1.4.0 | Module #5 (Event Tracking Engine) completed - 4 soubory (3 nové + 1 upravený), 1326 řádků kódu | Claude |
| 2025-11-23 | 1.5.0 | Module #6 (Heatmap Engine) completed - 5 souborů, 1543 řádků kódu | Claude |
| 2025-11-23 | 1.6.0 | Module #7 (Session Replay Engine) completed - 9 souborů (7 nových + 2 upravené), 2251 řádků kódu | Claude |
| 2025-11-23 | 1.7.0 | Module #8 (UTM Campaign Tracking) completed - 6 souborů (5 nových + 1 upravený), 1768 řádků kódu | Claude |

---

**END OF NEWANAL.MD**

This document is the **SINGLE SOURCE OF TRUTH** for the Enterprise Analytics System project.

All future work must reference this document.

Any AI agent working on this project must read this document first.

**Last Updated:** 2025-11-23
**Status:** Modules #1-8 Complete, Modules #9-13 Pending Approval
