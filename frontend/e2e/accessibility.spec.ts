import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { searchOnHome } from './support/ui';

/**
 * WCAG 2.1 nível AA nas telas públicas (sem exigir login) -- as tags abaixo
 * são exatamente as que o axe-core mapeia pra esse nível de conformidade.
 */
const WCAG_21_AA_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

const PUBLIC_PAGES = ['/', '/login', '/register', '/forgot-password'];

for (const path of PUBLIC_PAGES) {
    test(`${path} não tem violação de WCAG 2.1 AA`, async ({ page }) => {
        await page.goto(path);

        const results = await new AxeBuilder({ page }).withTags(WCAG_21_AA_TAGS).analyze();

        expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
    });
}

async function assertNoViolations(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(WCAG_21_AA_TAGS).analyze();

    expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
}

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

async function mockZipCodeLookup(page: Page): Promise<void> {
    await page.route('**/api/zip-codes/**', async (route) => {
        await route.fulfill({
            json: { data: { street: 'Rua de Teste', neighborhood: 'Centro', city: 'São Paulo', state: 'SP' } },
        });
    });
}

async function registerSellerWithVehicle(
    page: Page,
    dealershipName: string,
    brand: string,
    model: string,
): Promise<void> {
    await page.goto('/register');
    await page.getByLabel('Nome').fill('Seller A11y');
    await page.getByLabel('E-mail').fill(uniqueEmail('a11y'));
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Vendedor' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await page.goto('/dealerships');
    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    await page.getByLabel('Nome').fill(dealershipName);
    await page.getByLabel('CEP').fill('01000-000');
    await page.getByLabel('Número').click();
    await expect(page.getByLabel('Endereço')).toHaveValue('Rua de Teste');
    await page.getByLabel('Número').fill('10');
    await page.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('cell', { name: dealershipName })).toBeVisible();

    await page.goto('/vehicles');
    await page.getByRole('button', { name: 'Novo veículo' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Concessionária').fill(dealershipName);
    await page.getByRole('option', { name: dealershipName }).click();
    await dialog.getByLabel('Marca').fill(brand);
    await dialog.getByLabel(/^Modelo/).fill(model);
    await dialog.getByLabel('Preço').fill('89900.00');
    await dialog.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('group', { name: `${brand} ${model}` })).toBeVisible();
}

test('página pública do veículo não tem violação de WCAG 2.1 AA', async ({ page }) => {
    await mockZipCodeLookup(page);
    const brand = `A11y${Date.now()}`;
    await registerSellerWithVehicle(page, `A11y Center ${Date.now()}`, brand, 'Vehicle');
    await page.getByRole('button', { name: 'Sair' }).click();

    await page.goto('/');
    await searchOnHome(page, brand);
    await page.getByRole('heading', { name: `${brand} Vehicle` }).click();
    await expect(page).toHaveURL(/\/veiculos\//);

    await assertNoViolations(page);
});

test('página pública da concessionária não tem violação de WCAG 2.1 AA', async ({ page }) => {
    await mockZipCodeLookup(page);
    const dealershipName = `A11y Dealership ${Date.now()}`;
    await registerSellerWithVehicle(page, dealershipName, `A11y${Date.now()}`, 'Vehicle');

    await page.goto('/dealerships');
    const publicUrl = await page
        .getByRole('row', { name: new RegExp(dealershipName) })
        .getByRole('link', { name: 'Ver página pública' })
        .getAttribute('href');
    await page.getByRole('button', { name: 'Sair' }).click();

    await page.goto(publicUrl!);
    await assertNoViolations(page);
});

test('painel autenticado (veículos, concessionárias, agendamentos, perfil) não tem violação de WCAG 2.1 AA', async ({
    page,
}) => {
    await mockZipCodeLookup(page);
    await registerSellerWithVehicle(page, `A11y Panel ${Date.now()}`, `A11y${Date.now()}`, 'Vehicle');

    await page.goto('/vehicles');
    await assertNoViolations(page);

    await page.goto('/dealerships');
    await assertNoViolations(page);

    await page.goto('/appointments');
    await assertNoViolations(page);

    await page.goto('/me');
    await assertNoViolations(page);
});
