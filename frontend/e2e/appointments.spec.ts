import { expect, test, type Locator, type Page } from '@playwright/test';
import { waitForAppointmentConfirmationLink } from './support/mailpit';
import { openMenuIfCollapsed, searchOnHome } from './support/ui';

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
    const email = uniqueEmail('appointment');

    await page.goto('/register');
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Vendedor' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);
}

async function createDealership(page: Page, name: string): Promise<void> {
    await page.goto('/dealerships');
    await page.getByRole('button', { name: 'Nova concessionária' }).click();
    await page.getByLabel('Nome').fill(name);
    await page.getByLabel('CEP').fill('01000-000');
    await page.getByLabel('Número').click();
    await expect(page.getByLabel('Endereço')).toHaveValue('Rua de Teste');
    await page.getByLabel('Número').fill('10');
    await page.getByRole('button', { name: 'Criar' }).click();
    await expect(page.getByRole('group', { name })).toBeVisible();
}

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

/** `TimePicker` (MUI X) é um input seccionado, não um `<input>` simples -- clicar foca a 1ª seção, e digitar avança sozinho. */
async function fillTimeField(scope: Locator, label: string, digits: string): Promise<void> {
    await scope.getByRole('group', { name: label, exact: true }).click();
    await scope.page().keyboard.type(digits);
}

/** Janela aberta o dia inteiro, no weekday de hoje -- garante slot livre não importa a hora que o teste rode. */
async function openAvailabilityAllDayToday(page: Page): Promise<void> {
    const weekdayNames = [
        'Domingo',
        'Segunda-feira',
        'Terça-feira',
        'Quarta-feira',
        'Quinta-feira',
        'Sexta-feira',
        'Sábado',
    ];
    const today = weekdayNames[new Date().getDay()];

    const dialog = page.getByRole('dialog');
    await dialog.getByRole('button', { name: today, exact: true }).click();
    await fillTimeField(dialog, 'Início', '0000');
    await fillTimeField(dialog, 'Fim', '2359');
    await dialog.getByRole('button', { name: 'Adicionar', exact: true }).click();
    await expect(dialog.getByText(`${today}, 00:00 às 23:59`)).toBeVisible();
}

async function setUpBookableVehicle(page: Page, dealershipName: string, brand: string, model: string): Promise<void> {
    await createDealership(page, dealershipName);
    await page.getByRole('group', { name: dealershipName }).getByRole('button', { name: 'Disponibilidade' }).click();
    await openAvailabilityAllDayToday(page);
    await page.getByRole('button', { name: 'Fechar' }).click();

    await page.goto('/vehicles');
    await createVehicle(page, dealershipName, { brand, model, price: '89900.00' });
    await expect(page.getByRole('group', { name: `${brand} ${model}` })).toBeVisible();
    await page
        .getByRole('group', { name: `${brand} ${model}` })
        .getByRole('button', { name: 'Disponibilidade' })
        .click();
    await openAvailabilityAllDayToday(page);
    await page.getByRole('button', { name: 'Fechar' }).click();
}

/** Carrossel de data/horário é inline na página do veículo, não um modal -- sem `dialog` pra escopar. */
/** O calendário abre sempre no mês corrente -- o número do dia não tem ambiguidade nesse momento. */
async function selectFirstAvailableSlot(page: Page): Promise<string> {
    const today = String(new Date().getDate());
    await page.getByRole('gridcell', { name: today, exact: true }).click();

    const timeOption = page.getByRole('listbox', { name: 'Selecione um horário' }).getByRole('option').first();
    await expect(timeOption).toBeVisible({ timeout: 10_000 });
    const slotTime = await timeOption.textContent();
    await timeOption.click();

    return slotTime!;
}

