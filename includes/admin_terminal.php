<?php
/**
 * Admin Terminal - Serverovy terminal v prohlizeci
 *
 * Umoznuje spoustet shell prikazy primo z admin panelu.
 * POUZE PRO ADMINA!
 */

// Bezpecnostni kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('<div class="error">Pristup odepren</div>');
}

// Kontrola dostupnosti shell funkci
$disabledFunctions = array_map('trim', explode(',', ini_get('disable_functions')));
$shellExecPovoleno = function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions);
$execPovoleno = function_exists('exec') && !in_array('exec', $disabledFunctions);
$procOpenPovoleno = function_exists('proc_open') && !in_array('proc_open', $disabledFunctions);

$shellDostupny = $shellExecPovoleno || $execPovoleno || $procOpenPovoleno;

// Zpracovat prikaz pokud byl odeslan
$vystupTerminalu = '';
$posledniPrikaz = '';
$chybaTerminalu = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminal_prikaz'])) {
    // Kontrola shell funkci
    if (!$shellDostupny) {
        $chybaTerminalu = 'Shell funkce jsou na tomto hostingu zakazany. Terminal neni dostupny.';
    }
    // CSRF kontrola
    elseif (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $chybaTerminalu = 'Neplatny CSRF token';
    } else {
        $prikaz = trim($_POST['terminal_prikaz']);
        $posledniPrikaz = $prikaz;

        if (!empty($prikaz)) {
            // Bezpecnostni omezeni - zakazane prikazy
            $zakazanePrikazy = [
                'rm -rf /',
                'rm -rf /*',
                'mkfs',
                'dd if=',
                ':(){:|:&};:',
                '> /dev/sda',
                'chmod -R 777 /',
                'wget.*|.*sh',
                'curl.*|.*sh',
            ];

            $jeZakazany = false;
            foreach ($zakazanePrikazy as $zakazany) {
                if (stripos($prikaz, $zakazany) !== false || preg_match('/' . $zakazany . '/i', $prikaz)) {
                    $jeZakazany = true;
                    break;
                }
            }

            if ($jeZakazany) {
                $chybaTerminalu = 'Tento prikaz je zakazan z bezpecnostnich duvodu.';
            } else {
                // Nastavit pracovni adresar na root projektu
                $projektDir = dirname(__DIR__);
                chdir($projektDir);

                // Spustit prikaz s timeoutem
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];

                $process = proc_open($prikaz, $descriptors, $pipes, $projektDir);

                if (is_resource($process)) {
                    // Zavrit stdin
                    fclose($pipes[0]);

                    // Precist stdout a stderr
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);

                    fclose($pipes[1]);
                    fclose($pipes[2]);

                    $navratovyKod = proc_close($process);

                    // Sestavit vystup
                    $vystupTerminalu = '';
                    if ($stdout) {
                        $vystupTerminalu .= $stdout;
                    }
                    if ($stderr) {
                        $vystupTerminalu .= ($vystupTerminalu ? "\n" : '') . $stderr;
                    }
                    if (empty($vystupTerminalu)) {
                        $vystupTerminalu = "(Prikaz nevratil zadny vystup, navratovy kod: {$navratovyKod})";
                    }
                } else {
                    $chybaTerminalu = 'Nelze spustit prikaz. Zkontrolujte prava.';
                }
            }
        }
    }
}

// Ziskat aktualni adresar
$aktualniAdresar = dirname(__DIR__);
$phpVerze = phpversion();
$serverInfo = php_uname('s') . ' ' . php_uname('r');
?>

