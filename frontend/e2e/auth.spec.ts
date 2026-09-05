import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

test.describe('login', () => {
    test('credenciais erradas mostra mensagem de erro', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('E-mail').fill('nobody@example.com');
        await page.getByLabel('Senha').fill('wrong-password');
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page.getByText('Invalid credentials.')).toBeVisible();
    });
});

test.describe('registro', () => {
    test('cria conta seller e loga automaticamente', async ({ page }) => {
        const email = uniqueEmail('seller');

        await page.goto('/register');
        await page.getByLabel('Nome').fill('Ada Sellertest');
        await page.getByLabel('E-mail').fill(email);
        await page.getByLabel('Senha').fill('Sup3rSecret!');
        // Select já vem "seller" como default -- não precisa trocar.
        await page.getByRole('button', { name: 'Criar conta' }).click();

        await expect(page).toHaveURL(/\/me$/);
        await expect(page.getByText('Vendedor', { exact: true })).toBeVisible();
    });

    test('cria conta customer e loga automaticamente', async ({ page }) => {
        const email = uniqueEmail('customer');

        await page.goto('/register');
        await page.getByLabel('Nome').fill('Bea Customertest');
        await page.getByLabel('E-mail').fill(email);
        await page.getByLabel('Senha').fill('Sup3rSecret!');
        await page.getByLabel('Tipo de conta').click();
        await page.getByRole('option', { name: 'Cliente' }).click();
        await page.getByRole('button', { name: 'Criar conta' }).click();

        await expect(page).toHaveURL(/\/me$/);
        await expect(page.getByText('Cliente', { exact: true })).toBeVisible();
    });

    test('e-mail duplicado mostra erro', async ({ page }) => {
        const email = uniqueEmail('dup');

        await page.goto('/register');
        await page.getByLabel('Nome').fill('Primeira Conta');
        await page.getByLabel('E-mail').fill(email);
        await page.getByLabel('Senha').fill('Sup3rSecret!');
        await page.getByRole('button', { name: 'Criar conta' }).click();
        await expect(page).toHaveURL(/\/me$/);

        // Segunda tentativa com o mesmo e-mail, a partir de uma sessão nova (sem cookie).
        await page.context().clearCookies();
        await page.goto('/register');
        await page.getByLabel('Nome').fill('Segunda Conta');
        await page.getByLabel('E-mail').fill(email);
        await page.getByLabel('Senha').fill('OutraSenha!23');
        await page.getByRole('button', { name: 'Criar conta' }).click();

        await expect(page.getByRole('alert')).toBeVisible();
        await expect(page).toHaveURL(/\/register$/);
    });
});

test('logout limpa a sessão e redireciona pro login', async ({ page }) => {
    const email = uniqueEmail('logout');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Logout Teste');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    // Sem sessão, /me deve redirecionar de volta pro login.
    await page.goto('/me');
    await expect(page).toHaveURL(/\/login$/);
});
