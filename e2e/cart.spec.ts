import { test, expect, type Page } from '@playwright/test';
import { ACCOUNTS, LAMP, addLampToCart, logIn } from './helpers';

// The "Total" row of the order summary (not "Subtotal"), e.g. "Total$20.00".
const total = (page: Page) => page.getByText(/^Total\$/);

test.describe('cart', () => {
  test('a guest adds a product, changes the quantity and removes it', async ({ page }) => {
    await addLampToCart(page);
    await page.goto('/cart');

    await expect(page.getByRole('link', { name: LAMP.name })).toBeVisible();
    await expect(total(page)).toHaveText('Total$20.00');

    await page.getByRole('button', { name: 'Increase quantity' }).click();
    await expect(total(page)).toHaveText('Total$40.00');

    await page.getByRole('button', { name: 'Remove' }).first().click();
    await expect(page.getByText('Your cart is empty')).toBeVisible();
  });

  test('a valid coupon lowers the total, an invalid one is refused', async ({ page }) => {
    await addLampToCart(page);
    await page.goto('/cart');

    await page.getByPlaceholder('Enter code').fill('NOPE');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('#coupon-error')).toHaveText("That code isn't valid.");

    await page.getByPlaceholder('Enter code').fill('WELCOME10');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.getByText(/Discount \(WELCOME10\)/)).toBeVisible();
    await expect(total(page)).toHaveText('Total$18.00');
  });

  test('a guest is asked to log in at checkout, and keeps the cart after logging in', async ({ page }) => {
    await addLampToCart(page);
    await page.goto('/cart');
    await expect(page.getByRole('link', { name: 'Log in to checkout' })).toBeVisible();

    await logIn(page, ACCOUNTS.customer);
    // Wait for the login to finish before navigating away from it.
    await expect(page.getByRole('button', { name: /Demo Customer/ })).toBeVisible();
    await page.goto('/cart');

    await expect(page.getByRole('link', { name: LAMP.name })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Checkout', exact: true })).toBeVisible();
  });
});
