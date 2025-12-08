<?php
/**
 * Generate Scheduled Reports - Cron Job
 *
 * Automatické generování naplánovaných reportů.
 * Spouští se denně v 06:00 a kontroluje všechny due schedules.
 *
 * Spouštět: Denně v 06:00
 * Cron: 0 6 * * * php /path/to/scripts/generate_scheduled_reports.php
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #12 - AI Reports Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/AIReportGenerator.php';

// ========================================
// KONFIGURACE
// ========================================
$debug = true; // Výpis do konzole

// ========================================
// SPUŠTĚNÍ
// ========================================
echo "==========================================\n";
echo "GENERATE SCHEDULED REPORTS - START\n";
echo "==========================================\n";
echo "Datum: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDbConnection();

    // ========================================
    // 1. NAJÍT DUE SCHEDULES
    // ========================================
    echo "1️⃣  Hledám due schedules...\n";

    $stmt = $pdo->query("
        SELECT * FROM wgs_analytics_report_schedules
        WHERE is_active = 1
        AND (next_run_at IS NULL OR next_run_at <= NOW())
        ORDER BY schedule_id ASC
    ");

    $dueSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Nalezeno: " . count($dueSchedules) . " due schedules\n\n";

    if (count($dueSchedules) === 0) {
        echo "Žádné due schedules k zpracování.\n";
        echo "==========================================\n";
        echo "GENERATE SCHEDULED REPORTS - KONEC\n";
        echo "==========================================\n";
        exit(0);
    }

    // ========================================
    // 2. ZPRACOVAT KAŽDÝ SCHEDULE
    // ========================================
    $generator = new AIReportGenerator($pdo);
    $successCount = 0;
    $errorCount = 0;

    foreach ($dueSchedules as $schedule) {
        echo "2️⃣  Zpracovávám schedule ID {$schedule['schedule_id']}: {$schedule['schedule_name']}\n";

        try {
            // Určit period based on frequency
            $period = determinePeriod($schedule['frequency'], $schedule['report_type']);

            echo "   Generuji {$schedule['report_type']} report pro {$period['start']} - {$period['end']}\n";

            // Generovat report
            $reportData = $generator->generujReport(
                $schedule['report_type'],
                $period['start'],
                $period['end']
            );

            // Uložit report
            $reportId = $generator->ulozReport($reportData);

            echo "   Report vygenerován: ID {$reportId}\n";

            // Email delivery (pokud nakonfigurováno)
            if ($schedule['delivery_method'] === 'email' && !empty($schedule['email_recipients'])) {
                $recipients = json_decode($schedule['email_recipients'], true);

                if (is_array($recipients) && count($recipients) > 0) {
                    sendReportEmail($reportId, $recipients, $reportData);
                    echo "   Email odeslán na: " . implode(', ', $recipients) . "\n";
                }
            }

            // Aktualizovat schedule (last_run_at a next_run_at)
            $nextRun = calculateNextRun($schedule);

            $updateStmt = $pdo->prepare("
                UPDATE wgs_analytics_report_schedules
                SET last_run_at = NOW(),
                    next_run_at = :next_run_at
                WHERE schedule_id = :id
            ");

            $updateStmt->execute([
                'next_run_at' => $nextRun,
                'id' => $schedule['schedule_id']
            ]);

            echo "   Next run: {$nextRun}\n";
            echo "   SUCCESS\n\n";

            $successCount++;

        } catch (Exception $e) {
            echo "   ERROR: " . $e->getMessage() . "\n\n";
            error_log("Schedule {$schedule['schedule_id']} failed: " . $e->getMessage());
            $errorCount++;
        }
    }

    // ========================================
    // 3. STATISTIKY
    // ========================================
    echo "3️⃣  Finální statistiky:\n";
    echo "Zpracováno: " . count($dueSchedules) . " schedules\n";
    echo "Úspěšné: {$successCount}\n";
    echo "Chyby: {$errorCount}\n\n";

    echo "==========================================\n";
    echo "GENERATE SCHEDULED REPORTS - DOKONČENO \n";
    echo "==========================================\n";
    echo "Konec: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "\n";
    echo "==========================================\n";
    echo "CHYBA DATABÁZE \n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);

} catch (Exception $e) {
    echo "\n";
    echo "==========================================\n";
    echo "NEOČEKÁVANÁ CHYBA \n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Určí period (start, end) based on frequency a report type
 */
function determinePeriod(string $frequency, string $reportType): array
{
    $now = new DateTime();

    switch ($reportType) {
        case 'daily':
            // Yesterday
            $end = new DateTime('yesterday');
            $start = clone $end;
            break;

        case 'weekly':
            // Last week (Monday - Sunday)
            $end = new DateTime('last sunday');
            $start = new DateTime('last monday', $end->getTimestamp());
            break;

        case 'monthly':
            // Last month
            $end = new DateTime('last day of last month');
            $start = new DateTime('first day of last month');
            break;

        default:
            // Fallback: yesterday
            $end = new DateTime('yesterday');
            $start = clone $end;
    }

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d')
    ];
}

/**
 * Vypočítá next_run_at timestamp
 */
function calculateNextRun(array $schedule): string
{
    $frequency = $schedule['frequency'];
    $timeOfDay = $schedule['time_of_day'] ?? '06:00:00';
    $dayOfWeek = $schedule['day_of_week'];
    $dayOfMonth = $schedule['day_of_month'];

    switch ($frequency) {
        case 'daily':
            $nextRun = new DateTime('tomorrow ' . $timeOfDay);
            break;

        case 'weekly':
            $targetDay = $dayOfWeek ?? 1; // Monday default
            $nextRun = new DateTime('next ' . getDayName($targetDay) . ' ' . $timeOfDay);
            break;

        case 'monthly':
            $targetDay = $dayOfMonth ?? 1;
            $nextRun = new DateTime('first day of next month ' . $timeOfDay);
            $nextRun->setDate(
                $nextRun->format('Y'),
                $nextRun->format('m'),
                min($targetDay, $nextRun->format('t'))
            );
            break;

        default:
            $nextRun = new DateTime('tomorrow ' . $timeOfDay);
    }

    return $nextRun->format('Y-m-d H:i:s');
}

/**
 * Vrátí název dne based on číslo (1-7)
 */
function getDayName(int $dayOfWeek): string
{
    $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];

    return $days[$dayOfWeek] ?? 'Monday';
}

/**
 * Odešle report emailem
 */
function sendReportEmail(int $reportId, array $recipients, array $reportData): void
{
    // Simplified email sending (použijte PHPMailer v produkci)
    $subject = "Analytics Report: {$reportData['period']['start']} - {$reportData['period']['end']}";

    $body = "Nový analytics report je k dispozici.\n\n";
    $body .= "Report ID: {$reportId}\n";
    $body .= "Typ: {$reportData['report_type']}\n";
    $body .= "Perioda: {$reportData['period']['start']} - {$reportData['period']['end']}\n\n";
    $body .= "Summary:\n";
    $body .= "- Sessions: {$reportData['summary']['total_sessions']}\n";
    $body .= "- Pageviews: {$reportData['summary']['total_pageviews']}\n";
    $body .= "- Bounce rate: {$reportData['summary']['bounce_rate']}%\n";
    $body .= "- Conversion rate: {$reportData['summary']['conversion_rate']}%\n\n";
    $body .= "Stáhněte si kompletní report: https://www.wgs-service.cz/analytics-reports.php\n";

    foreach ($recipients as $email) {
        mail($email, $subject, $body, "From: analytics@wgs-service.cz\r\n");
    }
}
?>
