const formatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
});

/**
 * Format an integer cent amount (as stored in the database) as a currency
 * string, e.g. formatMoney(1499) => "$14.99".
 */
export function formatMoney(cents) {
  return formatter.format((cents ?? 0) / 100);
}
