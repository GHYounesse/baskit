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

const wholeFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 0,
});

/**
 * Whole dollars, for big headline figures: 1234567 => "$12,346".
 */
export function formatMoneyWhole(cents) {
  return wholeFormatter.format((cents ?? 0) / 100);
}

/**
 * Short money for chart axes: 85000 => "$850", 123456 => "$1.2K", 250000000 => "$2.5M".
 */
export function formatMoneyCompact(cents) {
  const dollars = (cents ?? 0) / 100;
  const abs = Math.abs(dollars);
  const trim = (n) => n.toFixed(1).replace(/\.0$/, '');

  if (abs >= 1_000_000) return `$${trim(dollars / 1_000_000)}M`;
  if (abs >= 1_000) return `$${trim(dollars / 1_000)}K`;

  return `$${Math.round(dollars)}`;
}

const numberFormatter = new Intl.NumberFormat('en-US');

export function formatNumber(n) {
  return numberFormatter.format(n ?? 0);
}
