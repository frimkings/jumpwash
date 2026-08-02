import { chromium, expect } from '@playwright/test';

const baseUrl = process.env.APP_URL ?? 'http://127.0.0.1:8011';
const chromePath = process.env.CHROME_PATH ?? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const results = [];
const browserErrors = [];
let createdOrderNumber = '';
let generatedTagCode = '';
let paymentOrderNumber = '';
let activePage;

async function step(name, fn) {
    try {
        await fn();
        results.push({ name, status: 'PASS' });
        console.log(`PASS ${name}`);
    } catch (error) {
        if (activePage) {
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            await activePage.screenshot({ path: `storage/app/${slug}.png`, fullPage: true }).catch(() => {});
            console.log(`SCREENSHOT storage/app/${slug}.png`);
            console.log(`URL ${activePage.url()}`);
            console.log((await activePage.locator('body').innerText().catch(() => '')).slice(0, 2000));
        }

        results.push({ name, status: 'FAIL', error: error.message });
        console.log(`FAIL ${name}: ${error.message}`);
    }
}

async function firstNonEmptyValue(select) {
    return await select.evaluate((node) => {
        const option = Array.from(node.options).find((item) => item.value !== '');
        return option?.value ?? '';
    });
}

async function selectFirstNonEmpty(select, label) {
    await expect(select, `${label} select should be visible`).toBeVisible({ timeout: 10000 });
    const value = await firstNonEmptyValue(select);

    if (! value) {
        throw new Error(`${label} select has no selectable options`);
    }

    await select.selectOption(value);
}

async function selectOptionContaining(select, text, label) {
    await expect(select, `${label} select should be visible`).toBeVisible({ timeout: 10000 });
    const value = await select.evaluate((node, search) => {
        const option = Array.from(node.options).find((item) => item.textContent.includes(search));
        return option?.value ?? '';
    }, text);

    if (! value) {
        throw new Error(`${label} select has no option containing "${text}"`);
    }

    await select.selectOption(value);
}

async function waitForLivewire(page) {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(350);
}

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill('superadmin@jumpwash.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible({ timeout: 15000 });
}

const browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
});

const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
});

const page = await context.newPage();
activePage = page;

page.on('pageerror', (error) => browserErrors.push(error.message));
page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type())) {
        browserErrors.push(`${message.type()}: ${message.text()}`);
    }
});

await step('Login as seeded superadmin', async () => {
    await login(page);
});

await step('Create order through Livewire form and render receipt preview', async () => {
    await page.goto(`${baseUrl}/orders`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Order Management' })).toBeVisible({ timeout: 15000 });

    await page.locator('input[placeholder*="Search customer"]').fill('customer1@jumpwash.test');
    await expect(page.locator('.customer-results button').first()).toBeVisible({ timeout: 10000 });
    await page.locator('.customer-results button').first().click();
    await waitForLivewire(page);

    await page.locator('label.field:has-text("Pickup Needed") select').selectOption('1');
    await page.locator('label.field:has-text("Delivery Needed") select').selectOption('1');
    await waitForLivewire(page);

    await page.locator('label.field:has-text("Pickup Date") input').fill('2026-07-01');
    await page.locator('label.field:has-text("Pickup Time") input').fill('09:30');
    await page.locator('label.field:has-text("Delivery Date") input').fill('2026-07-02');
    await page.locator('label.field:has-text("Delivery Time") input').fill('15:45');

    const orderRow = page.locator('.order-lines__row').first();
    await selectOptionContaining(orderRow.locator('select').nth(0), 'Shirt', 'Product');
    await waitForLivewire(page);
    await selectOptionContaining(orderRow.locator('select').nth(1), 'Laundry', 'Service');
    await waitForLivewire(page);
    await orderRow.locator('input[type="number"]').first().fill('2');
    await page.locator('textarea[wire\\:model="notes"]').fill('Browser QA order');

    await page.getByRole('button', { name: 'Save Order' }).click();
    await expect(page.getByText('Order created.')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#created-order-preview-title')).toBeVisible({ timeout: 15000 });
    createdOrderNumber = (await page.locator('#created-order-preview-title').innerText()).trim();

    if (! await page.locator('#created-order-preview-title').isVisible()) {
        throw new Error(`Order ${createdOrderNumber} was created, but the receipt preview modal did not render`);
    }

    await expect(page.locator('#created-order-receipt-frame')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Print Receipt' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Open Print Page' })).toBeVisible();
});

