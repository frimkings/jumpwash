import { chromium, expect } from '@playwright/test';

const baseUrl = process.env.APP_URL ?? 'http://127.0.0.1:8011';
const chromePath = process.env.CHROME_PATH ?? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const packageName = `Browser Subscription ${Date.now()}`;
const browserErrors = [];

const browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
});

const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
});

const page = await context.newPage();

page.on('pageerror', (error) => browserErrors.push(error.message));
page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type())) {
        browserErrors.push(`${message.type()}: ${message.text()}`);
    }
});

async function waitForLivewire() {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(350);
}

async function selectFirstService() {
    const serviceSelect = page.locator('form.module-panel label.field:has-text("Service") select');
    await expect(serviceSelect).toBeVisible({ timeout: 10000 });
    const serviceValue = await serviceSelect.evaluate((node) => {
        const option = Array.from(node.options).find((item) => item.value !== '');
        return option?.value ?? '';
    });

    if (! serviceValue) {
        throw new Error('No active service option is available for subscription package creation');
    }

    await serviceSelect.selectOption(serviceValue);
}

await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
await page.locator('#email').fill('superadmin@jumpwash.test');
await page.locator('#password').fill('password');
await page.getByRole('button', { name: 'Sign in' }).click();
await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible({ timeout: 15000 });

await page.goto(`${baseUrl}/subscriptions`, { waitUntil: 'domcontentloaded' });
await expect(page.getByRole('heading', { name: 'Subscription Packages' }).first()).toBeVisible({ timeout: 15000 });

await expect(page.locator('form.module-panel label.field:has-text("Usage Limit") input')).toHaveAttribute('min', '1');
await expect(page.locator('form.module-panel label.field:has-text("Amount") input')).toHaveAttribute('min', '0.01');

await page.locator('form.module-panel input[placeholder="Silver"]').fill('Browser Invalid Package');
await page.locator('form.module-panel label.field:has-text("Usage Limit") input').fill('1');
await page.locator('form.module-panel label.field:has-text("Amount") input').fill('1');
await page.getByRole('button', { name: 'Save Package' }).click();
await expect(page.getByText(/service field is required|required/i).first()).toBeVisible({ timeout: 15000 });

await page.locator('form.module-panel input[placeholder="Silver"]').fill(packageName);
await selectFirstService();
await page.locator('form.module-panel label.field:has-text("Validity Months") input').fill('2');
await page.locator('form.module-panel label.field:has-text("Usage Limit") input').fill('8');
await page.locator('form.module-panel label.field:has-text("Amount") input').fill('199.99');
await page.getByRole('button', { name: 'Save Package' }).click();
await expect(page.getByText('Subscription package saved.')).toBeVisible({ timeout: 15000 });
await waitForLivewire();
await expect(page.locator('.package-row').filter({ hasText: packageName })).toBeVisible({ timeout: 10000 });
await expect(page.locator('.package-row').filter({ hasText: packageName })).toContainText('GHS 199.99');

await context.close();
await browser.close();

console.log(JSON.stringify({
    packageName,
    browserErrorCount: browserErrors.length,
    browserErrors,
}, null, 2));

if (browserErrors.length > 0) {
    process.exitCode = 1;
}
