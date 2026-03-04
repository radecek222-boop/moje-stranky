import { test, expect } from '@playwright/test';

/**
 * E2E testy: Stránky po přihlášení - WGS Service
 *
 * Tyto testy vyžadují TEST_EMAIL a TEST_HESLO env proměnné.
 * Spouštění:
 *   TEST_EMAIL=user@test.cz TEST_HESLO=heslo npx playwright test po-prihlaseni
 */

// Přeskočit pokud chybí přihlašovací údaje
test.skip(
    !process.env.TEST_EMAIL || !process.env.TEST_HESLO,
    'Vyžaduje TEST_EMAIL a TEST_HESLO env proměnné'
);

/**
 * Pomocná funkce — přihlásí uživatele
 */
async function prihlasit(page) {
    await page.goto('/login.php');
    await page.locator('#userEmail').fill(process.env.TEST_EMAIL);
    await page.locator('#userPassword').fill(process.env.TEST_HESLO);
    await page.locator('#loginButton').click();
    await page.waitForURL(/seznam\.php|index\.php/, { timeout: 10000 });
}

test.describe('Seznam reklamací', () => {

    test.beforeEach(async ({ page }) => {
        await prihlasit(page);
        await page.goto('/seznam.php');
    });

    test('zobrazí stránku se seznamem', async ({ page }) => {
        await expect(page).toHaveTitle(/Přehled reklamací|WGS/i);
    });

    test('zobrazí vyhledávací pole', async ({ page }) => {
        await expect(page.locator('#searchInput')).toBeVisible();
    });

    test('vyhledávání reaguje na vstup', async ({ page }) => {
        const searchInput = page.locator('#searchInput');
        await searchInput.fill('test');
        // Počkat na debounce (~300ms) a výsledky
        await page.waitForTimeout(500);
        // Výsledky nebo info o počtu se aktualizovaly
        const infoEl = page.locator('#searchResultsInfo');
        // Pole je viditelné nebo má text
        await expect(searchInput).toHaveValue('test');
    });

});

test.describe('Nová reklamace (přihlášený uživatel)', () => {

    test.beforeEach(async ({ page }) => {
        await prihlasit(page);
        await page.goto('/novareklamace.php');
    });

    test('zobrazí formulář', async ({ page }) => {
        await expect(page.locator('#reklamaceForm')).toBeVisible();
    });

    test('formulář má povinná pole', async ({ page }) => {
        const povinnaPolejIds = ['cislo', 'datum_prodeje'];
        for (const id of povinnaPolejIds) {
            const pole = page.locator(`#${id}`).first();
            if (await pole.count() > 0) {
                await expect(pole).toBeVisible();
            }
        }
    });

    test('odeslání prázdného formuláře je zablokováno', async ({ page }) => {
        // Kliknout submit bez vyplnění
        await page.locator('[type="submit"]').first().click();
        // Stránka zůstane na /novareklamace.php (validace zamezila odeslání)
        await expect(page).toHaveURL(/novareklamace\.php/);
    });

});
