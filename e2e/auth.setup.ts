import { test as setup, expect } from '@playwright/test';
import { ACCOUNTS, AUTH_FILE, logIn } from './helpers';

// Log in once per role and reuse the session in the specs that need it.
setup('log in as admin', async ({ page }) => {
  await logIn(page, ACCOUNTS.admin);
  await expect(page).toHaveURL(/\/admin/);
  await page.context().storageState({ path: AUTH_FILE.admin });
});

setup('log in as customer', async ({ page }) => {
  await logIn(page, ACCOUNTS.customer);
  await expect(page).not.toHaveURL(/\/login/);
  await page.context().storageState({ path: AUTH_FILE.customer });
});
