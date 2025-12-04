<?php
/**
 * GDPR Manager
 *
 * Správa GDPR compliance: consent management, data export/deletion, audit logging.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
 */

class GDPRManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================
    // CONSENT MANAGEMENT
    // ========================================

    /**
     * Zaznamenat consent
     *
     * @param string $fingerprintId
     * @param array $consents ['analytics' => true, 'marketing' => false, 'functional' => true]
     * @param string|null $privacyPolicyVersion
     * @return void
     */
    public function recordConsent(string $fingerprintId, array $consents, ?string $privacyPolicyVersion = '1.0'): void
    {
        $ip = $this->anonymizeIP($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_gdpr_consents (
                fingerprint_id,
                consent_analytics,
                consent_marketing,
                consent_functional,
                consent_ip,
                consent_user_agent,
                privacy_policy_version,
                consent_given_at
            ) VALUES (
                :fingerprint_id,
                :analytics,
                :marketing,
                :functional,
                :ip,
                :user_agent,
                :version,
                NOW()
            )
        ");

        $stmt->execute([
            'fingerprint_id' => $fingerprintId,
            'analytics' => $consents['analytics'] ?? 0,
            'marketing' => $consents['marketing'] ?? 0,
            'functional' => $consents['functional'] ?? 1, // Functional always enabled
            'ip' => $ip,
            'user_agent' => $userAgent,
            'version' => $privacyPolicyVersion
        ]);

        // Audit log
        $this->logGDPRAction('consent_given', [
            'fingerprint_id' => $fingerprintId,
            'consents' => $consents,
            'version' => $privacyPolicyVersion
        ]);
    }

    /**
     * Odvolat consent
     */
    public function withdrawConsent(string $fingerprintId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_gdpr_consents
            SET consent_withdrawn = 1,
                withdrawn_at = NOW(),
                consent_analytics = 0,
                consent_marketing = 0
            WHERE fingerprint_id = :fingerprint_id
            AND consent_withdrawn = 0
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);

        // Audit log
        $this->logGDPRAction('consent_withdrawn', [
            'fingerprint_id' => $fingerprintId
        ]);
    }

    /**
     * Získat consent status
     */
    public function getConsent(string $fingerprintId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM wgs_gdpr_consents
            WHERE fingerprint_id = :fingerprint_id
            AND consent_withdrawn = 0
            ORDER BY consent_given_at DESC
            LIMIT 1
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $consent = $stmt->fetch(PDO::FETCH_ASSOC);

        return $consent ?: null;
    }

    /**
     * Zkontrolovat, zda má uživatel consent pro analytics
     */
    public function hasAnalyticsConsent(string $fingerprintId): bool
    {
        $consent = $this->getConsent($fingerprintId);
        return $consent && (bool) $consent['consent_analytics'];
    }

    // ========================================
    // DATA SUBJECT RIGHTS
    // ========================================

    /**
     * Požádat o export dat (Right to Access - GDPR Article 15)
     *
     * @return int request_id
     */
    public function requestDataExport(string $fingerprintId, string $email): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_gdpr_data_requests (
                fingerprint_id,
                email,
                request_type,
                status,
                created_at
            ) VALUES (
                :fingerprint_id,
                :email,
                'export',
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            'fingerprint_id' => $fingerprintId,
            'email' => $email
        ]);

        $requestId = (int) $this->pdo->lastInsertId();

        // Audit log
        $this->logGDPRAction('data_export_requested', [
            'fingerprint_id' => $fingerprintId,
            'request_id' => $requestId,
            'email' => $email
        ]);

        return $requestId;
    }

    /**
     * Zpracovat export dat
     */
    public function processDataExport(int $requestId): string
    {
        // Získat request
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_gdpr_data_requests WHERE request_id = :id");
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Request not found');
        }

        // Update status
        $this->pdo->prepare("UPDATE wgs_gdpr_data_requests SET status = 'processing' WHERE request_id = :id")
            ->execute(['id' => $requestId]);

        // Collect all data
        $data = $this->collectAllUserData($request['fingerprint_id']);

        // Generate export file
        $exportData = [
            'fingerprint_id' => $request['fingerprint_id'],
            'export_date' => date('Y-m-d H:i:s'),
            'data' => $data
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Save to file
        $exportDir = __DIR__ . '/../exports/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = 'gdpr_export_' . $request['fingerprint_id'] . '_' . time() . '.json';
        $filepath = $exportDir . $filename;

        file_put_contents($filepath, $json);

        // Update request
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $stmt = $this->pdo->prepare("
            UPDATE wgs_gdpr_data_requests
            SET status = 'completed',
                processed_at = NOW(),
                processed_by = 'system',
                export_file_path = :filepath,
                export_expires_at = :expires_at
            WHERE request_id = :id
        ");

        $stmt->execute([
            'filepath' => $filepath,
            'expires_at' => $expiresAt,
            'id' => $requestId
        ]);

        // Audit log
        $this->logGDPRAction('data_exported', [
            'fingerprint_id' => $request['fingerprint_id'],
            'request_id' => $requestId,
            'export_file' => $filename
        ]);

        return $filepath;
    }

    /**
     * Požádat o smazání dat (Right to Be Forgotten - GDPR Article 17)
     */
    public function requestDataDeletion(string $fingerprintId, string $email): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_gdpr_data_requests (
                fingerprint_id,
                email,
                request_type,
                status,
                created_at
            ) VALUES (
                :fingerprint_id,
                :email,
                'delete',
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            'fingerprint_id' => $fingerprintId,
            'email' => $email
        ]);

        $requestId = (int) $this->pdo->lastInsertId();

        // Audit log
        $this->logGDPRAction('data_deletion_requested', [
            'fingerprint_id' => $fingerprintId,
            'request_id' => $requestId,
            'email' => $email
        ]);

        return $requestId;
    }

    /**
     * Zpracovat smazání dat
     */
    public function processDataDeletion(int $requestId): void
    {
        // Získat request
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_gdpr_data_requests WHERE request_id = :id");
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Request not found');
        }

        // Update status
        $this->pdo->prepare("UPDATE wgs_gdpr_data_requests SET status = 'processing' WHERE request_id = :id")
            ->execute(['id' => $requestId]);

        // Delete all user data
        $this->deleteUserData($request['fingerprint_id']);

        // Update request
        $stmt = $this->pdo->prepare("
            UPDATE wgs_gdpr_data_requests
            SET status = 'completed',
                processed_at = NOW(),
                processed_by = 'system'
            WHERE request_id = :id
        ");

        $stmt->execute(['id' => $requestId]);

        // Audit log (s NULL fingerprint protože je smazán)
        $this->logGDPRAction('data_deleted', [
            'fingerprint_id' => null,
            'request_id' => $requestId,
            'original_fingerprint' => $request['fingerprint_id']
        ]);
    }

    /**
     * Anonymizovat data uživatele (zachovat agregovaná data)
     */
    public function anonymizeUserData(string $fingerprintId): void
    {
        // Anonymizovat sessions
        $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET fingerprint_id = SHA2(CONCAT(fingerprint_id, UNIX_TIMESTAMP()), 256)
            WHERE fingerprint_id = :fingerprint_id
        ")->execute(['fingerprint_id' => $fingerprintId]);

        // Anonymizovat pageviews
        $this->pdo->prepare("
            UPDATE wgs_pageviews
            SET fingerprint_id = SHA2(CONCAT(fingerprint_id, UNIX_TIMESTAMP()), 256),
                ip = '0.0.0.0'
            WHERE fingerprint_id = :fingerprint_id
        ")->execute(['fingerprint_id' => $fingerprintId]);

        // Anonymizovat events
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_events'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("
                UPDATE wgs_analytics_events
                SET fingerprint_id = SHA2(CONCAT(fingerprint_id, UNIX_TIMESTAMP()), 256)
                WHERE fingerprint_id = :fingerprint_id
            ")->execute(['fingerprint_id' => $fingerprintId]);
        }

        // Audit log
        $this->logGDPRAction('data_anonymized', ['fingerprint_id' => $fingerprintId]);
    }

    /**
     * Smazat všechna data uživatele
     */
    private function deleteUserData(string $fingerprintId): void
    {
        // Sessions
        $this->pdo->prepare("DELETE FROM wgs_analytics_sessions WHERE fingerprint_id = :fid")
            ->execute(['fid' => $fingerprintId]);

        // Pageviews
        $this->pdo->prepare("DELETE FROM wgs_pageviews WHERE fingerprint_id = :fid")
            ->execute(['fid' => $fingerprintId]);

        // Events (pokud existuje)
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_events'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_events WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // Heatmaps
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_clicks'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_heatmap_clicks WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // Session replay
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_replay_frames'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_replay_frames WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // Conversions
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_conversions'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_conversions WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // User scores
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_user_scores'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_user_scores WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // Real-time
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_realtime'");
        if ($tableCheck->rowCount() > 0) {
            $this->pdo->prepare("DELETE FROM wgs_analytics_realtime WHERE fingerprint_id = :fid")
                ->execute(['fid' => $fingerprintId]);
        }

        // Consents
        $this->pdo->prepare("DELETE FROM wgs_gdpr_consents WHERE fingerprint_id = :fid")
            ->execute(['fid' => $fingerprintId]);
    }

    /**
     * Collect all user data
     */
    private function collectAllUserData(string $fingerprintId): array
    {
        $data = [];

        // Sessions
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_sessions WHERE fingerprint_id = :fid");
        $stmt->execute(['fid' => $fingerprintId]);
        $data['sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pageviews
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_pageviews WHERE fingerprint_id = :fid");
        $stmt->execute(['fid' => $fingerprintId]);
        $data['pageviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Events (pokud existuje)
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_events'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_events WHERE fingerprint_id = :fid");
            $stmt->execute(['fid' => $fingerprintId]);
            $data['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Conversions
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_conversions'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_conversions WHERE fingerprint_id = :fid");
            $stmt->execute(['fid' => $fingerprintId]);
            $data['conversions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Consents
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_gdpr_consents WHERE fingerprint_id = :fid");
        $stmt->execute(['fid' => $fingerprintId]);
        $data['consents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // User scores
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_user_scores'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_user_scores WHERE fingerprint_id = :fid");
            $stmt->execute(['fid' => $fingerprintId]);
            $data['user_scores'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $data;
    }

    // ========================================
    // RETENTION POLICY
    // ========================================

    /**
     * Aplikovat retention policy (smazat data starší než X dní)
     *
     * @param int $retentionDays Default 730 dní (2 roky)
     * @return int Počet smazaných záznamů
     */
    public function applyRetentionPolicy(int $retentionDays = 730): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        $deletedCount = 0;

        // Sessions
        $stmt = $this->pdo->prepare("
            DELETE FROM wgs_analytics_sessions
            WHERE DATE(session_start) < :cutoff_date
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);
        $deletedCount += $stmt->rowCount();

        // Pageviews
        $stmt = $this->pdo->prepare("
            DELETE FROM wgs_pageviews
            WHERE DATE(datum) < :cutoff_date
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);
        $deletedCount += $stmt->rowCount();

        // Events (pokud existuje)
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_events'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->pdo->prepare("
                DELETE FROM wgs_analytics_events
                WHERE DATE(event_timestamp) < :cutoff_date
            ");
            $stmt->execute(['cutoff_date' => $cutoffDate]);
            $deletedCount += $stmt->rowCount();
        }

        // Audit log
        $this->logGDPRAction('retention_policy_applied', [
            'cutoff_date' => $cutoffDate,
            'retention_days' => $retentionDays,
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }

    // ========================================
    // AUDIT LOGGING
    // ========================================

    /**
     * Log GDPR action
     */
    public function logGDPRAction(string $actionType, array $details, ?string $legalBasis = 'consent'): void
    {
        $ip = $this->anonymizeIP($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_gdpr_audit_log (
                fingerprint_id,
                action_type,
                action_details,
                user_ip,
                user_agent,
                legal_basis,
                created_at
            ) VALUES (
                :fingerprint_id,
                :action_type,
                :action_details,
                :user_ip,
                :user_agent,
                :legal_basis,
                NOW()
            )
        ");

        $stmt->execute([
            'fingerprint_id' => $details['fingerprint_id'] ?? null,
            'action_type' => $actionType,
            'action_details' => json_encode($details),
            'user_ip' => $ip,
            'user_agent' => $userAgent,
            'legal_basis' => $legalBasis
        ]);
    }

    /**
     * Získat audit log pro fingerprint
     */
    public function getAuditLog(string $fingerprintId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM wgs_gdpr_audit_log
            WHERE fingerprint_id = :fingerprint_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->execute([
            'fingerprint_id' => $fingerprintId,
            'limit' => $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // UTILITIES
    // ========================================

    /**
     * Anonymizovat IP adresu (GDPR requirement)
     */
    private function anonymizeIP(string $ip): string
    {
        if (strpos($ip, '.') !== false) {
            // IPv4 - maskovat poslední oktet
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        } else {
            // IPv6 - maskovat posledních 80 bitů
            return substr($ip, 0, 19) . '::';
        }
    }
}
?>
