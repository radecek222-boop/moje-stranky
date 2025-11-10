<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test všech tlačítek</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wgs-white);
            color: var(--wgs-black);
            padding: 2rem;
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid var(--wgs-black);
        }

        h1 {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 1.75rem;
        }

        .section {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
        }

        .checklist {
            list-style: none;
        }

        .checklist li {
            padding: 0.75rem;
            margin: 0.5rem 0;
            background: #f8f8f8;
            border: 2px solid var(--wgs-border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid var(--wgs-black);
            cursor: pointer;
            flex-shrink: 0;
        }

        .checkbox.checked {
            background: var(--wgs-black);
            position: relative;
        }

        .checkbox.checked:after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
        }

        .test-button {
            padding: 0.5rem 1rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .test-button:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .description {
            flex: 1;
            font-size: 0.85rem;
            color: var(--wgs-grey);
        }

        .status {
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            border: 2px solid;
        }

        .status-pending { background: #fff3e0; color: #e65100; border-color: #f57c00; }
        .status-ok { background: #e8f5e9; color: #2e7d32; border-color: #4CAF50; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TEST VŠECH TLAČÍTEK</h1>
        <p style="color: #999; font-size: 0.875rem; margin-top: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Manuální checklist pro ověření funkčnosti admin panelu
        </p>
    </div>

    <div class="section">
        <h2>1. DEBUG NÁSTROJE</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Tyto nástroje se nacházejí v admin.php?tab=tools v sekci "DEBUG NÁSTROJE"
        </p>

        <ul class="checklist">
            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>⚡ AKTIVNÍ DIAGNOSTIKA</strong> - Real-time testování přístupů
                    <br><small>Mělo by se otevřít diagnostic_access_active.php v novém okně</small>
                </div>
                <a href="/diagnostic_access_active.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>SQL/PHP Debug</strong> - Ruční SQL dotazy
                    <br><small>Mělo by se otevřít diagnostic_tool.php v novém okně</small>
                </div>
                <a href="/diagnostic_tool.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>Dokumentace systému</strong> - Popis řízení přístupu
                    <br><small>Mělo by se otevřít diagnostic_access_control.php v novém okně</small>
                </div>
                <a href="/diagnostic_access_control.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>STRUKTURA</strong> - Databázová struktura
                    <br><small>Mělo by se otevřít show_table_structure.php v novém okně</small>
                </div>
                <a href="/show_table_structure.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>FOTKY</strong> - Debug fotek
                    <br><small>Mělo by se otevřít debug_photos.php v novém okně</small>
                </div>
                <a href="/debug_photos.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>TEST ✓</strong> - Validace nástrojů
                    <br><small>Mělo by se otevřít validate_tools.php v novém okně</small>
                </div>
                <a href="/validate_tools.php" target="_blank" class="test-button">TEST</a>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>2. TESTOVÁNÍ ROLÍ</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Tlačítka pro simulaci různých rolí - <strong>POZOR: Mění session!</strong>
        </p>

        <ul class="checklist">
            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>[Admin]</strong> - Simulace admin role
                    <br><small>Změní session: user_id=1, user_email=admin@wgs-service.cz, role=admin</small>
                </div>
                <span class="status status-pending">FORMA POST</span>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>[Prodejce]</strong> - Simulace prodejce
                    <br><small>Změní session: user_id=7, user_email=naty@naty.cz, role=prodejce</small>
                </div>
                <span class="status status-pending">FORMA POST</span>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>[Technik]</strong> - Simulace technika
                    <br><small>Změní session: user_id=15, user_email=milan@technik.cz, role=technik</small>
                </div>
                <span class="status status-pending">FORMA POST</span>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>[Guest]</strong> - Simulace guesta
                    <br><small>Změní session: user_id=NULL, user_email=jiri@novacek.cz, role=guest</small>
                </div>
                <span class="status status-pending">FORMA POST</span>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>[RESET NA ADMIN]</strong> - Obnoví původní admin session
                    <br><small>Zobrazuje se pouze když je aktivní simulace</small>
                </div>
                <span class="status status-pending">FORMA POST</span>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>3. TESTOVACÍ ODKAZY (v sekci Testování rolí)</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Odkazy pro otevření hlavních stránek v novém okně - použij po simulaci role!
        </p>

        <ul class="checklist">
            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>SEZNAM</strong> - Seznam reklamací
                    <br><small>Mělo by se otevřít seznam.php v novém okně se simulovanou rolí</small>
                </div>
                <a href="/seznam.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>DB</strong> - Struktura databáze
                    <br><small>Mělo by se otevřít show_table_structure.php v novém okně</small>
                </div>
                <a href="/show_table_structure.php" target="_blank" class="test-button">TEST</a>
            </li>

            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>DIAGNOSTIKA</strong> - Webová diagnostika
                    <br><small>Mělo by se otevřít diagnostic_web.php v novém okně</small>
                </div>
                <a href="/diagnostic_web.php" target="_blank" class="test-button">TEST</a>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>4. INSTALACE & MIGRACE</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1rem;">
            Instalační nástroje pro RBAC systém
        </p>

        <ul class="checklist">
            <li>
                <div class="checkbox" onclick="this.classList.toggle('checked')"></div>
                <div class="description">
                    <strong>ZOBRAZIT DETAIL / SPUSTIT INSTALACI</strong> - RBAC instalátor
                    <br><small>Mělo by se otevřít install_role_based_access.php v novém okně</small>
                </div>
                <a href="/install_role_based_access.php" target="_blank" class="test-button">TEST</a>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>✅ OČEKÁVANÉ CHOVÁNÍ</h2>
        <div style="font-size: 0.85rem; color: var(--wgs-grey); line-height: 1.6;">
            <h3 style="font-size: 0.9rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">DEBUG NÁSTROJE:</h3>
            <ul style="margin-left: 1.5rem;">
                <li>Všechna tlačítka otevírají nové okno (target="_blank")</li>
                <li>Žádné chybové hlášky v konzoli</li>
                <li>Stránky se načítají správně</li>
            </ul>

            <h3 style="font-size: 0.9rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">TESTOVÁNÍ ROLÍ:</h3>
            <ul style="margin-left: 1.5rem;">
                <li>Po kliknutí na roli: stránka se přenačte s parametrem ?simulated=role</li>
                <li>Session info zobrazuje správné hodnoty</li>
                <li>Upozornění "SIMULACE AKTIVNÍ" se zobrazí</li>
                <li>Tlačítko [RESET NA ADMIN] se objeví</li>
            </ul>

            <h3 style="font-size: 0.9rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">TESTOVACÍ ODKAZY:</h3>
            <ul style="margin-left: 1.5rem;">
                <li>Otevírají se v novém okně</li>
                <li>Používají simulovanou session (pokud je aktivní)</li>
                <li>SEZNAM zobrazuje pouze reklamace podle simulované role</li>
            </ul>
        </div>
    </div>

    <div class="section">
        <h2>🔧 CO BYLO OPRAVENO</h2>
        <div style="font-size: 0.85rem; color: var(--wgs-grey); line-height: 1.6;">
            <strong style="color: #4CAF50;">✓ Session klíče:</strong> Změněno z 'email' na 'user_email' (konzistence s login_controller)
            <br>
            <strong style="color: #4CAF50;">✓ Session klíče:</strong> Změněno z 'name' na 'user_name' (konzistence s login_controller)
            <br>
            <strong style="color: #4CAF50;">✓ Session info:</strong> Zobrazuje správné klíče (user_email místo email)
            <br>
            <strong style="color: #4CAF50;">✓ Reset funkce:</strong> Obnovuje správné klíče při resetu
        </div>
    </div>

    <a href="/admin.php?tab=tools" style="display: inline-block; margin-top: 2rem; padding: 0.75rem 1.5rem; background: var(--wgs-black); color: var(--wgs-white); text-decoration: none; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border: 2px solid var(--wgs-black); transition: all 0.3s;">
        ← ZPĚT NA ADMIN PANEL
    </a>

    <div style="margin-top: 2rem; text-align: center; color: var(--wgs-grey); font-size: 0.8rem;">
        <small>WGS SERVICE - TEST VŠECH TLAČÍTEK © 2025</small>
    </div>

    <script>
        // Automatický počet zaškrtnutých checkboxů
        setInterval(() => {
            const total = document.querySelectorAll('.checkbox').length;
            const checked = document.querySelectorAll('.checkbox.checked').length;
            document.title = `Test tlačítek (${checked}/${total})`;
        }, 500);
    </script>
</body>
</html>
