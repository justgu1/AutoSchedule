import { expect, test, type Page } from '@playwright/test';

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

/** UF virou Autocomplete -- digitar não basta, precisa selecionar a opção pra o form realmente atualizar. */
async function selectState(page: Page, code: string): Promise<void> {
    await page.getByLabel('UF').fill(code);
    await page.getByRole('option', { name: new RegExp(`^${code} --`) }).click();
}

/** Registra um seller e devolve o próprio id (via a resposta de `GET /me` disparada ao chegar em `/me`) -- necessário pra montar `owner_user_id` no fluxo de admin abaixo. */
async function registerSellerAndGetId(page: Page, name: string, phone?: string): Promise<string> {
    const email = uniqueEmail('dealershipowner');

    await page.goto('/register');
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Vendedor' }).click();

    const mePromise = page.waitForResponse((response) => response.url().includes('/api/me') && response.ok());
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);
    const body = (await (await mePromise).json()) as { data: { id: string; phone: string | null } };

    if (phone) {
        // Perfil não tem edição de telefone na UI ainda -- ajusta direto pela API pra exercitar "Usar o meu"
        // no form de concessionária. `page.request` não passa pelo `apiClient.ts`, então o header CSRF
        // precisa ser montado na mão a partir do cookie `XSRF-TOKEN` que a própria sessão já tem.
        const csrfCookie = (await page.context().cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
        await page.request.patch('/api/me', {
            data: { phone },
            headers: csrfCookie ? { 'X-CSRF-Token': decodeURIComponent(csrfCookie.value) } : {},
        });
    }

    return body.data.id;
}

