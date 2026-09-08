import type { Page } from '@playwright/test';

/**
 * `FilterSidebar` fica direto na tela no desktop, mas escondida atrás do botão "Filtros"
 * (drawer) no projeto `mobile` -- sem isso, os campos do filtro não são encontrados nesse projeto.
 */
export async function openFiltersIfCollapsed(page: Page): Promise<void> {
    const filtersButton = page.getByRole('button', { name: 'Filtros' });

    if (await filtersButton.isVisible()) {
        await filtersButton.click();
    }
}

export async function searchOnHome(page: Page, term: string): Promise<void> {
    await openFiltersIfCollapsed(page);
    await page.getByLabel('Buscar').fill(term);
}
