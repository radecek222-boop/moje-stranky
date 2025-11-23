<?php
/**
 * Recalculate User Scores - Cron Job
 *
 * Denní přepočítání engagement, frustration a interest scores
 * pro všechny sessions starší než 1 den.
 *
 * Spouštět: Denní v 02:00
 * Cron: 0 2 * * * php /path/to/scripts/recalculate_user_scores.php
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #10 - User Interest AI Scoring
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/UserScoreCalculator.php';

// ========================================
// KONFIGURACE
// ========================================
$daysBack = 7; // Přepočítat scores pro sessions z posledních 7 dní
$batchSize = 100; // Zpracovat po 100 sessions
$debug = true; // Výpis do konzole

// ========================================
// SPUŠTĚNÍ
// ========================================
echo "==========================================\n";
echo "RECALCULATE USER SCORES - START\n";
echo "==========================================\n";
echo "Datum: " . date('Y-m-d H:i:s') . "\n";
echo "Days back: {$daysBack}\n";
echo "Batch size: {$batchSize}\n\n";

try {
    $pdo = getDbConnection();
    $scoreCalculator = new UserScoreCalculator($pdo);

    // ========================================
    // 1. NAJÍT SESSIONS BEZ SCORES
    // ========================================
    echo "1️⃣  Hledám sessions bez scores...\n";

    $sql = "
    SELECT s.session_id
    FROM wgs_analytics_sessions s
    LEFT JOIN wgs_analytics_user_scores us ON s.session_id = us.session_id
    WHERE us.session_id IS NULL
    AND DATE(s.session_start) >= DATE_SUB(CURDATE(), INTERVAL :days_back DAY)
    LIMIT :batch_size
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'days_back' => $daysBack,
        'batch_size' => $batchSize
    ]);
    $missingSessions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Nalezeno " . count($missingSessions) . " sessions bez scores\n\n";

    // ========================================
    // 2. PŘEPOČÍTAT CHYBĚJÍCÍ SCORES
    // ========================================
    if (count($missingSessions) > 0) {
        echo "2️⃣  Přepočítávám chybějící scores...\n";

        $processedCount = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($missingSessions as $sessionId) {
            try {
                $success = $scoreCalculator->aktualizujScores($sessionId);

                if ($success) {
                    $successCount++;
                    if ($debug) {
                        echo "  ✅ {$sessionId} - úspěšně přepočítáno\n";
                    }
                } else {
                    $errorCount++;
                    if ($debug) {
                        echo "  ❌ {$sessionId} - chyba při přepočítání\n";
                    }
                }

                $processedCount++;

            } catch (Exception $e) {
                $errorCount++;
                echo "  ❌ {$sessionId} - Exception: " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
        echo "Zpracováno: {$processedCount}\n";
        echo "Úspěšně: {$successCount}\n";
        echo "Chyby: {$errorCount}\n\n";
    }

    // ========================================
    // 3. AKTUALIZOVAT ZASTARALÉ SCORES
    // ========================================
    echo "3️⃣  Aktualizuji zastaralé scores (starší než 24h)...\n";

    $sql = "
    SELECT session_id
    FROM wgs_analytics_user_scores
    WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL :days_back DAY)
    LIMIT :batch_size
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'days_back' => $daysBack,
        'batch_size' => $batchSize
    ]);
    $outdatedSessions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Nalezeno " . count($outdatedSessions) . " zastaralých scores\n\n";

    if (count($outdatedSessions) > 0) {
        $processedCount = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($outdatedSessions as $sessionId) {
            try {
                $success = $scoreCalculator->aktualizujScores($sessionId);

                if ($success) {
                    $successCount++;
                    if ($debug) {
                        echo "  ✅ {$sessionId} - aktualizováno\n";
                    }
                } else {
                    $errorCount++;
                    if ($debug) {
                        echo "  ❌ {$sessionId} - chyba při aktualizaci\n";
                    }
                }

                $processedCount++;

            } catch (Exception $e) {
                $errorCount++;
                echo "  ❌ {$sessionId} - Exception: " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
        echo "Zpracováno: {$processedCount}\n";
        echo "Úspěšně: {$successCount}\n";
        echo "Chyby: {$errorCount}\n\n";
    }

    // ========================================
    // 4. STATISTIKY
    // ========================================
    echo "4️⃣  Finální statistiky...\n";

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_scores,
            AVG(engagement_score) as avg_engagement,
            AVG(frustration_score) as avg_frustration,
            AVG(interest_score) as avg_interest
        FROM wgs_analytics_user_scores
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL {$daysBack} DAY)
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Celkem scores: " . $stats['total_scores'] . "\n";
    echo "Průměrné engagement: " . round($stats['avg_engagement'], 2) . "\n";
    echo "Průměrná frustrace: " . round($stats['avg_frustration'], 2) . "\n";
    echo "Průměrný zájem: " . round($stats['avg_interest'], 2) . "\n\n";

    echo "==========================================\n";
    echo "RECALCULATE USER SCORES - DOKONČENO ✅\n";
    echo "==========================================\n";
    echo "Konec: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "\n";
    echo "==========================================\n";
    echo "CHYBA DATABÁZE ❌\n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);

} catch (Exception $e) {
    echo "\n";
    echo "==========================================\n";
    echo "NEOČEKÁVANÁ CHYBA ❌\n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
