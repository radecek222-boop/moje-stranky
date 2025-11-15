<?php
/**
 * Rate Limiter - Omezení počtu požadavků
 * Ochrana proti spamování a DoS útokům
 */

class RateLimiter {
    private $pdo;
    private $tableName = 'wgs_rate_limits';

    public     /**
     *   construct
     *
     * @param mixed $pdo Pdo
     */
function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    /**
     * Vytvoří tabulku pro rate limiting pokud neexistuje
     */
    private     /**
     * EnsureTableExists
     */
function ensureTableExists() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `identifier` VARCHAR(255) NOT NULL COMMENT 'IP adresa nebo user ID',
                `action_type` VARCHAR(100) NOT NULL COMMENT 'Typ akce (email, sms, api)',
                `attempt_count` INT(11) UNSIGNED NOT NULL DEFAULT 1,
                `first_attempt_at` DATETIME NOT NULL,
                `last_attempt_at` DATETIME NOT NULL,
                `blocked_until` DATETIME NULL DEFAULT NULL,
                INDEX `idx_identifier_action` (`identifier`, `action_type`),
                INDEX `idx_blocked` (`blocked_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Tabulka už existuje nebo jiná chyba - ignoruj
            error_log("RateLimiter: Table creation failed: " . $e->getMessage());
        }
    }

    /**
     * Zkontroluje, jestli je akce povolena
     *
     * @param string $identifier - Identifikátor (IP nebo user ID)
     * @param string $actionType - Typ akce (email, sms, api)
     * @param array $limits - Limity [
     *   'max_attempts' => 5,       // Max pokusů
     *   'window_minutes' => 10,    // Časové okno v minutách
     *   'block_minutes' => 60      // Doba blokování při překročení
     * ]
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => string|null, 'message' => string]
     */
    public     /**
     * CheckLimit
     *
     * @param mixed $identifier Identifier
     * @param mixed $actionType ActionType
     * @param mixed $limits Limits
     */
function checkLimit($identifier, $actionType, $limits = []) {
        // Výchozí limity
        $maxAttempts = $limits['max_attempts'] ?? 5;
        $windowMinutes = $limits['window_minutes'] ?? 10;
        $blockMinutes = $limits['block_minutes'] ?? 60;

        try {
            // Vyčisti staré záznamy
            $this->cleanup();

            // Zkontroluj, jestli není zablokovaný
            $blocked = $this->isBlocked($identifier, $actionType);
            if ($blocked) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $blocked['blocked_until'],
                    'message' => 'Příliš mnoho pokusů. Zkuste to prosím později.'
                ];
            }

            // CRITICAL FIX: Zahájit transakci pro ochranu proti race condition
            $this->pdo->beginTransaction();

            // Načti aktuální počet pokusů v časovém okně
            $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

            // CRITICAL FIX: FOR UPDATE lock pro ochranu proti concurrent updates
            $stmt = $this->pdo->prepare("
                SELECT * FROM `{$this->tableName}`
                WHERE identifier = :identifier
                  AND action_type = :action_type
                  AND first_attempt_at >= :window_start
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':identifier' => $identifier,
                ':action_type' => $actionType,
                ':window_start' => $windowStart
            ]);

            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $attemptCount = (int)$record['attempt_count'];

                if ($attemptCount >= $maxAttempts) {
                    // Překročen limit - zablokuj
                    $blockedUntil = date('Y-m-d H:i:s', strtotime("+{$blockMinutes} minutes"));

                    $updateStmt = $this->pdo->prepare("
                        UPDATE `{$this->tableName}`
                        SET blocked_until = :blocked_until,
                            last_attempt_at = NOW()
                        WHERE id = :id
                    ");

                    $updateStmt->execute([
                        ':blocked_until' => $blockedUntil,
                        ':id' => $record['id']
                    ]);

                    // CRITICAL FIX: COMMIT transakce
                    $this->pdo->commit();

                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_at' => $blockedUntil,
                        'message' => "Překročen limit pokusů ({$maxAttempts} za {$windowMinutes} minut). Zkuste to za " . $this->formatTimeRemaining($blockedUntil) . "."
                    ];
                }

                // Zvýš počítadlo
                $updateStmt = $this->pdo->prepare("
                    UPDATE `{$this->tableName}`
                    SET attempt_count = attempt_count + 1,
                        last_attempt_at = NOW()
                    WHERE id = :id
                ");

                $updateStmt->execute([':id' => $record['id']]);

                $remaining = $maxAttempts - ($attemptCount + 1);

                // CRITICAL FIX: COMMIT transakce
                $this->pdo->commit();

                return [
                    'allowed' => true,
                    'remaining' => max(0, $remaining),
                    'reset_at' => date('Y-m-d H:i:s', strtotime($record['first_attempt_at'] . " +{$windowMinutes} minutes")),
                    'message' => "OK. Zbývá {$remaining} pokusů."
                ];
            } else {
                // První pokus v časovém okně - vytvoř nový záznam
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO `{$this->tableName}` (identifier, action_type, attempt_count, first_attempt_at, last_attempt_at)
                    VALUES (:identifier, :action_type, 1, NOW(), NOW())
                ");

                $insertStmt->execute([
                    ':identifier' => $identifier,
                    ':action_type' => $actionType
                ]);

                // CRITICAL FIX: COMMIT transakce
                $this->pdo->commit();

                return [
                    'allowed' => true,
                    'remaining' => $maxAttempts - 1,
                    'reset_at' => date('Y-m-d H:i:s', strtotime("+{$windowMinutes} minutes")),
                    'message' => 'OK'
                ];
            }
        } catch (PDOException $e) {
            // CRITICAL FIX: ROLLBACK transakce při chybě
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("RateLimiter error: " . $e->getMessage());
            // V případě chyby povolíme požadavek (fail-open)
            return [
                'allowed' => true,
                'remaining' => null,
                'reset_at' => null,
                'message' => 'Rate limiter nedostupný - pokračuji'
            ];
        }
    }

    /**
     * Zkontroluje, jestli je identifikátor zablokovaný
     */
    private     /**
     * IsBlocked
     *
     * @param mixed $identifier Identifier
     * @param mixed $actionType ActionType
     */
function isBlocked($identifier, $actionType) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `{$this->tableName}`
            WHERE identifier = :identifier
              AND action_type = :action_type
              AND blocked_until IS NOT NULL
              AND blocked_until > NOW()
            ORDER BY blocked_until DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Vyčistí staré záznamy (starší než 24 hodin)
     */
    private     /**
     * Cleanup
     */
function cleanup() {
        try {
            $this->pdo->exec("
                DELETE FROM `{$this->tableName}`
                WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND (blocked_until IS NULL OR blocked_until < NOW())
            ");
        } catch (PDOException $e) {
            error_log("RateLimiter cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Formátuje zbývající čas
     */
    private     /**
     * FormatTimeRemaining
     *
     * @param mixed $blockedUntil BlockedUntil
     */
function formatTimeRemaining($blockedUntil) {
        $remaining = strtotime($blockedUntil) - time();

        if ($remaining < 60) {
            return $remaining . ' sekund';
        } elseif ($remaining < 3600) {
            return ceil($remaining / 60) . ' minut';
        } else {
            return ceil($remaining / 3600) . ' hodin';
        }
    }

    /**
     * Resetuje limity pro daný identifikátor (admin funkce)
     */
    public     /**
     * Reset
     *
     * @param mixed $identifier Identifier
     * @param mixed $actionType ActionType
     */
function reset($identifier, $actionType = null) {
        try {
            if ($actionType) {
                $stmt = $this->pdo->prepare("
                    DELETE FROM `{$this->tableName}`
                    WHERE identifier = :identifier AND action_type = :action_type
                ");
                $stmt->execute([
                    ':identifier' => $identifier,
                    ':action_type' => $actionType
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    DELETE FROM `{$this->tableName}`
                    WHERE identifier = :identifier
                ");
                $stmt->execute([':identifier' => $identifier]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("RateLimiter reset error: " . $e->getMessage());
            return false;
        }
    }
}
