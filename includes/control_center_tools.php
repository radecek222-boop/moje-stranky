<?php
/**
 * Control Center - Nástroje & Diagnostika
 * Čistší verze bez starých dokončených úkolů
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// Detect embed mode
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Check installation status
$rbacInstalled = false;
$fakturaceInstalled = false;

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'created_by'");
    $rbacInstalled = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'fakturace_firma'");
    $fakturaceInstalled = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Ignore errors
}

// Process auto-fix form submission
$fixMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_fix_all_visibility'])) {
    try {
        $stmt = $pdo->query("SELECT id, email, role FROM wgs_users WHERE email IS NOT NULL AND email != ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalFixed = 0;
        $details = [];

        foreach ($users as $user) {
            $userRole = strtolower(trim($user['role'] ?? 'user'));

            $stmt = $pdo->prepare("UPDATE wgs_reklamace
                                  SET created_by = :user_id,
                                      created_by_role = :role
                                  WHERE LOWER(TRIM(email)) = LOWER(:email)
                                  AND (created_by IS NULL OR created_by = 0)");
            $stmt->execute([
                ':user_id' => $user['id'],
                ':email' => $user['email'],
                ':role' => $userRole
            ]);

            $affected = $stmt->rowCount();
            if ($affected > 0) {
                $totalFixed += $affected;
                $details[] = "{$user['email']} ({$userRole}): $affected";
            }
        }

        $fixMessage = "<div style='background: var(--c-success); color: white; padding: 1.5rem; margin-bottom: 1.5rem; font-weight: 600;'>
            AUTO-OPRAVA DOKONČENA!<br>
            Celkem opraveno: <strong>$totalFixed reklamací</strong><br>
            " . (count($details) > 0 ? '<br>' . implode('<br>', $details) : '') . "
        </div>";
    } catch (Exception $e) {
        $fixMessage = "<div style='background: var(--c-error); color: white; padding: 1.5rem; margin-bottom: 1.5rem;'>
            CHYBA: " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
}

// Process individual fix
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_email'])) {
    $emailToFix = trim($_POST['fix_email']);
    $userIdToSet = (int)$_POST['fix_user_id'];

    try {
        $stmt = $pdo->prepare("SELECT role FROM wgs_users WHERE id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userIdToSet]);
        $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        $userRole = strtolower(trim($userRecord['role'] ?? 'user'));

        $stmt = $pdo->prepare("UPDATE wgs_reklamace
                              SET created_by = :user_id,
                                  created_by_role = :role
                              WHERE LOWER(TRIM(email)) = LOWER(:email)
                              AND (created_by IS NULL OR created_by = 0)");
        $stmt->execute([
            ':user_id' => $userIdToSet,
            ':email' => $emailToFix,
            ':role' => $userRole
        ]);

        $affected = $stmt->rowCount();
        $fixMessage = "<div style='background: var(--c-success); color: white; padding: 1.5rem; margin-bottom: 1.5rem; font-weight: 600;'>
            OPRAVENO! $affected reklamací pro $emailToFix<br>
            (created_by = $userIdToSet, role = $userRole)
        </div>";
    } catch (Exception $e) {
        $fixMessage = "<div style='background: var(--c-error); color: white; padding: 1.5rem; margin-bottom: 1.5rem;'>
            CHYBA: " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NULL OR created_by = 0");
$nullCreatedBy = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by > 0");
$hasCreatedBy = $stmt->fetchColumn();
?>

<style>
.tools-container {
    max-width: 1200px;
    margin: <?= $embedMode ? '0' : '2rem' ?> auto;
    padding: <?= $embedMode ? '0.5rem' : '2rem' ?>;
}

.tools-section {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    border-radius: 4px;
}

.tools-section h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--c-black);
    letter-spacing: 0.05em;
    border-left: 3px solid var(--c-success);
    padding-left: 0.5rem;
}

.status-box {
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.75rem;
    border: 2px solid;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.8rem;
}

.status-box.success {
    background: #e8f5e9;
    border-color: var(--c-success);
    color: #2e7d32;
}

.status-box.warning {
    background: #fff3e0;
    border-color: #f57c00;
    color: #e65100;
}

.status-box.installed {
    background: #f5f5f5;
    border-color: var(--c-border);
    color: var(--c-grey);
}

.tools-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.7rem;
    margin-top: 0.5rem;
}

.tools-table th {
    background: var(--c-black);
    color: var(--c-white);
    padding: 0.4rem 0.5rem;
    text-align: left;
    border: 1px solid var(--c-black);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.65rem;
}

.tools-table td {
    padding: 0.4rem 0.5rem;
    border: 1px solid var(--c-border);
    background: var(--c-white);
    font-size: 0.7rem;
}

.tools-table tr.error-row {
    background: #fdd !important;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.stat-item {
    padding: 0.5rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: 4px;
}

.stat-label {
    font-size: 0.65rem;
    color: var(--c-grey);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--c-black);
}

.stat-value.error {
    color: var(--c-error);
}

.stat-value.success {
    color: var(--c-success);
}

/* Compact buttons */
.tools-section .btn {
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
}

