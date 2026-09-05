import { defineConfig, devices } from '@playwright/test';

/**
 * `baseURL` aponta pro nginx (build real, `npm run build` já rodado na
 * imagem) -- é o que representa produção, não o dev server do Vite.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    // Poucos workers de propósito: cada registro/login passa por Argon2id no
    // backend (lento por design, contra brute-force) -- workers demais disputam
    // o mesmo PHP-FPM e estouram timeout sem ser um bug real, só fila.
    workers: 4,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    expect: { timeout: 10_000 },
    use: {
        baseURL: process.env.BASE_URL ?? 'http://localhost:8085',
        trace: 'on-first-retry',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        // Mesmos specs, viewport mobile -- pega quebra de layout/scroll horizontal que só aparece em tela pequena.
        { name: 'mobile', use: { ...devices['iPhone 13'] } },
    ],
});
