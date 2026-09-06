import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

test('desativar a conta -> logar de novo -> conta restaurada', async ({ page }) => {
    const email = uniqueEmail('trash');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Conta Trashtest');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Cliente' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    page.once('dialog', (dialog) => void dialog.accept());
    await page.getByRole('button', { name: 'Desativar minha conta' }).click();
    await expect(page).toHaveURL(/\/login$/);

    // Conta na lixeira -- logar de novo com as mesmas credenciais restaura sozinho, sem passo extra.
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);
    await expect(page.getByText('Conta Trashtest').first()).toBeVisible();
});
