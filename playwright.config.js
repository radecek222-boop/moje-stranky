import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E konfigurace - WGS Service
 *
 * Spuštění:
 *   npx playwright test              - všechny testy
 *   npx playwright test --headed     - s prohlížečem
 *   npx playwright test prihlaseni   - jen přihlášení
 *   npx playwright show-report       - HTML report
 *
 * Proměnné prostředí:
 *   BASE_URL     - adresa serveru (výchozí: http://localhost:8080)
 *   TEST_EMAIL   - testovací email (pro smoke testy)
 *   TEST_HESLO   - testovací heslo
 */

const zakladniUrl = process.env.BASE_URL || 'http://localhost:8080';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: false,       // PHP dev server = jednoprocesový
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'on-failure' }]],

    use: {
        baseURL: zakladniUrl,
        locale: 'cs-CZ',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'on-first-retry',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    // PHP dev server pro lokální vývoj (v CI server stojí samostatně)
    webServer: process.env.CI ? undefined : {
        command: 'php -S localhost:8080 -t /home/user/moje-stranky',
        url: 'http://localhost:8080',
        reuseExistingServer: true,
        timeout: 15_000,
    },
});