<div id="tab-terminal" class="tab-content">
    <div class="terminal-container">
        <div class="terminal-header">
            <h3 class="terminal-title" data-lang-cs="Serverovy Terminal" data-lang-en="Server Terminal" data-lang-it="Terminale Server">Serverovy Terminal</h3>
            <div class="terminal-info">
                <span class="terminal-info-item">PHP <?php echo $phpVerze; ?></span>
                <span class="terminal-info-item"><?php echo $serverInfo; ?></span>
            </div>
        </div>

        <?php if (!$shellDostupny): ?>
        <div class="terminal-disabled">
            <h4>Terminal neni dostupny</h4>
            <p>Shell funkce (shell_exec, exec, proc_open) jsou na tomto hostingu zakazany.</p>
            <p>Pro spusteni prikazu pouzijte:</p>
            <ul>
                <li><strong>SSH pristup</strong> - pripojte se pres SSH klienta</li>
                <li><strong>Hosting panel</strong> - nektere hostingy maji webovy terminal</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="terminal-body">
            <!-- Vystup -->
            <div class="terminal-output" id="terminalVystup">
                <div class="terminal-welcome">
                    <div>WGS Terminal v1.0</div>
                    <div>Pracovni adresar: <?php echo htmlspecialchars($aktualniAdresar); ?></div>
                    <div>---</div>
                </div>

                <?php if ($posledniPrikaz): ?>
                <div class="terminal-command-line">
                    <span class="terminal-prompt">$</span>
                    <span class="terminal-command"><?php echo htmlspecialchars($posledniPrikaz); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($chybaTerminalu): ?>
                <div class="terminal-error"><?php echo htmlspecialchars($chybaTerminalu); ?></div>
                <?php elseif ($vystupTerminalu): ?>
                <pre class="terminal-result"><?php echo htmlspecialchars($vystupTerminalu); ?></pre>
                <?php endif; ?>
            </div>

            <!-- Vstup -->
            <form method="POST" action="admin.php?tab=admin_terminal" class="terminal-input-form" id="terminalForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="terminal-input-wrapper">
                    <span class="terminal-prompt">$</span>
                    <input type="text"
                           name="terminal_prikaz"
                           id="terminalPrikaz"
                           class="terminal-input"
                           placeholder="Zadejte prikaz..."
                           autocomplete="off"
                           autofocus>
                    <button type="submit" class="terminal-submit-btn">Spustit</button>
                </div>
            </form>
        </div>

        <!-- Rychle prikazy -->
        <div class="terminal-shortcuts">
            <h4 data-lang-cs="Rychle prikazy:" data-lang-en="Quick Commands:" data-lang-it="Comandi Rapidi:">Rychle prikazy:</h4>
            <div class="terminal-shortcuts-grid">
                <button type="button" class="terminal-shortcut" data-cmd="ls -la">ls -la</button>
                <button type="button" class="terminal-shortcut" data-cmd="pwd">pwd</button>
                <button type="button" class="terminal-shortcut" data-cmd="php -v">php -v</button>
                <button type="button" class="terminal-shortcut" data-cmd="composer --version">composer --version</button>
                <button type="button" class="terminal-shortcut" data-cmd="composer update">composer update</button>
                <button type="button" class="terminal-shortcut" data-cmd="composer install">composer install</button>
                <button type="button" class="terminal-shortcut" data-cmd="git status">git status</button>
                <button type="button" class="terminal-shortcut" data-cmd="git log --oneline -5">git log -5</button>
                <button type="button" class="terminal-shortcut" data-cmd="df -h">df -h (disk)</button>
                <button type="button" class="terminal-shortcut" data-cmd="free -m">free -m (RAM)</button>
                <button type="button" class="terminal-shortcut" data-cmd="cat .env | head -20">.env (prvnich 20)</button>
                <button type="button" class="terminal-shortcut" data-cmd="tail -50 logs/php_errors.log">PHP chyby</button>
            </div>
        </div>

        <!-- Napoveda -->
        <div class="terminal-help">
            <h4 data-lang-cs="Napoveda:" data-lang-en="Help:" data-lang-it="Aiuto:">Napoveda:</h4>
            <ul>
                <li>Prikazy se spousti v adresari: <code><?php echo htmlspecialchars($aktualniAdresar); ?></code></li>
                <li>Pro zmenu adresare pouzijte: <code>cd adresar && prikaz</code></li>
                <li>Nebezpecne prikazy (rm -rf /, mkfs, atd.) jsou blokovany</li>
                <li>Vystup je omezen na textovy format</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.terminal-container {
    background: #1a1a1a;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.terminal-header {
    background: #2d2d2d;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #444;
}

