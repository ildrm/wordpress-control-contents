import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.beforeEach(async ({ page }) => {
  await page.request.get('/wp-login.php');
  const login = await page.request.post('/wp-login.php', {
    form: { log: 'uwcmp_test_admin', pwd: 'local-test-password-only', 'wp-submit': 'Log In', redirect_to: 'http://localhost:8876/wp-admin/', testcookie: '1' },
    maxRedirects: 0,
  });
  expect(login.status()).toBe(302);
  await page.goto('/wp-admin/');
  await expect(page).toHaveURL(/wp-admin/);
});

test('policy save, shadow behavior, simulation escaping and scoped audit screen', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=uwcmp-policy');
  const version = await page.locator('input[name=version]').inputValue();
  const nonce = await page.locator('input[name=_wpnonce]').inputValue();
  for (const fields of [{ 'terms[]': 'badword' }, { terms: 'safe', 'shadow[]': '1' }, { terms: 'safe', shadow: 'invalid' }]) {
    const invalid = await page.request.post('/wp-admin/admin-post.php', { form: { action: 'uwcmp_save', version, _wpnonce: nonce, ...fields } });
    expect(invalid.status()).toBe(409);
    await page.reload();
    await expect(page.locator('input[name=version]')).toHaveValue(version);
  }
  const csrf = await page.request.post('/wp-admin/admin-post.php', { form: { action: 'uwcmp_save', version, terms: 'unexpected replacement' } });
  expect(csrf.status()).toBe(403);
  await page.reload();
  await expect(page.locator('input[name=version]')).toHaveValue(version);
  await page.getByLabel('Prohibited words and phrases, one per line (maximum 1,000)').fill('badword\nعبارت ممنوع');
  await page.getByLabel('Shadow mode: record proposed decisions without changing content').check();
  await page.getByRole('button', { name: 'Save a new policy version' }).click();
  await expect(page.getByLabel('Shadow mode: record proposed decisions without changing content')).toBeChecked();
  await page.goto('/wp-admin/admin.php?page=uwcmp-playground');
  await page.getByLabel('Sample comment').fill('<img src=x onerror="window.__injected=true"> badword');
  await page.getByRole('button', { name: 'Test sample' }).click();
  await expect(page.locator('.wrap pre')).toContainText('"action": "allow"');
  await expect(page.locator('.wrap pre')).toContainText('"proposed_action": "pending"');
  expect(await page.evaluate(() => window.__injected)).toBeUndefined();
  await page.goto('/wp-admin/admin.php?page=uwcmp-audit');
  await expect(page.getByRole('heading', { name: 'Audit log' })).toBeVisible();
  await expect(page.locator('.wrap table')).toBeVisible();
  await expect(page.locator('.wrap table')).toContainText('Shadow proposal: pending');
});

for (const screen of ['uwcmp', 'uwcmp-policy', 'uwcmp-playground', 'uwcmp-audit']) {
  test(`${screen}: WCAG A/AA automated checks and RTL keyboard focus`, async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=${screen}`);
    const scan = await new AxeBuilder({ page }).include('.wrap').withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(scan.violations).toEqual([]);
    await page.evaluate(() => { document.documentElement.dir = 'rtl'; document.body.classList.add('rtl'); });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const overflow = await page.locator('.wrap').evaluate(el => el.scrollWidth > el.clientWidth + 1);
    expect(overflow).toBe(false);
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.activeElement !== document.body)).toBe(true);
  });
}

test('all moderation screens fit a narrow RTL viewport', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  for (const screen of ['uwcmp', 'uwcmp-policy', 'uwcmp-playground', 'uwcmp-audit']) {
    await page.goto(`/wp-admin/admin.php?page=${screen}`);
    await page.evaluate(() => { document.documentElement.dir = 'rtl'; document.body.classList.add('rtl'); });
    expect(await page.locator('.wrap').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
});
