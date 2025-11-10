<?php
/**
 * DEBUG endpoint - ukáže co je v SESSION a co hledá load.php
 */

require_once __DIR__ . '/init.php';

// Pouze pro přihlášené
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    die('Musíš být přihlášený');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG SESSION & LOAD.PHP ===\n\n";

echo "[SESSION DATA]\n";
echo "  user_id: " . var_export($_SESSION['user_id'] ?? null, true) . "\n";
echo "  user_email: " . var_export($_SESSION['user_email'] ?? null, true) . "\n";
echo "  user_name: " . var_export($_SESSION['user_name'] ?? null, true) . "\n";
echo "  is_admin: " . var_export($_SESSION['is_admin'] ?? false, true) . "\n";
echo "  role: " . var_export($_SESSION['role'] ?? null, true) . "\n";

echo "\n";

try {
    $pdo = getDbConnection();

    // Simuluj load.php logiku
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $userId = $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;

    echo "[LOAD.PHP FILTRY]\n";
    echo "  Je admin? " . ($isAdmin ? "ANO (vidí vše)" : "NE (filtruje)") . "\n";
    echo "  User ID pro filtr: " . var_export($userId, true) . "\n";
    echo "  Email pro filtr: " . var_export($userEmail, true) . "\n";

    echo "\n";

    // Všechny reklamace
    $stmt = $pdo->query("
        SELECT
            id,
            reklamace_id,
            jmeno,
            email,
            LOWER(TRIM(email)) as email_normalized,
            created_by,
            created_at
        FROM wgs_reklamace
        ORDER BY created_at DESC
    ");
    $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[VŠECHNY REKLAMACE V DB] Celkem: " . count($allClaims) . "\n";
    echo str_repeat("-", 100) . "\n";

    foreach ($allClaims as $claim) {
        echo sprintf("ID %d | %s | %s | email='%s' (normalized='%s') | created_by=%s\n",
            $claim['id'],
            $claim['reklamace_id'] ?? 'NULL',
            $claim['jmeno'] ?? 'NULL',
            $claim['email'] ?? 'NULL',
            $claim['email_normalized'] ?? 'NULL',
            $claim['created_by'] ?? 'NULL'
        );
    }

    echo "\n";

    if (!$isAdmin && $userEmail) {
        echo "[FILTROVÁNÍ PRO UŽIVATELE] email={$userEmail}\n";
        echo "  Normalized user email: '" . strtolower(trim($userEmail)) . "'\n\n";

        // Test email match
        $stmt = $pdo->prepare("
            SELECT
                id,
                reklamace_id,
                jmeno,
                email,
                LOWER(TRIM(email)) as email_normalized,
                created_by,
                (LOWER(TRIM(email)) = LOWER(TRIM(:user_email))) as email_match
            FROM wgs_reklamace
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_email' => $userEmail]);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "  Testování email match:\n";
        foreach ($claims as $claim) {
            $match = $claim['email_match'] ? '[OK] MATCH' : '[X] NO MATCH';
            echo sprintf("    ID %d: email='%s' normalized='%s' -> %s\n",
                $claim['id'],
                $claim['email'] ?? 'NULL',
                $claim['email_normalized'] ?? 'NULL',
                $match
            );
        }

        echo "\n";

        // Co by load.php vrátil
        $whereParts = [];
        $params = [];

        if ($userId !== null) {
            $whereParts[] = 'r.created_by = :created_by';
            $params[':created_by'] = $userId;
        }

        if ($userEmail) {
            $whereParts[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:user_email))';
            $params[':user_email'] = $userEmail;
        }

        if (!empty($whereParts)) {
            $whereClause = 'WHERE (' . implode(' OR ', $whereParts) . ')';

            echo "[SQL DOTAZ] load.php\n";
            echo "  WHERE: " . $whereClause . "\n";
            echo "  PARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n\n";

            $sql = "
                SELECT id, reklamace_id, jmeno, email, created_by
                FROM wgs_reklamace r
                $whereClause
                ORDER BY created_at DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $visible = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "[VIDITELNÉ REKLAMACE] Celkem: " . count($visible) . "\n";
            if (empty($visible)) {
                echo "  [VAROVÁNÍ] ŽÁDNÉ!\n";
            } else {
                foreach ($visible as $claim) {
                    echo sprintf("  ID %d: %s (%s) - created_by=%s\n",
                        $claim['id'],
                        $claim['jmeno'],
                        $claim['email'] ?? 'NULL',
                        $claim['created_by'] ?? 'NULL'
                    );
                }
            }
        }
    }

} catch (Exception $e) {
    echo "\n[CHYBA] " . $e->getMessage() . "\n";
}

echo "\n=== KONEC DEBUGU ===\n";
