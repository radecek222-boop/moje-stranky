<?php
/**
 * Debug nástroj - vyhledání uživatele podle user_id
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen - pouze admin');
}

$user_id = $_GET['user_id'] ?? null;

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug User Lookup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
        }
        .user-card {
            background: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .user-field {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            margin: 10px 0;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .user-field:last-child {
            border-bottom: none;
        }
        .user-field-label {
            font-weight: bold;
            color: #666;
        }
        .user-field-value {
            color: #000;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
            font-size: 1rem;
        }
        button {
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background: #1a300d;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 User Lookup</h1>

    <form method='GET'>
        <input type='text' name='user_id' placeholder='Zadej user_id (např. TCH20250002)' value='<?= htmlspecialchars($user_id ?? '') ?>'>
        <button type='submit'>Vyhledat</button>
    </form>

<?php
if ($user_id) {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo "<div class='user-card'>";
            echo "<h2>Uživatel nalezen</h2>";

            $fields = [
                'id' => 'Database ID',
                'user_id' => 'User ID',
                'email' => 'Email',
                'jmeno' => 'Jméno',
                'prijmeni' => 'Příjmení',
                'telefon' => 'Telefon',
                'role' => 'Role',
                'is_active' => 'Aktivní',
                'is_admin' => 'Admin',
                'last_login' => 'Poslední přihlášení',
                'created_at' => 'Vytvořen',
                'updated_at' => 'Aktualizován'
            ];

            foreach ($fields as $key => $label) {
                if (isset($user[$key])) {
                    $value = $user[$key];

                    // Formátování speciálních hodnot
                    if ($key === 'is_active' || $key === 'is_admin') {
                        $value = $value ? '✅ Ano' : '❌ Ne';
                    } elseif ($key === 'role') {
                        $roleMap = [
                            'technik' => '🔧 Technik',
                            'prodejce' => '💼 Prodejce',
                            'admin' => '👑 Admin'
                        ];
                        $value = $roleMap[$value] ?? $value;
                    }

                    echo "<div class='user-field'>";
                    echo "<div class='user-field-label'>{$label}:</div>";
                    echo "<div class='user-field-value'>" . htmlspecialchars($value ?? '—') . "</div>";
                    echo "</div>";
                }
            }

            echo "</div>";

            // Zobrazit nedávnou aktivitu
            $stmt = $pdo->prepare("
                SELECT action_type, ip_address, created_at, details
                FROM wgs_audit_log
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['user_id' => $user_id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($logs) {
                echo "<h3>Nedávná aktivita:</h3>";
                echo "<div style='background: #f8f8f8; padding: 15px; border-radius: 8px; font-size: 0.85rem;'>";
                foreach ($logs as $log) {
                    echo "<div style='border-bottom: 1px solid #ddd; padding: 8px 0;'>";
                    echo "<strong>" . htmlspecialchars($log['action_type']) . "</strong> ";
                    echo "z " . htmlspecialchars($log['ip_address']) . " ";
                    echo "(" . htmlspecialchars($log['created_at']) . ")";
                    echo "</div>";
                }
                echo "</div>";
            }

        } else {
            echo "<div class='error'>❌ Uživatel s ID <strong>" . htmlspecialchars($user_id) . "</strong> nebyl nalezen.</div>";
        }

    } catch (PDOException $e) {
        echo "<div class='error'>Chyba databáze: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

    <div style='margin-top: 2rem;'>
        <a href='admin.php' class='btn'>← Zpět na Admin</a>
    </div>
</div>
</body>
</html>
