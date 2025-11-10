<?php
/**
 * Diagnostic - kontrola reklamací v databázi
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die("PŘÍSTUP ODEPŘEN\nMusíte být přihlášeni pro zobrazení diagnostiky.\n");
}

echo "=== DIAGNOSTIKA REKLAMACÍ ===\n\n";

try {
    $pdo = getDbConnection();
    echo "[OK] Připojení k databázi úspěšné\n\n";

    // 1. Celkový počet reklamací
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "[DATA] Celkový počet reklamací: {$total}\n\n";

    // 2. Seznam všech reklamací s důležitými poli
    $stmt = $pdo->query("
        SELECT
            id,
            reklamace_id,
            cislo,
            jmeno,
            email,
            created_by,
            created_at,
            stav
        FROM wgs_reklamace
        ORDER BY created_at DESC
    ");
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[SEZNAM] Seznam reklamací:\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-5s %-18s %-12s %-25s %-30s %-12s %-20s %-12s\n",
        "ID", "REKL_ID", "CISLO", "JMENO", "EMAIL", "CREATED_BY", "CREATED_AT", "STAV");
    echo str_repeat("-", 120) . "\n";

    foreach ($reklamace as $r) {
        printf("%-5s %-18s %-12s %-25s %-30s %-12s %-20s %-12s\n",
            $r['id'] ?? '-',
            $r['reklamace_id'] ?? '-',
            $r['cislo'] ?? '-',
            substr($r['jmeno'] ?? '-', 0, 25),
            substr($r['email'] ?? '-', 0, 30),
            $r['created_by'] ?? 'NULL',
            $r['created_at'] ?? '-',
            $r['stav'] ?? '-'
        );
    }

    echo "\n\n";

    // 3. Informace o session
    echo "[SESSION] SESSION informace:\n";
    echo "  - user_id: " . ($_SESSION['user_id'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "  - user_email: " . ($_SESSION['user_email'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "  - is_admin: " . (($_SESSION['is_admin'] ?? false) ? 'ANO' : 'NE') . "\n";

    echo "\n";

    // 4. Reklamace, které by se ZOBRAZILY pro aktuálního uživatele
    if (isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        if ($isAdmin) {
            echo "[ADMIN] Jsi admin - vidíš VŠECHNY reklamace ({$total})\n";
        } else {
            $userId = $_SESSION['user_id'] ?? null;
            $userEmail = $_SESSION['user_email'] ?? null;

            $whereParts = [];
            $params = [];

            if ($userId !== null) {
                $whereParts[] = 'created_by = :created_by';
                $params[':created_by'] = $userId;
            }

            if ($userEmail) {
                $whereParts[] = 'email = :user_email';
                $params[':user_email'] = $userEmail;
            }

            if (!empty($whereParts)) {
                $whereClause = 'WHERE (' . implode(' OR ', $whereParts) . ')';
                $stmt = $pdo->prepare("SELECT COUNT(*) as visible FROM wgs_reklamace $whereClause");
                $stmt->execute($params);
                $visible = $stmt->fetch(PDO::FETCH_ASSOC)['visible'];

                echo "[USER] Jako přihlášený uživatel vidíš: {$visible} reklamací\n";
                echo "   (Filtr: created_by={$userId} OR email={$userEmail})\n\n";

                // Detail viditelných reklamací
                $stmt = $pdo->prepare("
                    SELECT id, reklamace_id, jmeno, email, created_by
                    FROM wgs_reklamace
                    $whereClause
                    ORDER BY created_at DESC
                ");
                $stmt->execute($params);
                $visibleRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($visibleRecords)) {
                    echo "[DETAIL] Detail viditelných reklamací:\n";
                    foreach ($visibleRecords as $r) {
                        echo "  - ID {$r['id']}: {$r['jmeno']} ({$r['email']}) - created_by: " . ($r['created_by'] ?? 'NULL') . "\n";
                    }
                }
            } else {
                echo "[VAROVÁNÍ] NEMÁŠ nastavený user_id ani email - neuvidíš ŽÁDNÉ reklamace!\n";
            }
        }
    } else {
        echo "[VAROVÁNÍ] NEJSI PŘIHLÁŠENÝ - přihlaš se pro zobrazení reklamací\n";
    }

    echo "\n";

    // 5. Reklamace bez created_by (problematické)
    $stmt = $pdo->query("SELECT COUNT(*) as orphaned FROM wgs_reklamace WHERE created_by IS NULL");
    $orphaned = $stmt->fetch(PDO::FETCH_ASSOC)['orphaned'];

    if ($orphaned > 0) {
        echo "[PROBLÉM] {$orphaned} reklamací má created_by = NULL\n";
        echo "   Tyto reklamace se nezobrazí uživatelům, kteří je vytvořili!\n";
        echo "   ŘEŠENÍ: Buď přiřadit created_by, nebo přihlásit se emailem použitým v reklamaci\n\n";

        $stmt = $pdo->query("
            SELECT id, reklamace_id, jmeno, email, created_at
            FROM wgs_reklamace
            WHERE created_by IS NULL
            ORDER BY created_at DESC
        ");
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "[DETAIL] Reklamace bez created_by:\n";
        foreach ($orphanedRecords as $r) {
            echo "  - ID {$r['id']}: {$r['jmeno']} ({$r['email']}) - {$r['created_at']}\n";
        }
    } else {
        echo "[OK] Všechny reklamace mají přiřazené created_by\n";
    }

    echo "\n[OK] Diagnostika dokončena!\n";

} catch (Exception $e) {
    echo "[CHYBA] " . $e->getMessage() . "\n";
    exit(1);
}
