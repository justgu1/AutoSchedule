import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

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
