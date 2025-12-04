<?php
/**
 * Diagnostika mazání poznámek
 * Otevřete v prohlížeči pro kompletní test
 */

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnost - pouze přihlášení
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    die('Přístup odepřen - přihlaste se');
}

$csrfToken = generateCSRFToken();
$testNoteId = $_GET['note_id'] ?? '999'; // Testovací ID
$apiUrl = '/api/notes_api.php';

// Pokud přijde POST s action=test_delete, simulovat mazání
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_delete') {
    header('Content-Type: application/json');

    // Simulovat co dělá notes_api.php
    $result = [
        'test' => 'Simulace delete requestu',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'NENÍ',
        'post_data' => $_POST,
        'csrf_valid' => validateCSRFToken($_POST['csrf_token'] ?? ''),
        'note_id' => $_POST['note_id'] ?? 'CHYBÍ',
        'session' => [
            'user_id' => $_SESSION['user_id'] ?? 'NENÍ',
            'user_email' => $_SESSION['user_email'] ?? 'NENÍ',
            'is_admin' => $_SESSION['is_admin'] ?? false
        ]
    ];

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika Delete Note</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 900px; margin: 2rem auto; padding: 1rem; background: #f5f5f5; }
        .card { background: white; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 0.5rem; }
        h2 { color: #555; margin-top: 0; }
        pre { background: #1a1a1a; color: #0f0; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; }
        .success { color: #155724; background: #d4edda; padding: 0.5rem 1rem; border-radius: 4px; }
        .error { color: #721c24; background: #f8d7da; padding: 0.5rem 1rem; border-radius: 4px; }
        .warning { color: #856404; background: #fff3cd; padding: 0.5rem 1rem; border-radius: 4px; }
        button { padding: 0.75rem 1.5rem; font-size: 1rem; cursor: pointer; border: none; border-radius: 4px; margin: 0.25rem; }
        .btn-primary { background: #333; color: white; }
        .btn-secondary { background: #666; color: white; }
        #results { margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Diagnostika Delete Note</h1>

    <div class="card">
        <h2>1. Stav Session</h2>
        <table>
            <tr><th>Klíč</th><th>Hodnota</th></tr>
            <tr><td>user_id</td><td><?= htmlspecialchars($_SESSION['user_id'] ?? 'NENÍ') ?></td></tr>
            <tr><td>user_email</td><td><?= htmlspecialchars($_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'NENÍ') ?></td></tr>
            <tr><td>role</td><td><?= htmlspecialchars($_SESSION['role'] ?? 'NENÍ') ?></td></tr>
            <tr><td>is_admin</td><td><?= ($_SESSION['is_admin'] ?? false) ? 'ANO' : 'NE' ?></td></tr>
            <tr><td>CSRF Token</td><td><code><?= htmlspecialchars(substr($csrfToken, 0, 20)) ?>...</code></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>2. Test Poznámek v DB</h2>
        <?php
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT n.id, n.claim_id, n.created_by, LEFT(n.note_text, 50) as text_preview, n.created_at
                                 FROM wgs_notes n ORDER BY n.id DESC LIMIT 5");
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($notes) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Claim</th><th>Autor</th><th>Text</th><th>Akce</th></tr>';
                foreach ($notes as $note) {
                    echo '<tr>';
                    echo '<td>' . $note['id'] . '</td>';
                    echo '<td>' . $note['claim_id'] . '</td>';
                    echo '<td>' . htmlspecialchars($note['created_by'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($note['text_preview']) . '...</td>';
                    echo '<td><button class="btn-secondary" onclick="testDelete(' . $note['id'] . ')">Test Delete</button></td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="warning">Žádné poznámky v databázi</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Chyba DB: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="card">
        <h2>3. Testy HTTP Requestů</h2>
        <p>Klikněte pro test různých typů requestů:</p>

        <button class="btn-primary" onclick="testFormData()">Test FormData (multipart)</button>
        <button class="btn-primary" onclick="testURLSearchParams()">Test URLSearchParams</button>
        <button class="btn-secondary" onclick="testDirectAPI()">Test přímo na notes_api.php</button>

        <div id="results"></div>
    </div>

    <div class="card">
        <h2>4. Ruční Test DELETE</h2>
        <form id="manualForm">
            <p>
                <label>Note ID: <input type="text" id="manualNoteId" value="<?= htmlspecialchars($testNoteId) ?>" style="width: 100px;"></label>
            </p>
            <button type="button" class="btn-primary" onclick="manualDelete()">Provést DELETE</button>
        </form>
    </div>

    <script>
    const CSRF_TOKEN = '<?= $csrfToken ?>';
    const resultsDiv = document.getElementById('results');

    function log(title, data, isError = false) {
        const div = document.createElement('div');
        div.style.cssText = 'margin: 1rem 0; padding: 1rem; border-radius: 4px; background: ' + (isError ? '#f8d7da' : '#d4edda');
        div.innerHTML = '<strong>' + title + '</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        resultsDiv.insertBefore(div, resultsDiv.firstChild);
    }

    // Test 1: FormData (multipart/form-data)
    async function testFormData() {
        console.log('[Test] FormData...');
        const formData = new FormData();
        formData.append('action', 'test_delete');
        formData.append('note_id', '999');
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            log('FormData Test - Status ' + response.status, data);
        } catch (e) {
            log('FormData Test - ERROR', { error: e.message }, true);
        }
    }

    // Test 2: URLSearchParams (application/x-www-form-urlencoded)
    async function testURLSearchParams() {
        console.log('[Test] URLSearchParams...');
        const params = new URLSearchParams();
        params.append('action', 'test_delete');
        params.append('note_id', '999');
        params.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            });
            const data = await response.json();
            log('URLSearchParams Test - Status ' + response.status, data);
        } catch (e) {
            log('URLSearchParams Test - ERROR', { error: e.message }, true);
        }
    }

    // Test 3: Přímo na notes_api.php
    async function testDirectAPI() {
        console.log('[Test] Direct API...');
        const params = new URLSearchParams();
        params.append('action', 'delete');
        params.append('note_id', '999');
        params.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch('/api/notes_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            });
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                data = { raw_response: text };
            }
            log('Direct API Test - Status ' + response.status, {
                status: response.status,
                statusText: response.statusText,
                response: data
            }, response.status >= 400);
        } catch (e) {
            log('Direct API Test - ERROR', { error: e.message }, true);
        }
    }

    // Test konkrétní poznámky
    async function testDelete(noteId) {
        console.log('[Test] Delete note:', noteId);
        const params = new URLSearchParams();
        params.append('action', 'delete');
        params.append('note_id', noteId);
        params.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch('/api/notes_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            });
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                data = { raw: text };
            }
            log('Delete Note #' + noteId + ' - Status ' + response.status, data, response.status >= 400);

            if (response.status === 200 && data.status === 'success') {
                alert('Poznámka #' + noteId + ' úspěšně smazána!');
                location.reload();
            }
        } catch (e) {
            log('Delete Note #' + noteId + ' - ERROR', { error: e.message }, true);
        }
    }

    // Ruční delete
    async function manualDelete() {
        const noteId = document.getElementById('manualNoteId').value;
        if (!noteId) {
            alert('Zadejte ID poznámky');
            return;
        }
        await testDelete(noteId);
    }

    console.log('[Diagnostika] Načtena, CSRF token:', CSRF_TOKEN.substring(0, 10) + '...');
    </script>
</body>
</html>
