import { expect, test } from '@playwright/test';

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

test('customer pode virar seller pelo próprio perfil', async ({ page }) => {
    const email = uniqueEmail('becomeseller');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Cara Upgradetest');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Cliente' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await expect(page.getByText('Cliente', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Tornar-se vendedor' }).click();

    await expect(page.getByText('Vendedor', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tornar-se vendedor' })).not.toBeVisible();
});
