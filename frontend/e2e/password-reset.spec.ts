import { expect, test } from '@playwright/test';
import { waitForResetLink } from './support/mailpit';

function uniqueEmail(prefix: string): string {
    return `${prefix}.${Date.now()}.${Math.random().toString(36).slice(2)}@example.com`;
}

test('esqueci a senha -> e-mail real via Mailpit -> redefinir -> login com a senha nova', async ({ page, context }) => {
    const email = uniqueEmail('reset');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Reset Teste');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('SenhaAntiga!1');
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    // Sessão limpa -- esqueci-senha não depende de estar logado.
    await context.clearCookies();

    await page.goto('/forgot-password');
    await page.getByLabel('E-mail').fill(email);
    await page.getByRole('button', { name: 'Enviar link' }).click();
    await expect(page.getByRole('alert')).toContainText('Se o e-mail existir');

    const resetLink = await waitForResetLink(email);
    const resetPath = new URL(resetLink).pathname + new URL(resetLink).search;

    await page.goto(resetPath);
    await page.getByLabel('Nova senha').fill('SenhaNova!2');
    await page.getByRole('button', { name: 'Redefinir senha' }).click();
    await expect(page.getByRole('alert')).toContainText('Senha redefinida');

    // Senha antiga não funciona mais.
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('SenhaAntiga!1');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page.getByText('Invalid credentials.')).toBeVisible();

    // Senha nova funciona.
    await page.getByLabel('Senha').fill('SenhaNova!2');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);
});
