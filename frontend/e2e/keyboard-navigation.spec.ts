import { expect, test } from '@playwright/test';

/**
 * WCAG 2.1.1 (Keyboard) e 2.4.3 (Focus Order) -- nenhum dos dois é estático,
 * então o axe-core (scan de DOM) não pega isso; só simulando teclado de
 * verdade. Indicador visual de foco (2.4.7, AA) fica de fora aqui -- é visual,
 * não tem asserção automatizada confiável sem screenshot-diff; permanece
 * revisão manual.
 */
test('login é operável só com teclado, sem mouse', async ({ page }) => {
    await page.goto('/login');

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('E-mail')).toBeFocused();
    await page.keyboard.type('nobody@example.com');

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Senha')).toBeFocused();
    await page.keyboard.type('wrong-password');

    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Entrar' })).toBeFocused();

    // Enter no botão focado submete o form -- nenhum clique de mouse em lugar nenhum.
    await page.keyboard.press('Enter');
    await expect(page.getByText('Invalid credentials.')).toBeVisible();
});

test('registro é operável só com teclado até o campo de role', async ({ page }) => {
    await page.goto('/register');

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Nome')).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('E-mail')).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Telefone')).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Senha')).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Tipo de conta')).toBeFocused();
});
