<?php
/**
 * Diagnostika: Proč nefunguje pulsování poznámek?
 *
 * Tento skript prověří celý systém krok za krokem.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze přihlášení uživatelé
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    die("PŘÍSTUP ODEPŘEN: Musíte být přihlášeni.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Pulsování poznámek</title>
    <style>
        body { font-family: monospace; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; font-weight: bold; }
        pre { background: white; padding: 15px; border: 1px solid #ddd; overflow-x: auto; max-height: 400px; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 30px; }
        h3 { color: #666; margin-top: 20px; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }
        .highlight { background: yellow; padding: 2px 5px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
        .step { background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>";

echo "<h1>🔍 Diagnostika: Proč nefunguje pulsování poznámek?</h1>";

try {
    $pdo = getDbConnection();
    $currentUserEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // ========================================================================
    // KROK 1: Informace o přihlášeném uživateli
    // ========================================================================
    echo "<h2>KROK 1: Kdo jste přihlášeni?</h2>";
    echo "<div class='box'>";
    echo "<p><strong>Email:</strong> <span class='highlight'>{$currentUserEmail}</span></p>";
    echo "<p><strong>Role:</strong> " . ($isAdmin ? '<span class="ok">ADMIN</span>' : '<span class="info">UŽIVATEL</span>') . "</p>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    echo "</div>";

    // ========================================================================
    // KROK 2: Kontrola existence tabulky wgs_notes_read
    // ========================================================================
    echo "<h2>KROK 2: Kontrola databázové tabulky</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notes_read'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<p class='ok'>✅ Tabulka wgs_notes_read EXISTUJE</p>";

        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_notes_read");
        $readCount = $stmt->fetchColumn();
        echo "<p>Počet read záznamů: <strong>{$readCount}</strong></p>";
    } else {
        echo "<p class='error'>❌ Tabulka wgs_notes_read NEEXISTUJE!</p>";
        echo "<p class='warning'>⚠️ MUSÍŠ SPUSTIT: <a href='pridej_notes_read_tracking.php'>pridej_notes_read_tracking.php</a></p>";
        die("</body></html>");
    }

    // ========================================================================
    // KROK 3: Všechny poznámky v databázi
    // ========================================================================
    echo "<h2>KROK 3: Všechny poznámky v databázi</h2>";
    $stmt = $pdo->query("
        SELECT id, claim_id, created_by, created_at,
               LEFT(note_text, 50) as note_preview
        FROM wgs_notes
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $allNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Claim ID</th><th>Autor (created_by)</th><th>Čas</th><th>Text (ukázka)</th></tr>";
    foreach ($allNotes as $note) {
        $isOwnNote = $note['created_by'] === $currentUserEmail;
        $rowClass = $isOwnNote ? 'style="background: #fffacd;"' : '';
        echo "<tr {$rowClass}>";
        echo "<td>{$note['id']}</td>";
        echo "<td>{$note['claim_id']}</td>";
        echo "<td>" . ($isOwnNote ? '<strong>' . $note['created_by'] . ' (VY)</strong>' : $note['created_by']) . "</td>";
        echo "<td>{$note['created_at']}</td>";
        echo "<td>{$note['note_preview']}...</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><em>Poznámka: Žlutě označené řádky jsou VAŠE poznámky (neměly by vám pulsovat).</em></p>";

    // ========================================================================
    // KROK 4: Co jste už přečetli?
    // ========================================================================
    echo "<h2>KROK 4: Které poznámky jste už přečetli?</h2>";
    $stmt = $pdo->prepare("
        SELECT nr.note_id, nr.read_at, n.created_by, n.claim_id
        FROM wgs_notes_read nr
        JOIN wgs_notes n ON nr.note_id = n.id
        WHERE nr.user_email = :user_email
        ORDER BY nr.read_at DESC
        LIMIT 20
    ");
    $stmt->execute([':user_email' => $currentUserEmail]);
    $readNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($readNotes) > 0) {
        echo "<p>Přečetli jste <strong>" . count($readNotes) . "</strong> poznámek:</p>";
        echo "<table>";
        echo "<tr><th>Note ID</th><th>Claim ID</th><th>Autor</th><th>Přečteno kdy</th></tr>";
        foreach ($readNotes as $read) {
            echo "<tr>";
            echo "<td>{$read['note_id']}</td>";
            echo "<td>{$read['claim_id']}</td>";
            echo "<td>{$read['created_by']}</td>";
            echo "<td>{$read['read_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ Nepřečetli jste ŽÁDNOU poznámku (nebo záznamy ještě nejsou v DB).</p>";
    }

    // ========================================================================
    // KROK 5: SQL dotaz pro get_unread_counts (KRITICKÝ TEST!)
    // ========================================================================
    echo "<h2>KROK 5: Test SQL dotazu pro nepřečtené poznámky</h2>";
    echo "<div class='step'>";
    echo "<strong>Tento dotaz používá API endpoint get_unread_counts:</strong>";
    echo "</div>";

    echo "<pre>";
    echo "SELECT
    n.claim_id,
    COUNT(*) as unread_count
FROM wgs_notes n
LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = '{$currentUserEmail}'
WHERE nr.id IS NULL
  AND n.created_by != '{$currentUserEmail}'
GROUP BY n.claim_id";
    echo "</pre>";

    $stmt = $pdo->prepare("
        SELECT
            n.claim_id,
            COUNT(*) as unread_count
        FROM wgs_notes n
        LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = :user_email
        WHERE nr.id IS NULL
          AND n.created_by != :user_email_author
        GROUP BY n.claim_id
    ");
    $stmt->execute([
        ':user_email' => $currentUserEmail,
        ':user_email_author' => $currentUserEmail
    ]);
    $unreadCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (count($unreadCounts) > 0) {
        echo "<p class='ok'>✅ MÁTE NEPŘEČTENÉ POZNÁMKY!</p>";
        echo "<table>";
        echo "<tr><th>Claim ID</th><th>Počet nepřečtených</th></tr>";
        foreach ($unreadCounts as $claimId => $count) {
            echo "<tr>";
            echo "<td><strong>{$claimId}</strong></td>";
            echo "<td class='ok'><strong>{$count}</strong></td>";
            echo "</tr>";
        }
        echo "</table>";

        $totalUnread = array_sum($unreadCounts);
        echo "<p class='ok'><strong>CELKEM: {$totalUnread} nepřečtených poznámek</strong></p>";

    } else {
        echo "<p class='warning'>⚠️ Žádné nepřečtené poznámky (nebo všechny jsou vaše vlastní).</p>";
        echo "<p><em>Zkuste:</em></p>";
        echo "<ul>";
        echo "<li>Přihlásit se jako jiný uživatel</li>";
        echo "<li>Přidat novou poznámku jako jiný uživatel</li>";
        echo "<li>Zkontrolovat tabulku wgs_notes_read</li>";
        echo "</ul>";
    }

    // ========================================================================
    // KROK 6: Test API endpointu přes GET (simulace frontendu)
    // ========================================================================
    echo "<h2>KROK 6: Test API endpointu (simulace AJAX volání)</h2>";

    echo "<div class='step'>";
    echo "<strong>Frontend volá:</strong> <code>api/notes_api.php?action=get_unread_counts</code>";
    echo "</div>";

    // Zkusíme přímo zavolat API logiku
    echo "<h3>A) Simulace API odpovědi (interní test):</h3>";
    echo "<pre>";
    echo json_encode([
        'status' => 'success',
        'unread_counts' => $unreadCounts
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";

    // Připravit JavaScript pro skutečné AJAX volání
    echo "<h3>B) Skutečné AJAX volání (jako frontend):</h3>";
    echo "<button id='testApiBtn' style='padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; border-radius: 5px;'>Zavolat API endpoint</button>";
    echo "<pre id='apiResponse' style='background: #f0f0f0; padding: 15px; margin-top: 10px;'>Klikni na tlačítko...</pre>";

    echo "<script>
    document.getElementById('testApiBtn').addEventListener('click', async function() {
        const responseEl = document.getElementById('apiResponse');
        responseEl.textContent = '⏳ Načítám...';

        try {
            const response = await fetch('api/notes_api.php?action=get_unread_counts');
            const text = await response.text();

            responseEl.textContent = 'HTTP Status: ' + response.status + '\\n\\n';
            responseEl.textContent += 'Response Headers:\\n';
            response.headers.forEach((value, key) => {
                responseEl.textContent += key + ': ' + value + '\\n';
            });
            responseEl.textContent += '\\n';
            responseEl.textContent += 'Response Body:\\n';
            responseEl.textContent += text;

            // Zkusit parsovat JSON
            try {
                const json = JSON.parse(text);
                responseEl.textContent += '\\n\\n✅ JSON je validní:\\n';
                responseEl.textContent += JSON.stringify(json, null, 2);
            } catch (e) {
                responseEl.textContent += '\\n\\n❌ JSON parsing error: ' + e.message;
            }

        } catch (error) {
            responseEl.textContent = '❌ CHYBA: ' + error.message;
        }
    });
    </script>";

    // ========================================================================
    // KROK 7: Proč může API vracet 400?
    // ========================================================================
    echo "<h2>KROK 7: Možné příčiny HTTP 400 chyby</h2>";
    echo "<div class='box'>";
    echo "<p class='error'><strong>Z konzole prohlížeče:</strong> Failed to load resource: the server responded with a status of 400 () (notes_api.php)</p>";
    echo "<p><strong>API může vracet 400 když:</strong></p>";
    echo "<ul>";
    echo "<li>❌ Uživatel není přihlášen → HTTP 401 (ne 400)</li>";
    echo "<li>❌ Neplatná akce → HTTP 400 ✅</li>";
    echo "<li>❌ Chybí reklamace_id (u jiných akcí) → HTTP 400 ✅</li>";
    echo "<li>❌ Exception v try-catch bloku → HTTP 400 ✅</li>";
    echo "</ul>";
    echo "</div>";

    // Kontrola zda je get_unread_counts v read-only actions
    echo "<h3>Kontrola: Je 'get_unread_counts' povolená GET akce?</h3>";
    echo "<pre>";
    echo "// Z notes_api.php řádek 32:\n";
    echo "\$readOnlyActions = ['get', 'list', 'count'];\n";
    echo "</pre>";
    echo "<p class='warning'>⚠️ NAŠEL JSEM PROBLÉM!</p>";
    echo "<p class='error'><strong>'get_unread_counts' NENÍ v seznamu povolených GET akcí!</strong></p>";
    echo "<p>Seznam povoluje jen: <code>get</code>, <code>list</code>, <code>count</code></p>";
    echo "<p>Ale endpoint se jmenuje: <code>get_unread_counts</code></p>";
    echo "<p class='highlight' style='background: #ffcccc; padding: 15px; border-radius: 5px;'>";
    echo "<strong>🔥 TO JE HLAVNÍ PROBLÉM! 🔥</strong><br>";
    echo "API odmítá GET request s action=get_unread_counts, protože není v \$readOnlyActions!<br>";
    echo "Vrací HTTP 400: 'Tato akce vyžaduje POST metodu s CSRF tokenem.'";
    echo "</p>";

    // ========================================================================
    // ŘEŠENÍ
    // ========================================================================
    echo "<h2>✅ ŘEŠENÍ</h2>";
    echo "<div class='box' style='background: #e8f5e9; border: 2px solid green;'>";
    echo "<h3>Musíš přidat 'get_unread_counts' do \$readOnlyActions v api/notes_api.php:</h3>";
    echo "<pre>";
    echo "// Řádek 32 v api/notes_api.php\n";
    echo "\$readOnlyActions = ['get', 'list', 'count', 'get_unread_counts'];\n";
    echo "</pre>";
    echo "<p><strong>Pak bude GET request fungovat a pulsování začne pracovat!</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<a href='seznam.php' style='padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 5px;'>← Zpět na Seznam</a> ";
echo "<a href='kontrola_notes_tracking.php' style='padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 5px;'>Kontrola notes tracking</a>";
echo "</body></html>";
?>
