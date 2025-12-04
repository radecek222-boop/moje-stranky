<?php
/**
 * PSA Kalkulátor - Data API
 * Poskytuje data zaměstnanců a období z SQL databáze
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

function respond(string $status, array $payload = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth(): void
{
    $isAdmin = !empty($_SESSION['is_admin']);
    $rawRole = strtolower((string) ($_SESSION['role'] ?? ''));
    $isTechnik = strpos($rawRole, 'technik') !== false || strpos($rawRole, 'technician') !== false;

    if (!$isAdmin && !$isTechnik) {
        respond('error', ['message' => 'Neautorizovaný přístup'], 401);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    requireAuth();
    $pdo = getDbConnection();

    // Kontrola existence tabulek
    $stmt = $pdo->query("SHOW TABLES LIKE 'psa_zamestnanci'");
    $tabulkyExistuji = $stmt->rowCount() > 0;

    if ($method === 'GET') {
        if (!$tabulkyExistuji) {
            // Fallback na JSON pokud tabulky neexistují
            $jsonPath = __DIR__ . '/../data/psa-employees.json';
            if (file_exists($jsonPath)) {
                $data = json_decode(file_get_contents($jsonPath), true);
                respond('success', ['data' => $data, 'source' => 'json']);
            }
            respond('error', ['message' => 'Databáze PSA není inicializována'], 500);
        }

        // Načíst konfiguraci
        $config = [
            'salaryRate' => 150,
            'invoiceRate' => 250,
            'company' => 'White Glove Service',
            'currency' => 'CZK'
        ];

        $stmtCfg = $pdo->query("SELECT klic, hodnota FROM psa_konfigurace");
        while ($row = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
            if ($row['klic'] === 'sazba_vyplata') $config['salaryRate'] = (int)$row['hodnota'];
            if ($row['klic'] === 'sazba_faktura') $config['invoiceRate'] = (int)$row['hodnota'];
            if ($row['klic'] === 'firma') $config['company'] = $row['hodnota'];
            if ($row['klic'] === 'mena') $config['currency'] = $row['hodnota'];
        }

        // Načíst zaměstnance
        $stmtEmp = $pdo->query("
            SELECT id, jmeno, ucet, banka, typ, aktivni, poznamka,
                   swift_iban, swift_bic, swift_banka, swift_adresa, swift_prijemce,
                   pausalni_limit, pausalni_dan
            FROM psa_zamestnanci
            ORDER BY id
        ");

        $employees = [];
        while ($row = $stmtEmp->fetch(PDO::FETCH_ASSOC)) {
            $emp = [
                'id' => (int)$row['id'],
                'name' => $row['jmeno'],
                'account' => $row['ucet'] ?? '',
                'bank' => $row['banka'] ?? '',
                'type' => $row['typ'] ?? 'standard',
                'active' => (bool)$row['aktivni'],
                'hours' => 0
            ];

            if ($row['poznamka']) {
                $emp['note'] = $row['poznamka'];
            }

            // SWIFT data
            if ($row['swift_iban']) {
                $emp['swiftData'] = [
                    'iban' => $row['swift_iban'],
                    'swift' => $row['swift_bic'],
                    'bankName' => $row['swift_banka'],
                    'bankAddress' => $row['swift_adresa'],
                    'beneficiary' => $row['swift_prijemce'],
                    'fees' => 'OUR',
                    'note' => 'Poplatky hradí odesílatel'
                ];
            }

            // Paušální data
            if ($row['pausalni_limit']) {
                $emp['pausalni'] = [
                    'rate' => (float)$row['pausalni_limit'],
                    'tax' => (float)$row['pausalni_dan'],
                    'description' => 'Paušální daň'
                ];
            }

            $employees[] = $emp;
        }

        // Načíst období
        $stmtPeriods = $pdo->query("
            SELECT id, rok, mesic, celkem_hodin, celkem_vyplat, celkem_faktur, zisk,
                   marek_bonus, radek_bonus, holky_bonus, radek_celkem, premie_celkem
            FROM psa_obdobi
            ORDER BY rok DESC, mesic DESC
        ");

        $periods = [];
        while ($period = $stmtPeriods->fetch(PDO::FETCH_ASSOC)) {
            $key = sprintf('%d-%02d', $period['rok'], $period['mesic']);

            // Načíst docházku pro období
            $stmtDoch = $pdo->prepare("
                SELECT d.zamestnanec_id, d.hodiny, d.bonus, d.premie,
                       z.jmeno, z.ucet, z.banka, z.typ
                FROM psa_dochazka d
                JOIN psa_zamestnanci z ON z.id = d.zamestnanec_id
                WHERE d.obdobi_id = ?
            ");
            $stmtDoch->execute([$period['id']]);

            $periodEmployees = [];
            while ($doch = $stmtDoch->fetch(PDO::FETCH_ASSOC)) {
                $periodEmployees[] = [
                    'id' => (int)$doch['zamestnanec_id'],
                    'name' => $doch['jmeno'],
                    'hours' => (float)$doch['hodiny'],
                    'type' => $doch['typ'],
                    'bonusAmount' => (float)$doch['bonus'],
                    'premieCastka' => (float)$doch['premie'],
                    'account' => $doch['ucet'],
                    'bank' => $doch['banka']
                ];
            }

            $periods[$key] = [
                'employees' => $periodEmployees,
                'totalHours' => (float)$period['celkem_hodin'],
                'totalSalary' => (float)$period['celkem_vyplat'],
                'totalInvoice' => (float)$period['celkem_faktur'],
                'profit' => (float)$period['zisk'],
                'marekBonus' => (float)$period['marek_bonus'],
                'radekBonus' => (float)$period['radek_bonus'],
                'girlsBonus' => (float)$period['holky_bonus'],
                'radekTotal' => (float)$period['radek_celkem'],
                'premieCelkem' => (float)$period['premie_celkem']
            ];
        }

        respond('success', [
            'data' => [
                'config' => $config,
                'employees' => $employees,
                'periods' => $periods
            ],
            'source' => 'database'
        ]);

    } elseif ($method === 'POST') {
        // Validace CSRF
        if (!validateCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            respond('error', ['message' => 'Neplatný CSRF token'], 403);
        }

        if (!$tabulkyExistuji) {
            respond('error', ['message' => 'Databáze PSA není inicializována. Spusťte /psa_vytvorit_tabulky.php'], 500);
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            respond('error', ['message' => 'Neplatný JSON'], 400);
        }

        $pdo->beginTransaction();

        try {
            // Uložit konfiguraci
            if (!empty($data['config'])) {
                $stmtCfg = $pdo->prepare("
                    INSERT INTO psa_konfigurace (klic, hodnota)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE hodnota = VALUES(hodnota)
                ");

                if (isset($data['config']['salaryRate'])) {
                    $stmtCfg->execute(['sazba_vyplata', $data['config']['salaryRate']]);
                }
                if (isset($data['config']['invoiceRate'])) {
                    $stmtCfg->execute(['sazba_faktura', $data['config']['invoiceRate']]);
                }
            }

            // Uložit/aktualizovat zaměstnance
            if (!empty($data['employees'])) {
                $stmtEmp = $pdo->prepare("
                    INSERT INTO psa_zamestnanci
                    (id, jmeno, ucet, banka, typ, aktivni, poznamka,
                     swift_iban, swift_bic, swift_banka, swift_adresa, swift_prijemce,
                     pausalni_limit, pausalni_dan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    jmeno = VALUES(jmeno),
                    ucet = VALUES(ucet),
                    banka = VALUES(banka),
                    typ = VALUES(typ),
                    aktivni = VALUES(aktivni),
                    poznamka = VALUES(poznamka),
                    swift_iban = VALUES(swift_iban),
                    swift_bic = VALUES(swift_bic),
                    swift_banka = VALUES(swift_banka),
                    swift_adresa = VALUES(swift_adresa),
                    swift_prijemce = VALUES(swift_prijemce),
                    pausalni_limit = VALUES(pausalni_limit),
                    pausalni_dan = VALUES(pausalni_dan)
                ");

                foreach ($data['employees'] as $emp) {
                    $swift = $emp['swiftData'] ?? null;
                    $pausalni = $emp['pausalni'] ?? null;

                    // Pro nové zaměstnance (záporné ID) nechat auto-increment
                    $empId = ($emp['id'] ?? 0) > 0 ? $emp['id'] : null;

                    $stmtEmp->execute([
                        $empId,
                        $emp['name'] ?? '',
                        $emp['account'] ?? '',
                        $emp['bank'] ?? '',
                        $emp['type'] ?? 'standard',
                        ($emp['active'] ?? true) ? 1 : 0,
                        $emp['note'] ?? null,
                        $swift['iban'] ?? null,
                        $swift['swift'] ?? null,
                        $swift['bankName'] ?? null,
                        $swift['bankAddress'] ?? null,
                        $swift['beneficiary'] ?? null,
                        $pausalni['rate'] ?? null,
                        $pausalni['tax'] ?? null
                    ]);

                    // Pokud byl nový zaměstnanec, získat jeho ID
                    if (!$empId) {
                        $emp['id'] = $pdo->lastInsertId();
                    }
                }
            }

            // Uložit období
            if (!empty($data['periodData'])) {
                $pd = $data['periodData'];
                $rok = $pd['year'] ?? date('Y');
                $mesic = $pd['month'] ?? date('n');

                // Vložit/aktualizovat období
                $stmtPer = $pdo->prepare("
                    INSERT INTO psa_obdobi
                    (rok, mesic, celkem_hodin, celkem_vyplat, celkem_faktur, zisk,
                     marek_bonus, radek_bonus, holky_bonus, radek_celkem, premie_celkem)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    celkem_hodin = VALUES(celkem_hodin),
                    celkem_vyplat = VALUES(celkem_vyplat),
                    celkem_faktur = VALUES(celkem_faktur),
                    zisk = VALUES(zisk),
                    marek_bonus = VALUES(marek_bonus),
                    radek_bonus = VALUES(radek_bonus),
                    holky_bonus = VALUES(holky_bonus),
                    radek_celkem = VALUES(radek_celkem),
                    premie_celkem = VALUES(premie_celkem)
                ");

                $stmtPer->execute([
                    $rok,
                    $mesic,
                    $pd['totalHours'] ?? 0,
                    $pd['totalSalary'] ?? 0,
                    $pd['totalInvoice'] ?? 0,
                    $pd['profit'] ?? 0,
                    $pd['marekBonus'] ?? 0,
                    $pd['radekBonus'] ?? 0,
                    $pd['girlsBonus'] ?? 0,
                    $pd['radekTotal'] ?? 0,
                    $pd['premieCelkem'] ?? 0
                ]);

                // Získat ID období
                $stmtGetId = $pdo->prepare("SELECT id FROM psa_obdobi WHERE rok = ? AND mesic = ?");
                $stmtGetId->execute([$rok, $mesic]);
                $obdobiId = $stmtGetId->fetchColumn();

                // Uložit docházku
                if (!empty($pd['employees']) && $obdobiId) {
                    $stmtDoch = $pdo->prepare("
                        INSERT INTO psa_dochazka
                        (obdobi_id, zamestnanec_id, hodiny, bonus, premie)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        hodiny = VALUES(hodiny),
                        bonus = VALUES(bonus),
                        premie = VALUES(premie)
                    ");

                    foreach ($pd['employees'] as $emp) {
                        if (($emp['id'] ?? 0) > 0) {
                            $stmtDoch->execute([
                                $obdobiId,
                                $emp['id'],
                                $emp['hours'] ?? 0,
                                $emp['bonusAmount'] ?? 0,
                                $emp['premieCastka'] ?? 0
                            ]);
                        }
                    }
                }
            }

            $pdo->commit();
            respond('success', ['message' => 'Data uložena']);

        } catch (Exception $e) {
            $pdo->rollBack();
            respond('error', ['message' => 'Chyba při ukládání: ' . $e->getMessage()], 500);
        }

    } else {
        respond('error', ['message' => 'Metoda není podporována'], 405);
    }

} catch (Throwable $e) {
    respond('error', ['message' => 'Neočekávaná chyba: ' . $e->getMessage()], 500);
}
?>
