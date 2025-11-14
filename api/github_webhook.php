<?php
/**
 * GitHub Webhook Handler
 * Receives webhooks from GitHub and processes them
 */

require_once __DIR__ . '/../init.php';

// Security: Verify GitHub signature
function verifyGitHubSignature($payload, $signature) {
    if (empty($signature)) {
        return false;
    }

    // Get secret from database or config
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT config_value FROM wgs_system_config WHERE config_key = 'github_webhook_secret'");
    $secret = $stmt->fetchColumn();

    if (empty($secret)) {
        // BEZPEČNOST: Pokud není secret, ODMÍTNOUT webhook (ne akceptovat!)
        error_log('GitHub webhook: No secret configured, rejecting webhook for security');
        return false;
    }

    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($hash, $signature);
}

// Log webhook to file for debugging
function logWebhook($data) {
    $logFile = __DIR__ . '/../logs/github_webhooks.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

try {
    // Get raw POST data
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';

    // Verify signature
    if (!verifyGitHubSignature($payload, $signature)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        logWebhook(['status' => 'rejected', 'reason' => 'invalid_signature', 'event' => $event]);
        exit;
    }

    // Parse JSON payload
    $data = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        logWebhook(['status' => 'rejected', 'reason' => 'invalid_json']);
        exit;
    }

    $pdo = getDbConnection();

    // Extract common data
    $repository = $data['repository']['full_name'] ?? 'unknown';
    $sender = $data['sender']['login'] ?? 'unknown';
    $branch = null;
    $commitMessage = null;
    $actionType = null;
    $actionTitle = null;
    $actionDescription = null;
    $priority = 'low';
    $createPendingAction = false;

    // Process different event types
    switch ($event) {
        case 'push':
            $branch = basename($data['ref'] ?? '');
            $commitMessage = $data['head_commit']['message'] ?? null;

            // If push to main/master, create pending action to deploy
            if (in_array($branch, ['main', 'master'])) {
                $createPendingAction = true;
                $actionType = 'deploy';
                $actionTitle = "Deploy do produkce ($branch)";
                $actionDescription = "Nový commit do $branch: " . substr($commitMessage, 0, 100);
                $priority = 'high';
            }
            break;

        case 'pull_request':
            $action = $data['action'] ?? '';
            $prNumber = $data['pull_request']['number'] ?? 0;
            $prTitle = $data['pull_request']['title'] ?? '';
            $branch = $data['pull_request']['head']['ref'] ?? '';

            if ($action === 'opened') {
                $createPendingAction = true;
                $actionType = 'review_pr';
                $actionTitle = "Review Pull Request #$prNumber";
                $actionDescription = $prTitle;
                $priority = 'medium';
            } elseif ($action === 'closed' && ($data['pull_request']['merged'] ?? false)) {
                $createPendingAction = true;
                $actionType = 'pr_merged';
                $actionTitle = "PR #$prNumber byl mergnut";
                $actionDescription = "Zkontrolujte, zda je potřeba deploy";
                $priority = 'medium';
            }
            break;

        case 'issues':
            $action = $data['action'] ?? '';
            $issueNumber = $data['issue']['number'] ?? 0;
            $issueTitle = $data['issue']['title'] ?? '';

            if ($action === 'opened') {
                $createPendingAction = true;
                $actionType = 'review_issue';
                $actionTitle = "Nový Issue #$issueNumber";
                $actionDescription = $issueTitle;
                $priority = 'low';

                // If issue has label 'bug', increase priority
                $labels = array_column($data['issue']['labels'] ?? [], 'name');
                if (in_array('bug', $labels)) {
                    $priority = 'high';
                } elseif (in_array('enhancement', $labels)) {
                    $priority = 'medium';
                }
            }
            break;

        case 'release':
            $action = $data['action'] ?? '';
            $releaseName = $data['release']['name'] ?? $data['release']['tag_name'] ?? '';

            if ($action === 'published') {
                $createPendingAction = true;
                $actionType = 'deploy_release';
                $actionTitle = "Deploy nové verze: $releaseName";
                $actionDescription = "Nasadit release do produkce";
                $priority = 'critical';
            }
            break;

        case 'workflow_run':
            $conclusion = $data['workflow_run']['conclusion'] ?? '';
            $workflowName = $data['workflow_run']['name'] ?? 'Workflow';

            if ($conclusion === 'failure') {
                $createPendingAction = true;
                $actionType = 'workflow_failed';
                $actionTitle = "Workflow selhal: $workflowName";
                $actionDescription = "Zkontrolujte logy a opravte chyby";
                $priority = 'high';
            }
            break;
    }

    // CRITICAL FIX: Zahájit transakci pro atomicitu webhook + pending action
    $pdo->beginTransaction();

    try {
        // Store webhook in database
        $stmt = $pdo->prepare("
            INSERT INTO wgs_github_webhooks
            (event_type, repository, author, branch, commit_message, payload, processed)
            VALUES (:event_type, :repository, :author, :branch, :commit_message, :payload, :processed)
        ");

        $stmt->execute([
            'event_type' => $event,
            'repository' => $repository,
            'author' => $sender,
            'branch' => $branch,
            'commit_message' => $commitMessage,
            'payload' => $payload,
            'processed' => $createPendingAction ? 1 : 0
        ]);

        $webhookId = $pdo->lastInsertId();

        // Create pending action if needed
        if ($createPendingAction && $actionTitle) {
            $stmt = $pdo->prepare("
                INSERT INTO wgs_pending_actions
                (action_type, action_title, action_description, priority, source_type, source_id, status)
                VALUES (:action_type, :action_title, :action_description, :priority, 'github_webhook', :source_id, 'pending')
            ");

            $stmt->execute([
                'action_type' => $actionType,
                'action_title' => $actionTitle,
                'action_description' => $actionDescription,
                'priority' => $priority,
                'source_id' => $webhookId
            ]);

            $actionId = $pdo->lastInsertId();

            logWebhook([
                'status' => 'processed',
                'event' => $event,
                'repository' => $repository,
                'action_created' => $actionId,
                'priority' => $priority
            ]);
        } else {
            logWebhook([
                'status' => 'stored',
                'event' => $event,
                'repository' => $repository,
                'no_action_needed' => true
            ]);
        }

        // CRITICAL FIX: COMMIT transakce - obě operace úspěšné
        $pdo->commit();

    } catch (PDOException $dbError) {
        // CRITICAL FIX: ROLLBACK transakce při chybě
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $dbError; // Re-throw to outer catch block
    }

    // Send success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'event' => $event,
        'webhook_id' => $webhookId,
        'action_created' => $createPendingAction
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    logWebhook(['status' => 'error', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    logWebhook(['status' => 'error', 'error' => $e->getMessage()]);
}
