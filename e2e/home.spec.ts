import { test, expect } from '@playwright/test';
import { AUTH_FILE } from './helpers';

test.describe('home page', () => {
  test('the front page is the home page, with the catalogue one click away', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { level: 1 })).toContainText('Fill your basket.');

    await page.getByRole('link', { name: 'Start shopping' }).first().click();

    await expect(page).toHaveURL('/shop');
    await expect(page.getByPlaceholder('Search products...')).toBeVisible();
  });

  test('a category row opens the catalogue filtered on that category', async ({ page }) => {
    await page.goto('/');

    await page.getByRole('link', { name: /^Books\s*\d+ products?/ }).click();

    await expect(page).toHaveURL(/\/shop\?category=books/);
  });

  test('a guest is invited to create an account', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('link', { name: 'Create an account' })).toBeVisible();
  });

  test.describe('signed in', () => {
    test.use({ storageState: AUTH_FILE.customer });

    test('a customer is offered their orders instead', async ({ page }) => {
      await page.goto('/');

      await expect(page.getByRole('link', { name: 'See my orders' })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Create an account' })).toHaveCount(0);
    });
  });
});