await step('Record payment and verify balance/payment history updates', async () => {
    await page.goto(`${baseUrl}/payments`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Payment Module' })).toBeVisible({ timeout: 15000 });

    const paymentIndex = await page.locator('.payment-order-button').evaluateAll((buttons) => {
        return buttons.findIndex((button) => {
            const text = button.textContent ?? '';
            const match = text.match(/Balance GHS ([0-9,]+\.\d{2})/);
            return text.includes('Unpaid') && match && Number(match[1].replace(/,/g, '')) > 0;
        });
    });

    if (paymentIndex < 0) {
        throw new Error('No unpaid order with a positive balance was available for payment testing');
    }

    const paymentButton = page.locator('.payment-order-button').nth(paymentIndex);
    paymentOrderNumber = ((await paymentButton.locator('h3').innerText()).trim());
    await paymentButton.click();
    await expect(page.locator('.selected-payment-order h3')).toHaveText(paymentOrderNumber, { timeout: 10000 });
    const amountInput = page.locator('input[wire\\:model="amount"]');
    await amountInput.fill('1.23');
    await amountInput.blur();
    await expect(amountInput).toHaveValue('1.23');
    const paymentReference = `BROWSER-${Date.now()}`;
    await page.locator('input[wire\\:model="reference"]').fill(paymentReference);
    await page.locator('textarea[wire\\:model="notes"]').fill('Browser QA payment');
    await waitForLivewire(page);
    await page.getByRole('button', { name: 'Save Payment' }).click();
    await expect(page.getByText(paymentReference)).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.payment-summary')).toContainText(/Part Paid|Paid/);
});

await step('Order-created pickup and delivery tasks appear in management boards', async () => {
    if (! createdOrderNumber) {
        throw new Error('No browser-created order number available');
    }

    await page.goto(`${baseUrl}/pickups`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2').filter({ hasText: 'Pickup Management' })).toBeVisible({ timeout: 15000 });
    await page.locator('input[placeholder*="Customer, phone, order"]').fill(createdOrderNumber);
    await waitForLivewire(page);
    await expect(page.locator('.pickup-row').filter({ hasText: createdOrderNumber }).first()).toBeVisible({ timeout: 15000 });
    await page.locator('.pickup-row').first().getByRole('button', { name: /Picked Up|Completed|Cancelled/i }).first().click();
    await waitForLivewire(page);
    await expect(page.locator('.pickup-row').first()).toBeVisible();

    await page.goto(`${baseUrl}/deliveries`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2').filter({ hasText: 'Delivery Management' })).toBeVisible({ timeout: 15000 });
    await page.locator('input[placeholder*="Customer, phone, order"]').fill(createdOrderNumber);
    await waitForLivewire(page);
    await expect(page.locator('.delivery-row').filter({ hasText: createdOrderNumber }).first()).toBeVisible({ timeout: 15000 });
    await page.locator('.delivery-row').first().getByRole('button', { name: /Assigned|Out|Delivered|Failed|Cancelled/i }).first().click();
    await waitForLivewire(page);
    await expect(page.locator('.delivery-row').first()).toBeVisible();
});

await step('Direct pickup and delivery scheduling pages save tasks', async () => {
    await page.goto(`${baseUrl}/pickups`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2').filter({ hasText: 'Pickup Management' })).toBeVisible({ timeout: 15000 });
    const pickupForm = page.locator('form.module-panel').first();
    await selectFirstNonEmpty(pickupForm.locator('label.field:has-text("Customer") select'), 'Pickup customer');
    await waitForLivewire(page);
    await selectFirstNonEmpty(pickupForm.locator('label.field:has-text("Linked Order") select'), 'Pickup order');
    await pickupForm.locator('label.field:has-text("Pickup Date") input').fill('2026-07-03');
    await pickupForm.locator('label.field:has-text("Pickup Time") input').fill('10:15');
    await pickupForm.locator('label.field:has-text("Address") textarea').fill('Browser QA pickup address');
    await page.getByRole('button', { name: 'Save Pickup' }).click();
    await expect(page.getByText('Pickup task saved.')).toBeVisible({ timeout: 15000 });

    await page.goto(`${baseUrl}/deliveries`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2').filter({ hasText: 'Delivery Management' })).toBeVisible({ timeout: 15000 });
    const deliveryForm = page.locator('form.module-panel').first();
    await selectFirstNonEmpty(deliveryForm.locator('label.field:has-text("Customer") select'), 'Delivery customer');
    await waitForLivewire(page);
    await selectFirstNonEmpty(deliveryForm.locator('label.field:has-text("Order") select'), 'Delivery order');
    await deliveryForm.locator('label.field:has-text("Delivery Date") input').fill('2026-07-04');
    await deliveryForm.locator('label.field:has-text("Delivery Time") input').fill('16:30');
    await deliveryForm.locator('label.field:has-text("Address") textarea').fill('Browser QA delivery address');
    await page.getByRole('button', { name: 'Save Delivery' }).click();
    await expect(page.getByText('Delivery task saved.')).toBeVisible({ timeout: 15000 });
});

await step('Generate and scan garment tags from browser UI', async () => {
    if (! createdOrderNumber) {
        throw new Error('No browser-created order number available');
    }

    await page.goto(`${baseUrl}/garment-tags`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Garment Tagging System' })).toBeVisible({ timeout: 15000 });
    await selectOptionContaining(page.locator('form.tag-create-panel select').first(), createdOrderNumber, 'Order');
    await waitForLivewire(page);
    await page.locator('input[wire\\:model="expected_garment_count"]').fill('2');
    await page.locator('input[wire\\:model="tagRows.0.garment_type"]').fill('Browser Shirt');
    await page.locator('input[wire\\:model="tagRows.0.quantity"]').fill('2');
    await page.locator('input[wire\\:model="tagRows.0.color"]').fill('Blue');
    await page.locator('input[wire\\:model="tagRows.0.condition"]').fill('Good');
    await page.getByRole('button', { name: 'Generate & Preview Tags' }).click();
    await expect(page.getByText('Generated Tags')).toBeVisible({ timeout: 15000 });
    generatedTagCode = (await page.locator('#generated-tag-print-area .tag-print-code').first().innerText()).trim();
    await expect(page.getByRole('button', { name: 'Print All Tags' })).toBeVisible();
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await waitForLivewire(page);

    await page.locator('input[wire\\:model="scan_code"]').fill(generatedTagCode);
    await page.getByRole('button', { name: 'Scan' }).click();
    await expect(page.getByText(generatedTagCode).first()).toBeVisible({ timeout: 15000 });
    await page.locator('.workflow-stepper').getByRole('button', { name: /Washing/ }).click();
    await waitForLivewire(page);
    await expect(page.locator('.current-scan-card')).toContainText('Washing');
});

await step('Open printable receipt route for browser-created order', async () => {
    if (! createdOrderNumber) {
        throw new Error('No browser-created order number available');
    }

    await page.goto(`${baseUrl}/orders`, { waitUntil: 'domcontentloaded' });
    await page.goto(`${baseUrl}/payments`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Payment Module' })).toBeVisible({ timeout: 15000 });

    if (paymentOrderNumber) {
        await page.locator('input[placeholder*="Order number"]').fill(paymentOrderNumber);
        await waitForLivewire(page);
    await page.locator('.payment-order-button').first().click();
    await expect(page.locator('.selected-payment-order h3')).toHaveText(paymentOrderNumber, { timeout: 10000 });
    } else {
        await page.locator('.payment-order-button').first().click();
        paymentOrderNumber = ((await page.locator('.selected-payment-order h3').innerText()).trim());
    }

    const printHref = await page.locator(`.selected-payment-order a[href*="/receipts/orders/"][href*="print=1"]`).first().getAttribute('href');

    if (! printHref) {
        throw new Error('No printable receipt link found in order queue');
    }

    await page.goto(new URL(printHref, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
    await expect(page.getByText(paymentOrderNumber).first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('body')).toContainText(/Receipt|Total|Balance/i);
});

await context.close();
await browser.close();

if (browserErrors.length > 0) {
    console.log('BROWSER WARNINGS/ERRORS');
    for (const error of browserErrors) {
        console.log(`- ${error}`);
    }
}

const failed = results.filter((result) => result.status === 'FAIL');
console.log('SUMMARY');
console.log(JSON.stringify({
    createdOrderNumber,
    paymentOrderNumber,
    generatedTagCode,
    passed: results.filter((result) => result.status === 'PASS').length,
    failed: failed.length,
    failures: failed,
    browserErrorCount: browserErrors.length,
}, null, 2));

if (failed.length > 0 || browserErrors.length > 0) {
    process.exitCode = 1;
}
