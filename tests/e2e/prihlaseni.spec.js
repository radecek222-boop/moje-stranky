import { test, expect } from '@playwright/test';

/**
 * E2E testy: Přihlašování - WGS Service
 *
 * Testuje UI a validaci — nezávisí na živé DB.
 * Testy s reálnými přihlašovacími údaji vyžadují
 * env proměnné TEST_EMAIL a TEST_HESLO.
 */

test.describe('Přihlašovací stránka', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/login.php');
    });

    test('zobrazí přihlašovací formulář', async ({ page }) => {
        await expect(page).toHaveTitle(/WGS|White Glove|přihlášení/i);
        await expect(page.locator('#userEmail')).toBeVisible();
        await expect(page.locator('#userPassword')).toBeVisible();
        await expect(page.locator('#loginButton')).toBeVisible();
    });

    test('zobrazí chybu při prázdném formuláři', async ({ page }) => {
        await page.locator('#loginButton').click();
        // HTML5 validace — pole musí být označena jako required
        const emailInput = page.locator('#userEmail');
        await expect(emailInput).toHaveAttribute('required');
        const passwordInput = page.locator('#userPassword');
        await expect(passwordInput).toHaveAttribute('required');
    });

    test('zobrazí chybu při neplatném formátu emailu', async ({ page }) => {
        await page.locator('#userEmail').fill('neplatny-email');
        await page.locator('#userPassword').fill('heslo123');
        await page.locator('#loginButton').click();
        // HTML5 validace emailu
        const neplatny = await page.locator('#userEmail').evaluate(el => !el.validity.valid);
        expect(neplatny).toBe(true);
    });

    test('zobrazí chybu při nesprávných přihlašovacích údajích', async ({ page }) => {
        await page.locator('#userEmail').fill('neexistujici@test.cz');
        await page.locator('#userPassword').fill('spatneHeslo123');
        await page.locator('#loginButton').click();

        // Počkat na server response nebo notifikaci
        const notifikace = page.locator('#notification');
        await expect(notifikace).toBeVisible({ timeout: 8000 });
    });

    test('přesměruje nepřihlášeného uživatele na login', async ({ page }) => {
        await page.goto('/seznam.php');
        await expect(page).toHaveURL(/login\.php/);
    });

    test('přesměruje nepřihlášeného uživatele při přístupu na admin', async ({ page }) => {
        await page.goto('/admin.php');
        await expect(page).toHaveURL(/login\.php/);
    });

});

test.describe('Přihlášení s platnými údaji', () => {

    // Tyto testy běží pouze pokud jsou nastaveny env proměnné
    test.skip(
        !process.env.TEST_EMAIL || !process.env.TEST_HESLO,
        'Vyžaduje TEST_EMAIL a TEST_HESLO env proměnné'
    );

    test('přihlásí uživatele a přesměruje na seznam', async ({ page }) => {
        await page.goto('/login.php');
        await page.locator('#userEmail').fill(process.env.TEST_EMAIL);
        await page.locator('#userPassword').fill(process.env.TEST_HESLO);
        await page.locator('#loginButton').click();

        await expect(page).toHaveURL(/seznam\.php|index\.php/, { timeout: 10000 });
    });

    test('odhlásí uživatele a přesměruje na login', async ({ page }) => {
        // Nejprve přihlásit
        await page.goto('/login.php');
        await page.locator('#userEmail').fill(process.env.TEST_EMAIL);
        await page.locator('#userPassword').fill(process.env.TEST_HESLO);
        await page.locator('#loginButton').click();
        await page.waitForURL(/seznam\.php|index\.php/, { timeout: 10000 });

        // Odhlásit
        await page.goto('/logout.php');
        await expect(page).toHaveURL(/login\.php/);
    });

});
