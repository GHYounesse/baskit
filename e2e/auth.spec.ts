import { test, expect } from '@playwright/test';
import { ACCOUNTS, logIn } from './helpers';

test.describe('login', () => {
  test('an admin lands in the back office', async ({ page }) => {
    await logIn(page, ACCOUNTS.admin);

    await expect(page).toHaveURL(/\/admin/);
  });

  test('a customer lands in the shop and can log out', async ({ page }) => {
    await logIn(page, ACCOUNTS.customer);
    await expect(page).toHaveURL('/');

    await page.getByRole('button', { name: /Demo Customer/ }).click();
    await page.getByRole('button', { name: 'Log out' }).click();

    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible();
  });

  test('a wrong password is refused and stays on the login page', async ({ page }) => {
    await logIn(page, { email: ACCOUNTS.customer.email, password: 'not-the-password' });

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByText(/do not match our records|credentials/i)).toBeVisible();
  });
});
