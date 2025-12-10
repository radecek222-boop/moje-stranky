<?php
/**
 * Diagnostika: Proč prodejce nevidí své reklamace?
 *
 * Spustit jako přihlášený prodejce v prohlížeči:
 * https://www.wgs-service.cz/api/debug_moje_reklamace.php
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nepřihlášen - přihlaste se prosím'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'unknown';
$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

$pdo = getDbConnection();

$diagnostika = [
    'krok_1_session' => [
        'popis' => 'Data ze session po přihlášení',
        'user_id' => $userId,
        'role' => $userRole,
        'email' => $userEmail,
        'name' => $userName
    ]
];

// Krok 2: Najít uživatele v DB
$stmt = $pdo->prepare("SELECT id, user_id, name, email, role FROM wgs_users WHERE user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $userId]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

$diagnostika['krok_2_uzivatel_v_db'] = [
    'popis' => 'Najít uživatele v tabulce wgs_users podle user_id',
    'sql' => "SELECT * FROM wgs_users WHERE user_id = '{$userId}'",
    'vysledek' => $dbUser ?: 'NENALEZEN!'
];

// Krok 3: Počet reklamací s přesným user_id
$stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = :user_id");
$stmt->execute([':user_id' => $userId]);
$pocetPresny = $stmt->fetchColumn();

$diagnostika['krok_3_pocet_presny'] = [
    'popis' => "Počet reklamací s created_by = '{$userId}' (přesná shoda)",
    'sql' => "SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = '{$userId}'",
    'pocet' => (int)$pocetPresny
];

// Krok 4: Ukázka těchto reklamací
if ($pocetPresny > 0) {
    $stmt = $pdo->prepare("SELECT id, reklamace_id, jmeno, email, created_by, stav FROM wgs_reklamace WHERE created_by = :user_id LIMIT 5");
    $stmt->execute([':user_id' => $userId]);
    $ukazka = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $diagnostika['krok_4_ukazka_mych_reklamaci'] = $ukazka;
} else {
    $diagnostika['krok_4_ukazka_mych_reklamaci'] = 'Žádné reklamace nenalezeny';
}

// Krok 5: Všechny unikátní hodnoty created_by v databázi
$stmt = $pdo->query("SELECT DISTINCT created_by, COUNT(*) as pocet FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by != '' GROUP BY created_by");
$vsechnyCreatedBy = $stmt->fetchAll(PDO::FETCH_ASSOC);

$diagnostika['krok_5_vsechny_created_by_hodnoty'] = [
    'popis' => 'Všechny unikátní hodnoty ve sloupci created_by',
    'hodnoty' => $vsechnyCreatedBy
];

// Krok 6: Reklamace BEZ created_by
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NULL OR created_by = ''");
$bezCreatedBy = $stmt->fetchColumn();

$diagnostika['krok_6_bez_created_by'] = [
    'popis' => 'Počet reklamací bez vyplněného created_by',
    'pocet' => (int)$bezCreatedBy
];

// Krok 7: Kontrola logiky filtrování
$isProdejce = in_array(strtolower($userRole), ['prodejce', 'user'], true);
$diagnostika['krok_7_logika_filtrovani'] = [
    'popis' => 'Jak backend filtruje reklamace',
    'role' => $userRole,
    'je_prodejce' => $isProdejce,
    'filtr' => $isProdejce ? "WHERE created_by = '{$userId}'" : 'Jiná role - jiný filtr'
];

// Krok 8: Simulace dotazu z load.php
if ($isProdejce && $userId) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.reklamace_id, r.jmeno, r.created_by, r.stav
        FROM wgs_reklamace r
        WHERE r.created_by = :created_by
        LIMIT 10
    ");
    $stmt->execute([':created_by' => $userId]);
    $simulace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $diagnostika['krok_8_simulace_load_php'] = [
        'popis' => 'Simulace toho, co by vrátil load.php',
        'pocet_vracenych' => count($simulace),
        'data' => $simulace
    ];
}

// ZÁVĚR
if ($pocetPresny == 0) {
    $diagnostika['ZAVER'] = [
        'problem' => 'NALEZEN',
        'popis' => "Žádné reklamace nemají created_by = '{$userId}'",
        'reseni' => 'Spusťte migrační skript /oprav_created_by_prodejcu.php pro opravu starých záznamů'
    ];
} else {
    $diagnostika['ZAVER'] = [
        'problem' => 'Data jsou v pořádku',
        'popis' => "Máte {$pocetPresny} reklamací s created_by = '{$userId}'",
        'poznamka' => 'Problém může být ve frontendu nebo v kešování prohlížeče. Zkuste Ctrl+F5.'
    ];
}

echo json_encode($diagnostika, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