/**
 * Lista paginada e sem filtro por nome -- procura clicando "próxima página"
 * até achar ou acabarem as páginas. `toBeVisible` (com timeout curto, não
 * `isVisible` cru) importa aqui: clicar "próxima" já desabilita o botão da
 * MESMA vez que a página muda de número, antes do refetch daquela página
 * terminar -- sem esperar, a última página sempre parece "não achou".
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

// 1x1 PNG válido -- não precisa de um arquivo de fixture no repo, `setInputFiles` aceita o buffer direto.
const TINY_PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
);

async function registerSeller(page: Page, name: string): Promise<void> {
    const email = uniqueEmail('dealership');

    await page.goto('/register');
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Vendedor' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);
}

test('seller cria, edita, envia foto, move pra lixeira e restaura a própria concessionária', async ({ page }) => {
    await registerSeller(page, 'Seller Dealershiptest');

    await page.getByRole('link', { name: 'Concessionárias' }).click();
    await expect(page).toHaveURL(/\/dealerships$/);
    await expect(page.getByText('Nenhuma concessionária ainda')).toBeVisible();

    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    await page.getByLabel('Nome').fill('Auto Center E2E');
    await page.getByLabel('CEP').fill('01000-000');
    await page.getByLabel('Endereço').fill('Rua de Teste');
    // Número só aceita dígito -- letra digitada é descartada, não vai pro valor.
    await page.getByLabel('Número').fill('10a0b');
    await expect(page.getByLabel('Número')).toHaveValue('100');
    await page.getByLabel('Bairro').fill('Centro');
    await page.getByLabel('Cidade').fill('São Paulo');
    await selectState(page, 'SP');
    await page.getByRole('button', { name: 'Criar' }).click();

    await expect(page.getByRole('cell', { name: 'Auto Center E2E' })).toBeVisible();
    await expect(page.getByText('São Paulo/SP')).toBeVisible();

    // Editar -- reabre o mesmo form pré-preenchido, muda só o nome.
    await page
        .getByRole('row', { name: /Auto Center E2E/ })
        .getByRole('button', { name: 'Editar' })
        .click();
    await expect(page.getByLabel('Cidade')).toHaveValue('São Paulo');
    await page.getByLabel('Nome').fill('Auto Center Renomeado');
    await page.getByRole('button', { name: 'Salvar' }).click();
    await expect(page.getByRole('cell', { name: 'Auto Center Renomeado' })).toBeVisible();

    // Foto -- só uma, processada de forma assíncrona (job + SSE), acompanhada até "done".
    await page
        .getByRole('row', { name: /Auto Center Renomeado/ })
        .getByRole('button', { name: 'Foto' })
        .click();
    await expect(page.getByText('Nenhuma foto ainda.')).toBeVisible();
    await page
        .locator('input[type="file"]')
        .setInputFiles({ name: 'foto.png', mimeType: 'image/png', buffer: TINY_PNG });
    await expect(page.getByText('Concluído.')).toBeVisible({ timeout: 15_000 });
    await expect(page.getByRole('button', { name: 'Remover' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Trocar foto' })).toBeVisible();
    await page.getByRole('button', { name: 'Fechar' }).click();

    // Lixeira -- some da lista? não, seller ainda enxerga o próprio status trashed. Restaura em seguida.
    const row = page.getByRole('row', { name: /Auto Center Renomeado/ });
    await row.getByRole('button', { name: 'Mover pra lixeira' }).click();
    await page.getByRole('button', { name: 'Mover pra lixeira', exact: true }).click();
    await expect(row.getByText('Na lixeira')).toBeVisible();

    await row.getByRole('button', { name: 'Restaurar' }).click();
    await expect(page.getByText('Concessionária restaurada.')).toBeVisible();
    await expect(row.getByText('Ativa')).toBeVisible();
});

test('seller usa o próprio telefone no formulário da concessionária', async ({ page }) => {
    await registerSellerAndGetId(page, 'Seller Phonetest', '11955554444');

    await page.goto('/dealerships');
    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    await page.getByRole('button', { name: 'Usar o meu' }).click();

    await expect(page.getByLabel('Telefone')).toHaveValue('11955554444');
});

test('admin cria concessionária pra um seller e reassocia o dono pra outro', async ({ page }) => {
    const ownerAId = await registerSellerAndGetId(page, 'Dono Original');
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    const ownerBId = await registerSellerAndGetId(page, 'Dono Novo');
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.getByLabel('E-mail').fill('admin@autoschedule.local');
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await page.getByRole('link', { name: 'Concessionárias' }).click();
    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    const dealershipName = `Admin Reassoc ${Date.now()}`;
    await page.getByLabel(/Dono/).fill(ownerAId);
    await page.getByLabel('Nome').fill(dealershipName);
    await page.getByLabel('CEP').fill('01000-000');
    await page.getByLabel('Endereço').fill('Rua Admin');
    await page.getByLabel('Número').fill('1');
    await page.getByLabel('Bairro').fill('Centro');
    await page.getByLabel('Cidade').fill('Rio de Janeiro');
    await selectState(page, 'RJ');
    await page.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('dialog')).not.toBeVisible();

    await findRowAcrossPages(page, dealershipName);
    await page
        .getByRole('row', { name: new RegExp(dealershipName) })
        .getByRole('button', { name: 'Editar' })
        .click();
    await expect(page.getByLabel(/Dono/)).toHaveValue(ownerAId);
    await page.getByLabel(/Dono/).fill(ownerBId);
    await page.getByRole('button', { name: 'Salvar' }).click();
    await expect(page.getByRole('dialog')).not.toBeVisible();

    await findRowAcrossPages(page, dealershipName);
    await page
        .getByRole('row', { name: new RegExp(dealershipName) })
        .getByRole('button', { name: 'Editar' })
        .click();
    await expect(page.getByLabel(/Dono/)).toHaveValue(ownerBId);
});

test('customer não vê o link de concessionárias e é redirecionado se acessar a rota direto', async ({ page }) => {
    const email = uniqueEmail('dealershipcustomer');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Customer Dealershiptest');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Cliente' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await expect(page.getByRole('link', { name: 'Concessionárias' })).not.toBeVisible();

    await page.goto('/dealerships');
    await expect(page).toHaveURL(/\/me$/);
});
