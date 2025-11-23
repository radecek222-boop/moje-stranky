<?php
/**
 * Analytics Reports API
 *
 * API pro správu AI-generated reportů a schedules.
 *
 * Actions:
 * - list: Seznam vygenerovaných reportů
 * - generate: Manuální generování reportu
 * - detail: Detail konkrétního reportu
 * - download: Stažení HTML reportu
 * - schedule_list: Seznam schedules
 * - schedule_create: Vytvoření schedule
 * - schedule_update: Update schedule
 * - schedule_delete: Smazání schedule
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #12 - AI Reports Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/AIReportGenerator.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ========================================
    // AUTHENTICATION CHECK (admin only)
    // ========================================
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        sendJsonError('Přístup odepřen - pouze pro admins', 403);
    }

    // ========================================
    // CSRF VALIDACE
    // ========================================
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    $pdo = getDbConnection();

    // ========================================
    // PARAMETRY
    // ========================================
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // ========================================
    // ACTION ROUTING
    // ========================================
    switch ($action) {
        // ========================================
        // LIST - Seznam reportů
        // ========================================
        case 'list':
            $stmt = $pdo->prepare("
                SELECT
                    report_id,
                    report_type,
                    report_period_start,
                    report_period_end,
                    status,
                    generated_at,
                    generated_by,
                    file_size
                FROM wgs_analytics_reports
                ORDER BY generated_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->execute(['limit' => $limit, 'offset' => $offset]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total count
            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_analytics_reports");
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            sendJsonSuccess('Reporty načteny', [
                'reports' => $reports,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        // ========================================
        // GENERATE - Generování nového reportu
        // ========================================
        case 'generate':
            $reportType = $_POST['report_type'] ?? null;
            $dateFrom = $_POST['date_from'] ?? null;
            $dateTo = $_POST['date_to'] ?? null;

            if (!$reportType || !$dateFrom || !$dateTo) {
                sendJsonError('Chybí povinné parametry: report_type, date_from, date_to');
            }

            // Validace report_type
            if (!in_array($reportType, ['daily', 'weekly', 'monthly'])) {
                sendJsonError('Neplatný report_type (povolené: daily, weekly, monthly)');
            }

            // Validace date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                sendJsonError('Neplatný formát data (použijte YYYY-MM-DD)');
            }

            // Generovat report
            $generator = new AIReportGenerator($pdo);
            $reportData = $generator->generujReport($reportType, $dateFrom, $dateTo);

            // Uložit do DB
            $reportId = $generator->ulozReport($reportData);

            sendJsonSuccess('Report úspěšně vygenerován', [
                'report_id' => $reportId,
                'report_type' => $reportType,
                'period' => $reportData['period']
            ]);
            break;

        // ========================================
        // DETAIL - Detail reportu
        // ========================================
        case 'detail':
            $reportId = $_GET['report_id'] ?? null;

            if (!$reportId) {
                sendJsonError('Chybí report_id');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_analytics_reports WHERE report_id = :id");
            $stmt->execute(['id' => $reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                sendJsonError('Report nenalezen', 404);
            }

            // Dekódovat JSON data
            $report['report_data'] = json_decode($report['report_data'], true);
            $report['insights'] = json_decode($report['insights'], true);
            $report['recommendations'] = json_decode($report['recommendations'], true);
            $report['anomalies'] = json_decode($report['anomalies'], true);

            sendJsonSuccess('Report načten', ['report' => $report]);
            break;

        // ========================================
        // DOWNLOAD - Stažení HTML reportu
        // ========================================
        case 'download':
            $reportId = $_GET['report_id'] ?? null;

            if (!$reportId) {
                sendJsonError('Chybí report_id');
            }

            $generator = new AIReportGenerator($pdo);
            $html = $generator->exportHTML($reportId);

            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="report_' . $reportId . '.html"');
            echo $html;
            exit;

        // ========================================
        // SCHEDULE_LIST - Seznam schedules
        // ========================================
        case 'schedule_list':
            $stmt = $pdo->query("
                SELECT * FROM wgs_analytics_report_schedules
                ORDER BY schedule_id DESC
            ");
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Dekódovat JSON email_recipients
            foreach ($schedules as &$schedule) {
                $schedule['email_recipients'] = json_decode($schedule['email_recipients'], true);
            }

            sendJsonSuccess('Schedules načteny', ['schedules' => $schedules]);
            break;

        // ========================================
        // SCHEDULE_CREATE - Vytvoření schedule
        // ========================================
        case 'schedule_create':
            $scheduleName = $_POST['schedule_name'] ?? null;
            $reportType = $_POST['report_type'] ?? null;
            $frequency = $_POST['frequency'] ?? null;
            $deliveryMethod = $_POST['delivery_method'] ?? 'download';
            $emailRecipients = $_POST['email_recipients'] ?? null;
            $dayOfWeek = $_POST['day_of_week'] ?? null;
            $dayOfMonth = $_POST['day_of_month'] ?? null;
            $timeOfDay = $_POST['time_of_day'] ?? '06:00:00';

            if (!$scheduleName || !$reportType || !$frequency) {
                sendJsonError('Chybí povinné parametry: schedule_name, report_type, frequency');
            }

            // Vypočítat next_run_at
            $nextRunAt = $this->calculateNextRun($frequency, $dayOfWeek, $dayOfMonth, $timeOfDay);

            $stmt = $pdo->prepare("
                INSERT INTO wgs_analytics_report_schedules (
                    schedule_name,
                    report_type,
                    frequency,
                    day_of_week,
                    day_of_month,
                    time_of_day,
                    delivery_method,
                    email_recipients,
                    next_run_at
                ) VALUES (
                    :schedule_name,
                    :report_type,
                    :frequency,
                    :day_of_week,
                    :day_of_month,
                    :time_of_day,
                    :delivery_method,
                    :email_recipients,
                    :next_run_at
                )
            ");

            $stmt->execute([
                'schedule_name' => $scheduleName,
                'report_type' => $reportType,
                'frequency' => $frequency,
                'day_of_week' => $dayOfWeek,
                'day_of_month' => $dayOfMonth,
                'time_of_day' => $timeOfDay,
                'delivery_method' => $deliveryMethod,
                'email_recipients' => $emailRecipients ? json_encode(explode(',', $emailRecipients)) : null,
                'next_run_at' => $nextRunAt
            ]);

            $scheduleId = $pdo->lastInsertId();

            sendJsonSuccess('Schedule vytvořen', ['schedule_id' => $scheduleId]);
            break;

        // ========================================
        // SCHEDULE_UPDATE - Update schedule
        // ========================================
        case 'schedule_update':
            $scheduleId = $_POST['schedule_id'] ?? null;
            $isActive = $_POST['is_active'] ?? null;

            if (!$scheduleId) {
                sendJsonError('Chybí schedule_id');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_analytics_report_schedules
                SET is_active = :is_active
                WHERE schedule_id = :id
            ");

            $stmt->execute([
                'is_active' => $isActive ? 1 : 0,
                'id' => $scheduleId
            ]);

            sendJsonSuccess('Schedule aktualizován');
            break;

        // ========================================
        // SCHEDULE_DELETE - Smazání schedule
        // ========================================
        case 'schedule_delete':
            $scheduleId = $_POST['schedule_id'] ?? null;

            if (!$scheduleId) {
                sendJsonError('Chybí schedule_id');
            }

            $stmt = $pdo->prepare("DELETE FROM wgs_analytics_report_schedules WHERE schedule_id = :id");
            $stmt->execute(['id' => $scheduleId]);

            sendJsonSuccess('Schedule smazán');
            break;

        // ========================================
        // DEFAULT - Neplatná akce
        // ========================================
        default:
            sendJsonError('Neplatná akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("API Reports - Database error: " . $e->getMessage());
    sendJsonError('Chyba databáze při zpracování požadavku');
} catch (Exception $e) {
    error_log("API Reports - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}

/**
 * Vypočítá next_run_at timestamp
 */
function calculateNextRun(string $frequency, ?int $dayOfWeek, ?int $dayOfMonth, string $timeOfDay): string
{
    $now = new DateTime();

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
            $nextRun->setDate($nextRun->format('Y'), $nextRun->format('m'), min($targetDay, $nextRun->format('t')));
            break;

        default:
            $nextRun = new DateTime('tomorrow ' . $timeOfDay);
    }

    return $nextRun->format('Y-m-d H:i:s');
}

function getDayName(int $dayOfWeek): string
{
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayOfWeek] ?? 'Monday';
}
?>
