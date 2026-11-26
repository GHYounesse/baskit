import { describe, expect, it } from 'vitest';
import { formatMoney, formatMoneyCompact, formatMoneyWhole, formatNumber } from './money';

// Prices are integer cents everywhere; this is the one place they become dollars.
describe('formatMoney', () => {
  it('formats cents as dollars', () => {
    expect(formatMoney(1499)).toBe('$14.99');
    expect(formatMoney(5)).toBe('$0.05');
    expect(formatMoney(100000)).toBe('$1,000.00');
  });

  it('treats a missing amount as zero', () => {
    expect(formatMoney(null)).toBe('$0.00');
    expect(formatMoney(undefined)).toBe('$0.00');
  });
});

describe('formatMoneyWhole', () => {
  it('drops the cents', () => {
    expect(formatMoneyWhole(1234567)).toBe('$12,346');
  });
});

describe('formatMoneyCompact', () => {
  it('shortens big amounts for chart axes', () => {
    expect(formatMoneyCompact(85000)).toBe('$850');
    expect(formatMoneyCompact(123456)).toBe('$1.2K');
    expect(formatMoneyCompact(250000000)).toBe('$2.5M');
  });

  it('does not show a trailing .0', () => {
    expect(formatMoneyCompact(200000)).toBe('$2K');
  });
});

describe('formatNumber', () => {
  it('groups thousands and defaults to zero', () => {
    expect(formatNumber(1234567)).toBe('1,234,567');
    expect(formatNumber(null)).toBe('0');
  });
});
