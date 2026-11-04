import type { Page } from '@playwright/test';

// Accounts created by database/seeders/UserSeeder.php.
export const ACCOUNTS = {
  admin: { email: 'admin@example.com', password: 'password' },
  customer: { email: 'customer@example.com', password: 'password' },
};

export const AUTH_FILE = {
  admin: 'e2e/.auth/admin.json',
  customer: 'e2e/.auth/customer.json',
};

// Fixture created by database/seeders/E2ESeeder.php: $20.00, 5 in stock.
export const LAMP = { name: 'E2E Desk Lamp', slug: 'e2e-desk-lamp' };

export async function logIn(page: Page, account: { email: string; password: string }) {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email' }).fill(account.email);
  await page.getByRole('textbox', { name: 'Password' }).fill(account.password);
  await page.getByRole('button', { name: 'Log in' }).click();
}

export async function addLampToCart(page: Page) {
  await page.goto(`/products/${LAMP.slug}`);
  await page.getByRole('button', { name: /Add to cart/ }).click();
  await page.getByText('Added to cart.').waitFor();
}
