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

/**
 * No projeto `mobile` o drawer de filtro é modal -- some o resultado atrás dele. Sem fechar
 * de volta (botão "Ver resultados", só existe nesse projeto), o card filtrado nunca aparece.
 */
export async function closeFiltersIfOpen(page: Page): Promise<void> {
    const applyButton = page.getByRole('button', { name: 'Ver resultados' });

    if (await applyButton.isVisible()) {
        await applyButton.click();
    }
}

export async function searchOnHome(page: Page, term: string): Promise<void> {
    await openFiltersIfCollapsed(page);
    await page.getByLabel('Buscar').fill(term);
    await closeFiltersIfOpen(page);
}

/**
 * A navegação de `AuthenticatedLayout` fica direto no AppBar no desktop, mas escondida atrás do
 * botão "Abrir menu" (drawer) no projeto `mobile` -- sem isso, os links não são encontrados nesse projeto.
 */
export async function openMenuIfCollapsed(page: Page): Promise<void> {
    const menuButton = page.getByRole('button', { name: 'Abrir menu' });

    if (await menuButton.isVisible()) {
        await menuButton.click();
    }
}
