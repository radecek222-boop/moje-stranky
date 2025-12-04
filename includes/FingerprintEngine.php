<?php
/**
 * FINGERPRINT ENGINE
 *
 * Handles device fingerprinting for cross-session user tracking.
 * Implements storage, retrieval, similarity detection, and session linking.
 *
 * Module #1 of Enterprise Analytics System
 *
 * Features:
 * - SHA-256 fingerprint hashing
 * - Component-based similarity scoring
 * - Session merging and linking
 * - GDPR-compliant pseudonymous identifiers
 * - Device mapping (multiple UAs per fingerprint)
 *
 * @package WGS_Analytics
 * @version 1.0.0
 */

class FingerprintEngine {

    /**
     * Database connection
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Similarity threshold for fingerprint matching (0.0 to 1.0)
     * @var float
     */
    private const SIMILARITY_THRESHOLD = 0.85;

    /**
     * Component weights for similarity calculation
     * @var array
     */
    private const COMPONENT_WEIGHTS = [
        'canvas_hash' => 0.30,      // 30% weight
        'webgl' => 0.25,            // 25% weight (vendor + renderer)
        'audio_hash' => 0.20,       // 20% weight
        'screen' => 0.15,           // 15% weight (resolution + color depth)
        'other' => 0.10             // 10% weight (timezone, platform, etc.)
    ];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Store or update a fingerprint in the database
     *
     * @param array $components Fingerprint components from client
     * @return array ['fingerprint_id' => string, 'is_new' => bool, 'session_count' => int, ...]
     * @throws InvalidArgumentException if components are invalid
     * @throws PDOException if database error occurs
     */
    public function storeFingerprint(array $components): array {
        // Validate components
        $this->validateComponents($components);

        // Calculate fingerprint ID
        $fingerprintId = $this->calculateFingerprintId($components);

        // Check if fingerprint exists
        $stmt = $this->pdo->prepare("
            SELECT id, fingerprint_id, session_count, first_seen, last_seen, device_map
            FROM wgs_analytics_fingerprints
            WHERE fingerprint_id = :fingerprint_id
            LIMIT 1
        ");
        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Fingerprint exists - update
            $newSessionCount = $existing['session_count'] + 1;

            $updateStmt = $this->pdo->prepare("
                UPDATE wgs_analytics_fingerprints
                SET last_seen = NOW(),
                    session_count = :session_count
                WHERE fingerprint_id = :fingerprint_id
            ");
            $updateStmt->execute([
                'session_count' => $newSessionCount,
                'fingerprint_id' => $fingerprintId
            ]);

            // Update device map with new user agent if provided
            if (isset($components['user_agent'])) {
                $this->updateDeviceMap($fingerprintId, $components['user_agent']);
            }

            return [
                'fingerprint_id' => $fingerprintId,
                'is_new' => false,
                'session_count' => $newSessionCount,
                'first_seen' => $existing['first_seen'],
                'last_seen' => date('Y-m-d H:i:s')
            ];

        } else {
            // New fingerprint - insert
            $insertStmt = $this->pdo->prepare("
                INSERT INTO wgs_analytics_fingerprints (
                    fingerprint_id, canvas_hash, webgl_vendor, webgl_renderer,
                    audio_hash, timezone, timezone_offset,
                    screen_width, screen_height, color_depth, pixel_ratio,
                    avail_width, avail_height,
                    touch_support, hardware_concurrency, device_memory,
                    platform, max_touch_points,
                    plugins_hash, fonts_hash,
                    device_map, first_seen, last_seen, session_count
                ) VALUES (
                    :fingerprint_id, :canvas_hash, :webgl_vendor, :webgl_renderer,
                    :audio_hash, :timezone, :timezone_offset,
                    :screen_width, :screen_height, :color_depth, :pixel_ratio,
                    :avail_width, :avail_height,
                    :touch_support, :hardware_concurrency, :device_memory,
                    :platform, :max_touch_points,
                    :plugins_hash, :fonts_hash,
                    :device_map, NOW(), NOW(), 1
                )
            ");

            // Prepare device map JSON
            $deviceMap = isset($components['user_agent']) ?
                json_encode([$components['user_agent'] => 1]) :
                json_encode([]);

            $insertStmt->execute([
                'fingerprint_id' => $fingerprintId,
                'canvas_hash' => $components['canvas_hash'] ?? null,
                'webgl_vendor' => $components['webgl_vendor'] ?? null,
                'webgl_renderer' => $components['webgl_renderer'] ?? null,
                'audio_hash' => $components['audio_hash'] ?? null,
                'timezone' => $components['timezone'] ?? null,
                'timezone_offset' => $components['timezone_offset'] ?? null,
                'screen_width' => $components['screen_width'] ?? null,
                'screen_height' => $components['screen_height'] ?? null,
                'color_depth' => $components['color_depth'] ?? null,
                'pixel_ratio' => $components['pixel_ratio'] ?? null,
                'avail_width' => $components['avail_width'] ?? null,
                'avail_height' => $components['avail_height'] ?? null,
                'touch_support' => $components['touch_support'] ?? 0,
                'hardware_concurrency' => $components['hardware_concurrency'] ?? null,
                'device_memory' => $components['device_memory'] ?? null,
                'platform' => $components['platform'] ?? null,
                'max_touch_points' => $components['max_touch_points'] ?? null,
                'plugins_hash' => $components['plugins_hash'] ?? null,
                'fonts_hash' => $components['fonts_hash'] ?? null,
                'device_map' => $deviceMap
            ]);

            $now = date('Y-m-d H:i:s');

            return [
                'fingerprint_id' => $fingerprintId,
                'is_new' => true,
                'session_count' => 1,
                'first_seen' => $now,
                'last_seen' => $now
            ];
        }
    }

    /**
     * Retrieve a fingerprint by ID
     *
     * @param string $fingerprintId The fingerprint hash
     * @return array|null Fingerprint data or null if not found
     */
    public function getFingerprint(string $fingerprintId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM wgs_analytics_fingerprints
            WHERE fingerprint_id = :fingerprint_id
            LIMIT 1
        ");
        $stmt->execute(['fingerprint_id' => $fingerprintId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['device_map'])) {
            $result['device_map'] = json_decode($result['device_map'], true);
        }

        return $result ?: null;
    }

    /**
     * Update last_seen timestamp for a fingerprint
     *
     * @param string $fingerprintId The fingerprint hash
     * @return bool Success status
     */
    public function updateLastSeen(string $fingerprintId): bool {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_fingerprints
            SET last_seen = NOW()
            WHERE fingerprint_id = :fingerprint_id
        ");

        return $stmt->execute(['fingerprint_id' => $fingerprintId]);
    }

    /**
     * Link a fingerprint to sessions and pageviews
     *
     * @param string $fingerprintId The fingerprint hash
     * @param string $sessionId The session ID
     * @return bool Success status
     */
    public function linkToSession(string $fingerprintId, string $sessionId): bool {
        // Update wgs_pageviews
        $stmt1 = $this->pdo->prepare("
            UPDATE wgs_pageviews
            SET fingerprint_id = :fingerprint_id
            WHERE session_id = :session_id
            AND fingerprint_id IS NULL
        ");

        $result1 = $stmt1->execute([
            'fingerprint_id' => $fingerprintId,
            'session_id' => $sessionId
        ]);

        // Update wgs_analytics_sessions if table exists
        try {
            $stmt2 = $this->pdo->prepare("
                UPDATE wgs_analytics_sessions
                SET fingerprint_id = :fingerprint_id
                WHERE session_id = :session_id
                AND fingerprint_id IS NULL
            ");

            $stmt2->execute([
                'fingerprint_id' => $fingerprintId,
                'session_id' => $sessionId
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet (Module #2)
            // This is OK - will be linked when table is created
        }

        return $result1;
    }

    /**
     * Find similar fingerprints (for handling browser updates)
     *
     * @param array $components Fingerprint components
     * @param float $threshold Similarity threshold (0.0 to 1.0)
     * @return array Array of similar fingerprints with similarity scores
     */
    public function findSimilarFingerprints(array $components, float $threshold = 0.85): array {
        // Get candidate fingerprints (same canvas or webgl)
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM wgs_analytics_fingerprints
            WHERE canvas_hash = :canvas_hash
               OR (webgl_vendor = :webgl_vendor AND webgl_renderer = :webgl_renderer)
            LIMIT 100
        ");

        $stmt->execute([
            'canvas_hash' => $components['canvas_hash'] ?? '',
            'webgl_vendor' => $components['webgl_vendor'] ?? '',
            'webgl_renderer' => $components['webgl_renderer'] ?? ''
        ]);

        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $similar = [];

        foreach ($candidates as $candidate) {
            $similarity = $this->calculateSimilarity($components, $candidate);

            if ($similarity >= $threshold) {
                $similar[] = [
                    'fingerprint_id' => $candidate['fingerprint_id'],
                    'similarity' => $similarity,
                    'session_count' => $candidate['session_count'],
                    'first_seen' => $candidate['first_seen'],
                    'last_seen' => $candidate['last_seen']
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return $similar;
    }

    /**
     * Get statistics for a fingerprint
     *
     * @param string $fingerprintId The fingerprint hash
     * @return array Statistics (session_count, first_seen, last_seen, etc.)
     */
    public function getFingerprintStats(string $fingerprintId): array {
        $stmt = $this->pdo->prepare("
            SELECT
                session_count,
                first_seen,
                last_seen,
                DATEDIFF(last_seen, first_seen) as days_active,
                device_map
            FROM wgs_analytics_fingerprints
            WHERE fingerprint_id = :fingerprint_id
            LIMIT 1
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['device_map'])) {
            $deviceMap = json_decode($result['device_map'], true);
            $result['unique_user_agents'] = count($deviceMap ?? []);
            $result['device_map'] = $deviceMap;
        }

        return $result ?: [];
    }

    /**
     * Calculate fingerprint ID from components
     *
     * Uses SHA-256 hash of normalized components for consistency.
     *
     * @param array $components All fingerprint components
     * @return string SHA-256 hash (32 characters)
     */
    private function calculateFingerprintId(array $components): string {
        // Extract core components for fingerprint
        $coreComponents = [
            'canvas' => $components['canvas_hash'] ?? '',
            'webgl_vendor' => $components['webgl_vendor'] ?? '',
            'webgl_renderer' => $components['webgl_renderer'] ?? '',
            'audio' => $components['audio_hash'] ?? '',
            'screen_width' => $components['screen_width'] ?? 0,
            'screen_height' => $components['screen_height'] ?? 0,
            'color_depth' => $components['color_depth'] ?? 0,
            'pixel_ratio' => $components['pixel_ratio'] ?? 1.0,
            'timezone' => $components['timezone'] ?? '',
            'platform' => $components['platform'] ?? ''
        ];

        // Sort keys for consistency
        ksort($coreComponents);

        // Serialize to JSON
        $serialized = json_encode($coreComponents);

        // SHA-256 hash
        $hash = hash('sha256', $serialized);

        // Return first 32 characters (sufficient uniqueness)
        return substr($hash, 0, 32);
    }

    /**
     * Hash a single component
     *
     * @param mixed $data Component data
     * @return string SHA-256 hash
     */
    private function hashComponent($data): string {
        $serialized = is_array($data) ? json_encode($data) : (string)$data;
        return hash('sha256', $serialized);
    }

    /**
     * Calculate similarity score between two fingerprints
     *
     * Uses weighted component matching for robust similarity detection.
     *
     * @param array $fp1 First fingerprint components
     * @param array $fp2 Second fingerprint components (from database)
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarity(array $fp1, array $fp2): float {
        $score = 0.0;

        // Canvas hash (30% weight) - exact match or no match
        if (isset($fp1['canvas_hash']) && isset($fp2['canvas_hash'])) {
            $score += ($fp1['canvas_hash'] === $fp2['canvas_hash']) ? 0.30 : 0.0;
        }

        // WebGL (25% weight) - vendor and renderer
        if (isset($fp1['webgl_vendor']) && isset($fp2['webgl_vendor']) &&
            isset($fp1['webgl_renderer']) && isset($fp2['webgl_renderer'])) {

            $vendorMatch = ($fp1['webgl_vendor'] === $fp2['webgl_vendor']) ? 0.125 : 0.0;
            $rendererMatch = ($fp1['webgl_renderer'] === $fp2['webgl_renderer']) ? 0.125 : 0.0;
            $score += $vendorMatch + $rendererMatch;
        }

        // Audio hash (20% weight) - exact match or no match
        if (isset($fp1['audio_hash']) && isset($fp2['audio_hash'])) {
            $score += ($fp1['audio_hash'] === $fp2['audio_hash']) ? 0.20 : 0.0;
        }

        // Screen properties (15% weight) - resolution and color depth
        if (isset($fp1['screen_width']) && isset($fp2['screen_width']) &&
            isset($fp1['screen_height']) && isset($fp2['screen_height']) &&
            isset($fp1['color_depth']) && isset($fp2['color_depth'])) {

            $widthMatch = ($fp1['screen_width'] === $fp2['screen_width']) ? 0.05 : 0.0;
            $heightMatch = ($fp1['screen_height'] === $fp2['screen_height']) ? 0.05 : 0.0;
            $depthMatch = ($fp1['color_depth'] === $fp2['color_depth']) ? 0.05 : 0.0;
            $score += $widthMatch + $heightMatch + $depthMatch;
        }

        // Other components (10% weight) - timezone, platform
        if (isset($fp1['timezone']) && isset($fp2['timezone'])) {
            $score += ($fp1['timezone'] === $fp2['timezone']) ? 0.05 : 0.0;
        }

        if (isset($fp1['platform']) && isset($fp2['platform'])) {
            $score += ($fp1['platform'] === $fp2['platform']) ? 0.05 : 0.0;
        }

        return round($score, 2);
    }

    /**
     * Update device_map JSON with new user agent
     *
     * Tracks different user agents seen with the same fingerprint.
     *
     * @param string $fingerprintId The fingerprint hash
     * @param string $userAgent User agent string
     * @return void
     */
    private function updateDeviceMap(string $fingerprintId, string $userAgent): void {
        // Get current device_map
        $stmt = $this->pdo->prepare("
            SELECT device_map
            FROM wgs_analytics_fingerprints
            WHERE fingerprint_id = :fingerprint_id
            LIMIT 1
        ");
        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return;

        $deviceMap = json_decode($result['device_map'] ?? '{}', true);

        // Increment count for this UA or set to 1 if new
        $deviceMap[$userAgent] = ($deviceMap[$userAgent] ?? 0) + 1;

        // Update database
        $updateStmt = $this->pdo->prepare("
            UPDATE wgs_analytics_fingerprints
            SET device_map = :device_map
            WHERE fingerprint_id = :fingerprint_id
        ");

        $updateStmt->execute([
            'device_map' => json_encode($deviceMap),
            'fingerprint_id' => $fingerprintId
        ]);
    }

    /**
     * Validate fingerprint components
     *
     * Ensures all required components are present and valid.
     *
     * @param array $components Components to validate
     * @return bool Valid or not
     * @throws InvalidArgumentException if invalid
     */
    private function validateComponents(array $components): bool {
        // Required components (at least one must be present)
        $hasAnyComponent = isset($components['canvas_hash']) ||
                          isset($components['webgl_vendor']) ||
                          isset($components['audio_hash']);

        if (!$hasAnyComponent) {
            throw new InvalidArgumentException(
                'At least one fingerprint component (canvas, webgl, or audio) must be provided'
            );
        }

        // Validate numeric fields if present
        if (isset($components['screen_width']) && (!is_numeric($components['screen_width']) || $components['screen_width'] <= 0)) {
            throw new InvalidArgumentException('Invalid screen_width');
        }

        if (isset($components['screen_height']) && (!is_numeric($components['screen_height']) || $components['screen_height'] <= 0)) {
            throw new InvalidArgumentException('Invalid screen_height');
        }

        if (isset($components['pixel_ratio']) && (!is_numeric($components['pixel_ratio']) || $components['pixel_ratio'] < 1.0)) {
            throw new InvalidArgumentException('Invalid pixel_ratio');
        }

        if (isset($components['timezone_offset']) && !is_numeric($components['timezone_offset'])) {
            throw new InvalidArgumentException('Invalid timezone_offset');
        }

        return true;
    }
}