.terminal-title {
    color: #fff;
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.terminal-info {
    display: flex;
    gap: 15px;
}

.terminal-info-item {
    color: #888;
    font-size: 0.8rem;
}

.terminal-body {
    padding: 0;
}

.terminal-output {
    background: #0d0d0d;
    min-height: 300px;
    max-height: 500px;
    overflow-y: auto;
    padding: 20px;
    color: #00ff00;
    font-size: 0.9rem;
    line-height: 1.6;
}

.terminal-welcome {
    color: #888;
    margin-bottom: 15px;
}

.terminal-command-line {
    margin: 10px 0;
}

.terminal-prompt {
    color: #00ff00;
    margin-right: 8px;
    font-weight: bold;
}

.terminal-command {
    color: #fff;
}

.terminal-result {
    color: #ccc;
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 10px 0;
    font-family: inherit;
    font-size: inherit;
    background: transparent;
    padding: 0;
}

.terminal-error {
    color: #ff6b6b;
    margin: 10px 0;
}

.terminal-disabled {
    background: #2d2d2d;
    padding: 40px;
    text-align: center;
    color: #ccc;
}

.terminal-disabled h4 {
    color: #ff6b6b;
    margin: 0 0 15px 0;
    font-size: 1.2rem;
}

.terminal-disabled p {
    margin: 10px 0;
    color: #999;
}

.terminal-disabled ul {
    list-style: none;
    padding: 0;
    margin: 20px 0 0 0;
}

.terminal-disabled li {
    margin: 10px 0;
    color: #888;
}

.terminal-disabled strong {
    color: #ccc;
}

.terminal-input-form {
    background: #1a1a1a;
    border-top: 1px solid #333;
}

.terminal-input-wrapper {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    gap: 10px;
}

.terminal-input {
    flex: 1;
    background: #0d0d0d;
    border: 1px solid #333;
    color: #fff;
    padding: 12px 15px;
    font-family: inherit;
    font-size: 0.95rem;
    border-radius: 6px;
    outline: none;
}

.terminal-input:focus {
    border-color: #555;
}

.terminal-input::placeholder {
    color: #555;
}

.terminal-submit-btn {
    background: #333;
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.terminal-submit-btn:hover {
    background: #444;
}

.terminal-shortcuts {
    background: #2d2d2d;
    padding: 20px;
    border-top: 1px solid #444;
}

.terminal-shortcuts h4 {
    color: #fff;
    margin: 0 0 15px 0;
    font-size: 0.9rem;
}

.terminal-shortcuts-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.terminal-shortcut {
    background: #3d3d3d;
    color: #ccc;
    border: 1px solid #555;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.terminal-shortcut:hover {
    background: #4d4d4d;
    color: #fff;
    border-color: #666;
}

.terminal-help {
    background: #1a1a1a;
    padding: 20px;
    border-top: 1px solid #333;
}

.terminal-help h4 {
    color: #888;
    margin: 0 0 10px 0;
    font-size: 0.85rem;
}

.terminal-help ul {
    color: #666;
    margin: 0;
    padding-left: 20px;
    font-size: 0.8rem;
    line-height: 1.8;
}

.terminal-help code {
    background: #2d2d2d;
    padding: 2px 8px;
    border-radius: 4px;
    color: #999;
}

/* Scrollbar */
.terminal-output::-webkit-scrollbar {
    width: 8px;
}

.terminal-output::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.terminal-output::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 4px;
}

.terminal-output::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Responzivita */
@media (max-width: 768px) {
    .terminal-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }

    .terminal-shortcuts-grid {
        justify-content: center;
    }

    .terminal-input-wrapper {
        flex-direction: column;
    }

    .terminal-input {
        width: 100%;
    }

    .terminal-submit-btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rychle prikazy
    const shortcuts = document.querySelectorAll('.terminal-shortcut');
    const input = document.getElementById('terminalPrikaz');
    const form = document.getElementById('terminalForm');

    shortcuts.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const cmd = this.getAttribute('data-cmd');
            input.value = cmd;
            input.focus();
        });
    });

    // Dvojklik spusti prikaz
    shortcuts.forEach(function(btn) {
        btn.addEventListener('dblclick', function() {
            const cmd = this.getAttribute('data-cmd');
            input.value = cmd;
            form.submit();
        });
    });

    // Scrollovat vystup dolu
    const vystup = document.getElementById('terminalVystup');
    if (vystup) {
        vystup.scrollTop = vystup.scrollHeight;
    }

    // Focus na input
    if (input) {
        input.focus();
    }

    // Historie prikazu (localStorage)
    let historie = JSON.parse(localStorage.getItem('terminal_historie') || '[]');
    let historieIndex = historie.length;

    input.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historieIndex > 0) {
                historieIndex--;
                input.value = historie[historieIndex] || '';
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historieIndex < historie.length - 1) {
                historieIndex++;
                input.value = historie[historieIndex] || '';
            } else {
                historieIndex = historie.length;
                input.value = '';
            }
        }
    });

    // Ulozit prikaz do historie
    form.addEventListener('submit', function() {
        const cmd = input.value.trim();
        if (cmd && (historie.length === 0 || historie[historie.length - 1] !== cmd)) {
            historie.push(cmd);
            // Omezit na 50 prikazu
            if (historie.length > 50) {
                historie = historie.slice(-50);
            }
            localStorage.setItem('terminal_historie', JSON.stringify(historie));
        }
    });
});
</script>
