import { test, expect } from '@playwright/test';
import { AUTH_FILE } from './helpers';

const ADMIN_SCREENS = ['/admin', '/admin/orders', '/admin/products', '/admin/coupons', '/admin/inventory'];

test.describe('as a guest', () => {
  for (const url of [...ADMIN_SCREENS, '/orders', '/checkout']) {
    test(`${url} sends you to the login page`, async ({ page }) => {
      await page.goto(url);

      await expect(page).toHaveURL(/\/login/);
    });
  }
});

test.describe('as a customer', () => {
  test.use({ storageState: AUTH_FILE.customer });

  for (const url of ADMIN_SCREENS) {
    test(`${url} is forbidden`, async ({ page }) => {
      const response = await page.goto(url);

      expect(response?.status()).toBe(403);
    });
  }

  test('the shop does not offer an Admin link', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('link', { name: 'Admin' })).toHaveCount(0);
  });
});

test.describe('as an admin', () => {
  test.use({ storageState: AUTH_FILE.admin });

  for (const url of ADMIN_SCREENS) {
    test(`${url} opens`, async ({ page }) => {
      const response = await page.goto(url);

      expect(response?.status()).toBe(200);
    });
  }
});
