<?php
/**
 * Admin API - Data Listing Module
 * Zpracování list_ akcí pro keys, users, reklamace
 * Vytvořeno: 2025-11-17 - Autonomní Refactoring Engine
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
    case 'list_keys':
        try {
            $stmt = $pdo->query("
                SELECT * FROM wgs_registration_keys
                ORDER BY created_at DESC
            ");

            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'keys' => $keys
            ]);
        } catch (PDOException $e) {
            error_log('[Admin API] list_keys error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'Chyba při načítání klíčů',
                'keys' => []
            ]);
        }
        break;

    case 'create_key':
        $keyType = $data['key_type'] ?? 'technik';
        $maxUsage = $data['max_usage'] ?? 1;

        try {
            // Generovat náhodný klíč
            $keyCode = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("
                INSERT INTO wgs_registration_keys
                (key_code, key_type, max_usage, usage_count, is_active, created_at)
                VALUES (:key_code, :key_type, :max_usage, 0, 1, NOW())
            ");

            $stmt->execute([
                'key_code' => $keyCode,
                'key_type' => $keyType,
                'max_usage' => $maxUsage
            ]);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Klíč vytvořen',
                'key_code' => $keyCode
            ]);
        } catch (PDOException $e) {
            error_log('[Admin API] create_key error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'Chyba při vytváření klíče'
            ]);
        }
        break;

    case 'delete_key':
        $keyCode = $data['key_code'] ?? null;

        if (!$keyCode) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'Chybí key_code'
            ]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM wgs_registration_keys WHERE key_code = :key_code");
            $stmt->execute(['key_code' => $keyCode]);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Klíč smazán'
            ]);
        } catch (PDOException $e) {
            error_log('[Admin API] delete_key error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'Chyba při mazání klíče'
            ]);
        }
        break;

    case 'list_users':
        try {
            $stmt = $pdo->query("
                SELECT
                    user_id AS id,
                    full_name AS name,
                    email,
                    role,
                    is_active,
                    created_at
                FROM wgs_users
                ORDER BY created_at DESC
            ");

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'data' => $users
            ]);
        } catch (PDOException $e) {
            error_log('[Admin API] list_users error: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'data' => [],
                'message' => 'Tabulka uživatelů není dostupná'
            ]);
        }
        break;

    case 'list_reklamace':
        try {
            $stmt = $pdo->query("
                SELECT
                    reklamace_id,
                    jmeno,
                    telefon,
                    email,
                    adresa,
                    psc,
                    mesto,
                    stav,
                    typ,
                    datum_vytvoreni,
                    termin,
                    cas_navstevy
                FROM wgs_reklamace
                ORDER BY datum_vytvoreni DESC
                LIMIT 1000
            ");

            $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapování stavů z databáze (wait/open/done) na frontend (ČEKÁ/DOMLUVENÁ/HOTOVO)
            foreach ($reklamace as &$r) {
                switch ($r['stav']) {
                    case 'wait':
                        $r['stav'] = 'ČEKÁ';
                        break;
                    case 'open':
                        $r['stav'] = 'DOMLUVENÁ';
                        break;
                    case 'done':
                        $r['stav'] = 'HOTOVO';
                        break;
                }
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'reklamace' => $reklamace
            ]);
        } catch (PDOException $e) {
            error_log('[Admin API] list_reklamace error: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'reklamace' => []
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => "Neznámá data akce: {$action}"
        ]);
}
