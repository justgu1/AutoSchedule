import { expect, test, type Page } from '@playwright/test';
import { openFiltersIfCollapsed } from './support/ui';

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

async function registerSeller(page: Page, name: string): Promise<void> {
    const email = uniqueEmail('vehicle');

    await page.goto('/register');
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Vendedor' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);
}

/** Veículo precisa de uma concessionária dona -- monta uma antes de cada teste do painel. */
async function createDealership(page: Page, name: string): Promise<void> {
    await page.goto('/dealerships');
    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('CEP').fill('01000-000');
    await page.getByLabel('Número').click();
    await expect(page.getByLabel('Endereço')).toHaveValue('Rua de Teste');
    await page.getByLabel('Número').fill('10');
    await page.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('cell', { name })).toBeVisible();
}

/**
 * O filtro empilhado da listagem e o form do dialog têm campos com o mesmo rótulo
 * (`Marca`/`Modelo`) -- MUI não esconde o fundo do `aria-hidden` enquanto o dialog está
 * aberto, então `getByLabel` sem escopo bate nos dois. Escopar em `role=dialog` resolve.
 */
async function createVehicle(
    page: Page,
    dealershipName: string,
    fields: { brand: string; model: string; price: string },
): Promise<void> {
    await page.getByRole('button', { name: 'Novo veículo' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Concessionária').fill(dealershipName);
    await page.getByRole('option', { name: dealershipName }).click();
    await dialog.getByLabel('Marca').fill(fields.brand);
    await dialog.getByLabel(/^Modelo/).fill(fields.model);
    await dialog.getByLabel('Preço').fill(fields.price);
    await dialog.getByRole('button', { name: 'Criar' }).click();
}

// 1x1 PNG válido -- não precisa de um arquivo de fixture no repo, `setInputFiles` aceita o buffer direto.
const TINY_PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
);

test('seller cadastra veículo, edita, envia fotos da galeria e move pra lixeira', async ({ page }) => {
    // Fotos passam pela fila real (worker + Redis + GD) -- folga sobre os 30s padrão pro CI, mais lento que local.
    test.setTimeout(60_000);
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Vehicletest');
    await createDealership(page, 'Auto Center Vehicle E2E');

    await page.getByRole('link', { name: 'Veículos' }).click();
    await expect(page).toHaveURL(/\/vehicles$/);
    await expect(page.getByText('Nenhum veículo encontrado')).toBeVisible();

    await createVehicle(page, 'Auto Center Vehicle E2E', { brand: 'Chevrolet', model: 'Onix', price: '89900.00' });
    const card = page.getByRole('group', { name: 'Chevrolet Onix' });
    await expect(card).toBeVisible();

    // Editar -- clicar no corpo do card (não é mais um botão "Editar" à parte) reabre o form pré-preenchido.
    await card.getByRole('button', { name: 'Editar Chevrolet Onix' }).click();
    const editDialog = page.getByRole('dialog');
    await expect(editDialog.getByLabel('Marca')).toHaveValue('Chevrolet');
    await editDialog.getByLabel('Preço').fill('79900.00');
    await editDialog.getByRole('button', { name: 'Salvar' }).click();
    await expect(page.getByText('R$ 79.900,00')).toBeVisible();

    // Galeria -- lote de fotos processado de forma assíncrona (job + SSE), acompanhado até "done".
    await card.getByRole('button', { name: 'Galeria' }).click();
    await expect(page.getByText('Nenhuma foto ainda.')).toBeVisible();
    await page
        .locator('input[type="file"]')
        .setInputFiles([{ name: 'foto1.png', mimeType: 'image/png', buffer: TINY_PNG }]);
    await expect(page.getByText('Concluído.')).toBeVisible({ timeout: 30_000 });
    await page.getByRole('button', { name: 'Fechar' }).click();

    // Lixeira -- some da lista? não, seller ainda enxerga o próprio status trashed.
    await card.getByRole('button', { name: 'Mover pra lixeira' }).click();
    await page.getByRole('button', { name: 'Mover pra lixeira', exact: true }).click();
    await expect(card.getByText('Na lixeira')).toBeVisible();

    await card.getByRole('button', { name: 'Restaurar' }).click();
    await expect(page.getByText('Veículo restaurado.')).toBeVisible();
    await expect(card.getByText('Ativo')).toBeVisible();
});

test('filtro de marca encontra o veículo pelo painel', async ({ page }) => {
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Vehiclefiltertest');
    await createDealership(page, 'Filter Center E2E');

    await page.goto('/vehicles');
    await createVehicle(page, 'Filter Center E2E', { brand: 'Toyota', model: 'Corolla', price: '145000.00' });
    await expect(page.getByRole('group', { name: 'Toyota Corolla' })).toBeVisible();

    await openFiltersIfCollapsed(page);
    await page.getByLabel('Buscar').fill('corola');
    await expect(page.getByRole('group', { name: 'Toyota Corolla' })).toBeVisible();
});

test('customer não vê o link de veículos e é redirecionado se acessar a rota direto', async ({ page }) => {
    const email = uniqueEmail('vehiclecustomer');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Customer Vehicletest');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Cliente' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await expect(page.getByRole('link', { name: 'Veículos' })).not.toBeVisible();

    await page.goto('/vehicles');
    await expect(page).toHaveURL(/\/me$/);
});
