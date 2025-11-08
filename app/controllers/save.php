<?php
/**
 * Save Controller
 * Ukládání reklamací a servisních požadavků
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // Kontrola CSRF tokenu
    requireCSRF();

    // Kontrola action
    $action = $_POST['action'] ?? '';
    if ($action !== 'create') {
        throw new Exception('Neplatná akce');
    }

    // Získání dat z formuláře
    $typ = $_POST['typ'] ?? 'servis';
    $cislo = $_POST['cislo'] ?? '';
    $datumProdeje = $_POST['datum_prodeje'] ?? null;
    $datumReklamace = $_POST['datum_reklamace'] ?? null;
    $jmeno = $_POST['jmeno'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefon = $_POST['telefon'] ?? '';
    $adresa = $_POST['adresa'] ?? '';
    $model = $_POST['model'] ?? '';
    $provedeni = $_POST['provedeni'] ?? '';
    $barva = $_POST['barva'] ?? '';
    $serioveCislo = $_POST['seriove_cislo'] ?? '';
    $popisProblemu = $_POST['popis_problemu'] ?? '';
    $doplnujiciInfo = $_POST['doplnujici_info'] ?? '';
    $fakturaceFirma = $_POST['fakturace_firma'] ?? 'CZ';

    // Validace povinných polí
    if (empty($jmeno)) {
        throw new Exception('Jméno je povinné');
    }
    if (empty($telefon) && empty($email)) {
        throw new Exception('Je nutné vyplnit telefon nebo email');
    }
    if (empty($popisProblemu)) {
        throw new Exception('Popis problému je povinný');
    }

    // Formátování dat pro databázi
    $datumProdejeForDb = null;
    if (!empty($datumProdeje) && $datumProdeje !== 'nevyplňuje se') {
        // Převod z českého formátu dd.mm.yyyy na yyyy-mm-dd
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datumProdeje, $matches)) {
            $datumProdejeForDb = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
    }

    $datumReklamaceForDb = null;
    if (!empty($datumReklamace) && $datumReklamace !== 'nevyplňuje se') {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datumReklamace, $matches)) {
            $datumReklamaceForDb = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // Vložení do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_reklamace (
            typ, cislo, datum_prodeje, datum_reklamace,
            jmeno, email, telefon, adresa,
            model, provedeni, barva, seriove_cislo,
            popis_problemu, doplnujici_info, fakturace_firma,
            created_at, updated_at
        ) VALUES (
            :typ, :cislo, :datum_prodeje, :datum_reklamace,
            :jmeno, :email, :telefon, :adresa,
            :model, :provedeni, :barva, :seriove_cislo,
            :popis_problemu, :doplnujici_info, :fakturace_firma,
            NOW(), NOW()
        )
    ");

    $result = $stmt->execute([
        ':typ' => $typ,
        ':cislo' => $cislo,
        ':datum_prodeje' => $datumProdejeForDb,
        ':datum_reklamace' => $datumReklamaceForDb,
        ':jmeno' => $jmeno,
        ':email' => $email,
        ':telefon' => $telefon,
        ':adresa' => $adresa,
        ':model' => $model,
        ':provedeni' => $provedeni,
        ':barva' => $barva,
        ':seriove_cislo' => $serioveCislo,
        ':popis_problemu' => $popisProblemu,
        ':doplnujici_info' => $doplnujiciInfo,
        ':fakturace_firma' => $fakturaceFirma
    ]);

    if (!$result) {
        throw new Exception('Chyba při ukládání do databáze');
    }

    $reklamaceId = $pdo->lastInsertId();

    // Úspěšná odpověď
    echo json_encode([
        'status' => 'success',
        'message' => 'Reklamace byla úspěšně vytvořena',
        'reklamace_id' => $reklamaceId,
        'id' => $reklamaceId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