test('seller cadastra disponibilidade e cliente agenda pelo site público', async ({ page }) => {
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Appointment');
    const dealershipName = `Auto Center Appt ${Date.now()}`;
    const brand = `Fiat${Date.now()}`;
    await setUpBookableVehicle(page, dealershipName, brand, 'Argo');

    await openMenuIfCollapsed(page);
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/');
    await searchOnHome(page, brand);
    await page.getByRole('heading', { name: `${brand} Argo` }).click();
    await expect(page).toHaveURL(/\/veiculos\//);

    await selectFirstAvailableSlot(page);
    await page.getByLabel('Nome').fill('Ada Lovelace');
    const customerEmail = uniqueEmail('customer');
    await page.getByLabel('E-mail').fill(customerEmail);
    await page.getByLabel('Telefone').fill('11999990000');

    await page.getByRole('button', { name: 'Confirmar agendamento' }).click();
    await expect(page.getByText('Agendamento concluído!')).toBeVisible();
});

test('reserva concorrente do mesmo horário vira 409', async ({ page }) => {
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Conflict');
    const dealershipName = `Conflict Center ${Date.now()}`;
    const brand = `Chevrolet${Date.now()}`;
    await setUpBookableVehicle(page, dealershipName, brand, 'Onix');
    await openMenuIfCollapsed(page);
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto('/');
    await searchOnHome(page, brand);
    await page.getByRole('heading', { name: `${brand} Onix` }).click();

    const slotTime = await selectFirstAvailableSlot(page);
    await page.getByLabel('Nome').fill('Grace Hopper');
    await page.getByLabel('E-mail').fill(uniqueEmail('first'));
    await page.getByLabel('Telefone').fill('11888880000');
    await page.getByRole('button', { name: 'Confirmar agendamento' }).click();
    await expect(page.getByText('Agendamento concluído!')).toBeVisible();

    // Mesmo vehicle_id/scheduled_at direto pela API -- reproduz a corrida sem depender de UI-timing.
    const vehicleUrl = new URL(page.url());
    const vehicleId = vehicleUrl.pathname.split('/').pop();
    const [hours, minutes] = slotTime.split(':');
    const scheduledAt = new Date();
    scheduledAt.setHours(Number(hours), Number(minutes), 0, 0);

    const conflictResponse = await page.request.post('/api/appointments', {
        data: {
            vehicle_id: vehicleId,
            scheduled_at: scheduledAt.toISOString(),
            customer_name: 'Ada Lovelace',
            customer_email: uniqueEmail('second'),
            customer_phone: '11999990000',
        },
    });

    expect(conflictResponse.status()).toBe(409);
});

/**
 * Regressão: `POST .../confirm` e `.../cancel` no painel não mandam corpo nenhum (a diferença
 * do fluxo do cliente, que manda `{token}`) -- um corpo vazio já quebrou isso uma vez (`Request::json()`
 * lançava em vez de virar `null`).
 */
test('seller confirma agendamento pendente direto pelo painel, sem token', async ({ page }) => {
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Override');
    const dealershipName = `Override Center ${Date.now()}`;
    const brand = `Renault${Date.now()}`;
    await setUpBookableVehicle(page, dealershipName, brand, 'Kwid');

    await openMenuIfCollapsed(page);
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/');
    await searchOnHome(page, brand);
    await page.getByRole('heading', { name: `${brand} Kwid` }).click();

    await selectFirstAvailableSlot(page);
    await page.getByLabel('Nome').fill('Ada Lovelace');
    await page.getByLabel('E-mail').fill(uniqueEmail('override-confirm'));
    await page.getByLabel('Telefone').fill('11999990000');
    await page.getByRole('button', { name: 'Confirmar agendamento' }).click();
    await expect(page.getByText('Agendamento concluído!')).toBeVisible();

    await page.goto('/login');
    await page.getByLabel('E-mail').fill('admin@autoschedule.local');
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);
    await page.goto('/appointments');

    const row = page.getByRole('row', { name: new RegExp(brand) });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Confirmar' }).click();
    await expect(row.getByText('Confirmado')).toBeVisible();
});

test('customer não vê o link de agendamentos e é redirecionado se acessar a rota direto', async ({ page }) => {
    const email = uniqueEmail('appointmentcustomer');

    await page.goto('/register');
    await page.getByLabel('Nome').fill('Customer Appointmenttest');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill('Sup3rSecret!');
    await page.getByLabel('Tipo de conta').click();
    await page.getByRole('option', { name: 'Cliente' }).click();
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/me$/);

    await expect(page.getByRole('link', { name: 'Agendamentos' })).not.toBeVisible();

    await page.goto('/appointments');
    await expect(page).toHaveURL(/\/me$/);
});

test('ciclo completo: e-mail de confirmação, token, retirada e devolução', async ({ page }) => {
    // Depende da rotina agendada (intervalo de até 60s) pra disparar o e-mail de confirmação.
    test.setTimeout(120_000);
    await mockZipCodeLookup(page);
    await registerSeller(page, 'Seller Fullcycle');
    const dealershipName = `Fullcycle Center ${Date.now()}`;
    const brand = `Toyota${Date.now()}`;
    await setUpBookableVehicle(page, dealershipName, brand, 'Corolla');
    await openMenuIfCollapsed(page);
    await page.getByRole('button', { name: 'Sair' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto('/');
    await searchOnHome(page, brand);
    await page.getByRole('heading', { name: `${brand} Corolla` }).click();

    await selectFirstAvailableSlot(page);
    const customerEmail = uniqueEmail('fullcycle');
    await page.getByLabel('Nome').fill('Ada Lovelace');
    await page.getByLabel('E-mail').fill(customerEmail);
    await page.getByLabel('Telefone').fill('11999990000');
    await page.getByRole('button', { name: 'Confirmar agendamento' }).click();
    await expect(page.getByText('Agendamento concluído!')).toBeVisible();

    const confirmationLink = await waitForAppointmentConfirmationLink(customerEmail);
    const confirmationPath = new URL(confirmationLink).pathname + new URL(confirmationLink).search;

    await page.goto(confirmationPath);
    await page.getByRole('button', { name: 'Confirmar teste-drive' }).click();
    await expect(page.getByText('Teste-drive confirmado!')).toBeVisible();

    // Volta como vendedor pra marcar retirada e devolução.
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('admin@autoschedule.local');
    await page.getByLabel('Senha').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/me$/);
    await page.goto('/appointments');
    const row = page.getByRole('row', { name: new RegExp(customerEmail) });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Marcar retirada' }).click();
    await expect(row.getByRole('button', { name: 'Marcar devolução' })).toBeVisible();
    await row.getByRole('button', { name: 'Marcar devolução' }).click();
    await expect(row.getByText('Concluído')).toBeVisible();
});
