import { expect, test, type Page } from '@playwright/test';
import { searchOnHome } from './support/ui';

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

/**
 * Lista paginada e sem filtro por nome -- procura clicando "próxima página" até achar
 * ou acabarem as páginas. Mesmo helper de `dealerships.spec.ts`.
 */
async function findRowAcrossPages(page: Page, cellText: string) {
    for (;;) {
        try {
            await expect(page.getByRole('cell', { name: cellText })).toBeVisible({ timeout: 2000 });

            return;
        } catch {
            // ainda não apareceu nessa página -- tenta a próxima.
        }

        const nextPage = page.getByRole('button', { name: 'Go to next page' });

        if (!(await nextPage.isEnabled().catch(() => false))) {
            throw new Error(`Linha "${cellText}" não encontrada em nenhuma página.`);
        }

        await nextPage.click();
    }
}

async function registerSellerWithVehicle(
    page: Page,
    dealershipName: string,
    brand: string,
    model: string,
): Promise<void> {
    const email = uniqueEmail('publicsite');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Seller Publicsitetest');
    await page.getByLabel('E-mail').fill(email);
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
    // Filtro empilhado da listagem e o form do dialog compartilham rótulo (`Marca`/`Modelo`) -- escopar evita ambiguidade.
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Concessionária').fill(dealershipName);
    await page.getByRole('option', { name: dealershipName }).click();
    await dialog.getByLabel('Marca').fill(brand);
    await dialog.getByLabel(/^Modelo/).fill(model);
    await dialog.getByLabel('Preço').fill('89900.00');
    await dialog.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('group', { name: `${brand} ${model}` })).toBeVisible();

    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);
}

test('index lista o catálogo público e o card abre a página do veículo, sem exigir conta', async ({ page }) => {
    await mockZipCodeLookup(page);
    const brand = `Fiat${Date.now()}`;
    await registerSellerWithVehicle(page, `Index Center ${Date.now()}`, brand, 'Argo');

    await page.goto('/');
    await expect(page.getByRole('heading', { name: /veículos/ })).toBeVisible();
    await searchOnHome(page, brand);
    await expect(page.getByRole('heading', { name: `${brand} Argo` })).toBeVisible();

    await page.getByRole('heading', { name: `${brand} Argo` }).click();
    await expect(page).toHaveURL(/\/veiculos\//);
    await expect(page.getByRole('heading', { name: `${brand} Argo` })).toBeVisible();
    await expect(page.getByText('R$ 89.900,00')).toBeVisible();
});

test('header oferece entrar e criar conta pra quem não tem sessão, e painel pra quem está logado', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Entrar', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Criar conta' })).toBeVisible();

    await page.getByRole('link', { name: 'Entrar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('E-mail').fill('admin@autoschedule.local');
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Entrar no painel' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Entrar', exact: true })).not.toBeVisible();
});

test('vitrine da página pública da concessionária mostra o veículo cadastrado', async ({ page }) => {
    await mockZipCodeLookup(page);
    const dealershipName = `Vitrine Center ${Date.now()}`;
    const brand = `Chevrolet${Date.now()}`;
    await registerSellerWithVehicle(page, dealershipName, brand, 'Onix');

    // Login de novo só pra pegar o link da página pública -- a fixture já deslogou no fim de `registerSellerWithVehicle`.
    await page.getByLabel('E-mail').fill('admin@autoschedule.local');
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);
    await page.goto('/dealerships');
    await findRowAcrossPages(page, dealershipName);
    const publicUrl = await page
        .getByRole('row', { name: new RegExp(dealershipName) })
        .getByRole('link', { name: 'Ver página pública' })
        .getAttribute('href');
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto(publicUrl!);
    await expect(page.getByRole('heading', { name: dealershipName })).toBeVisible();
    await expect(page.getByText(`${brand} Onix`)).toBeVisible();
    await expect(page.getByText('Em breve -- nenhum veículo cadastrado ainda.')).not.toBeVisible();
});
