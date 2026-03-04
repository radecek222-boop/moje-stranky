import { test, expect } from '@playwright/test';

/**
 * E2E testy: Veřejné stránky - WGS Service
 *
 * Testuje dostupnost a základní obsah veřejně přístupných stránek.
 * Nevyžaduje přihlášení ani DB.
 */

test.describe('Veřejné stránky', () => {

    test('domovská stránka se načte', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/WGS|White Glove/i);
        await expect(page.locator('body')).toBeVisible();
    });

    test('přihlašovací stránka se načte', async ({ page }) => {
        await page.goto('/login.php');
        await expect(page.locator('#userEmail')).toBeVisible();
        await expect(page.locator('#userPassword')).toBeVisible();
    });

    test('ceník se načte a zobrazí nadpis', async ({ page }) => {
        await page.goto('/cenik.php');
        await expect(page.locator('h1.hero-title')).toBeVisible();
        await expect(page.locator('h1.hero-title')).toContainText('Ceník');
    });

    test('ceník obsahuje kalkulačku', async ({ page }) => {
        await page.goto('/cenik.php');
        await expect(page.locator('#kalkulacka')).toBeVisible();
    });

    test('veřejný formulář nové reklamace se načte', async ({ page }) => {
        await page.goto('/novareklamace.php');
        await expect(page.locator('#reklamaceForm')).toBeVisible();
    });

    test('GDPR stránka se načte', async ({ page }) => {
        await page.goto('/gdpr.php');
        await expect(page.locator('body')).toBeVisible();
        // Zkontrolovat HTTP kód 200 (ne 404/500)
        const response = await page.goto('/gdpr.php');
        expect(response?.status()).toBe(200);
    });

    test('health endpoint vrací 200', async ({ page }) => {
        const response = await page.goto('/health.php');
        expect(response?.status()).toBe(200);
    });

    test('stránka podmínek se načte', async ({ page }) => {
        const response = await page.goto('/podminky.php');
        expect(response?.status()).toBe(200);
    });

});

test.describe('Přesměrování chráněných stránek', () => {

    const chraneneStranky = [
        '/seznam.php',
        '/admin.php',
        '/statistiky.php',
        '/protokol.php',
    ];

    for (const stranka of chraneneStranky) {
        test(`${stranka} přesměruje na login`, async ({ page }) => {
            await page.goto(stranka);
            await expect(page).toHaveURL(/login\.php/);
        });
    }

});

test.describe('API endpointy - základní dostupnost', () => {

    test('get_csrf_token vrací token', async ({ page }) => {
        const odpoved = await page.request.get('/app/controllers/get_csrf_token.php');
        expect(odpoved.status()).toBe(200);
        const json = await odpoved.json();
        expect(json).toHaveProperty('token');
        expect(typeof json.token).toBe('string');
        expect(json.token.length).toBeGreaterThan(10);
    });

    test('debug skripty jsou chráněny (vrací 403)', async ({ page }) => {
        const chraneneNastroje = [
            '/diagnostika_system.php',
            '/vsechny_tabulky.php',
            '/debug_geocoding.php',
        ];

        for (const url of chraneneNastroje) {
            const odpoved = await page.request.get(url);
            expect(odpoved.status()).toBe(403);
        }
    });

});