.tools-section .btn-sm {
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
}

/* Compact page headers */
.tools-container .page-title {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.tools-container .page-subtitle {
    font-size: 0.75rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .tools-container {
        padding: 1rem;
    }

    .tools-table {
        font-size: 0.75rem;
        display: block;
        overflow-x: auto;
    }
}
</style>

<div class="tools-container">
    <?php if (!$embedMode): ?>
    <h1 class="page-title">Diagnostika systému</h1>
    <p class="page-subtitle">Nástroje pro opravu problémů a údržbu databáze</p>
    <?php endif; ?>

    <?= $fixMessage ?>

    <!-- DIAGNOSTIKA VISIBILITY -->
    <div class="tools-section">
        <h3>Diagnostika viditelnosti reklamací</h3>

        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-label">Celkem reklamací</div>
                <div class="stat-value"><?= $totalClaims ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">created_by = NULL</div>
                <div class="stat-value <?= $nullCreatedBy > 0 ? 'error' : 'success' ?>"><?= $nullCreatedBy ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">created_by vyplněno</div>
                <div class="stat-value success"><?= $hasCreatedBy ?></div>
            </div>
        </div>

        <?php if ($nullCreatedBy > 0): ?>
            <div class="status-box warning">
                <strong>PROBLÉM NALEZEN!</strong><br>
                <span style="font-size: 1.1rem;"><?= $nullCreatedBy ?> reklamací</span> z celkových <strong><?= $totalClaims ?></strong> nemá vyplněné created_by<br>
                → Prodejci tyto reklamace NEVIDÍ v seznam.php
            </div>

            <form method="POST" style="margin-bottom: 1.5rem;">
                <button type="submit" name="auto_fix_all_visibility"
                        onclick="return confirm('OPRAVIT všechny reklamace s NULL created_by?')"
                        class="btn btn-success"
                        style="width: 100%; padding: 1rem;">
                    ⚡ AUTO-OPRAVA VŠECH (<?= $nullCreatedBy ?> reklamací)
                </button>
            </form>

            <!-- Table with problem emails -->
            <?php
            $stmt = $pdo->query("SELECT
                r.email,
                COUNT(*) as total,
                SUM(CASE WHEN r.created_by IS NULL OR r.created_by = 0 THEN 1 ELSE 0 END) as null_count,
                u.id as user_id,
                u.name as user_name,
                u.role
            FROM wgs_reklamace r
            LEFT JOIN wgs_users u ON LOWER(TRIM(u.email)) = LOWER(TRIM(r.email))
            WHERE r.email IS NOT NULL AND r.email != ''
            GROUP BY r.email, u.id, u.name, u.role
            HAVING null_count > 0
            ORDER BY null_count DESC, total DESC");

            $emailGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($emailGroups)): ?>
                <h4 style="margin: 1rem 0 0.5rem 0; font-size: 0.95rem; font-weight: 600;">Reklamace podle emailů:</h4>
                <table class="tools-table">
                    <tr>
                        <th>EMAIL</th>
                        <th>JMÉNO</th>
                        <th>NULL</th>
                        <th>AKCE</th>
                    </tr>
                    <?php foreach ($emailGroups as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
                        <td style="color: var(--c-error); font-weight: bold;"><?= $row['null_count'] ?></td>
                        <td>
                            <?php if ($row['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="fix_email" value="<?= htmlspecialchars($row['email']) ?>">
                                    <input type="hidden" name="fix_user_id" value="<?= $row['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        OPRAVIT (<?= $row['null_count'] ?>)
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--c-error);">User neexistuje</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php else: ?>
            <div class="status-box success">
                V POŘÁDKU: Všechny reklamace mají vyplněné created_by
            </div>
        <?php endif; ?>
    </div>

    <!-- SYSTEM STATUS -->
    <div class="tools-section">
        <h3>Stav systému</h3>

        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-label">Role-Based Access</div>
                <div class="stat-value <?= $rbacInstalled ? 'success' : 'error' ?>">
                    <?= $rbacInstalled ? 'Aktivní' : 'Neinstalováno' ?>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">CZ/SK Fakturace</div>
                <div class="stat-value <?= $fakturaceInstalled ? 'success' : 'error' ?>">
                    <?= $fakturaceInstalled ? 'Aktivní' : 'Neinstalováno' ?>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Databáze</div>
                <div class="stat-value success">Připojeno</div>
            </div>
        </div>

        <?php if (!$rbacInstalled || !$fakturaceInstalled): ?>
            <div class="status-box warning" style="margin-top: 1rem;">
                Některé migrace nejsou nainstalované. Kontaktujte administrátora nebo spusťte instalaci.
            </div>
        <?php endif; ?>
    </div>

    <!-- RECENT CLAIMS -->
    <div class="tools-section">
        <h3>Poslední reklamace (30)</h3>

        <?php
        $stmt = $pdo->query("SELECT
            id,
            reklamace_id,
            cislo,
            email,
            jmeno,
            created_by,
            created_by_role,
            created_at
        FROM wgs_reklamace
        ORDER BY created_at DESC
        LIMIT 30");

        $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div style="overflow-x: auto;">
            <table class="tools-table">
                <tr>
                    <th>ID</th>
                    <th>REK_ID</th>
                    <th>EMAIL</th>
                    <th>JMÉNO</th>
                    <th>CREATED_BY</th>
                    <th>ROLE</th>
                </tr>
                <?php foreach ($allClaims as $claim):
                    $isNull = ($claim['created_by'] === null || $claim['created_by'] == 0);
                ?>
                <tr class="<?= $isNull ? 'error-row' : '' ?>">
                    <td><?= $claim['id'] ?></td>
                    <td style="font-size: 0.75rem;"><?= $claim['reklamace_id'] ?? '-' ?></td>
                    <td style="font-size: 0.75rem;"><?= htmlspecialchars($claim['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($claim['jmeno'] ?? '-') ?></td>
                    <td style="font-weight: bold; <?= $isNull ? 'color: var(--c-error);' : '' ?>">
                        <?= $isNull ? 'NULL' : $claim['created_by'] ?>
                    </td>
                    <td><?= $claim['created_by_role'] ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- DATABASE INFO -->
    <div class="tools-section" style="background: var(--c-bg); border: none;">
        <p style="margin: 0; color: var(--c-grey); font-size: 0.85rem; line-height: 1.6;">
            <strong>Diagnostické nástroje pro údržbu systému.</strong><br>
            Nástroje automaticky kontrolují a opravují nejčastější problémy s viditelností reklamací.
            Všechny operace jsou bezpečné a lze je vrátit zpět pomocí databázové zálohy.
        </p>
    </div>
</div>
