import { formatNumber } from '@/utils/money';

/**
 * A signed stock change: "+5" in green when units come in, "−3" when they go
 * out. The sign and a hidden word carry the meaning, not only the colour.
 */
export default function StockChange({ value }) {
  const added = value > 0;

  return (
    <span className={`font-semibold tabular-nums ${added ? 'text-brand' : 'text-ink'}`}>
      <span className="sr-only">{added ? 'Added ' : 'Removed '}</span>
      {added ? '+' : '−'}
      {formatNumber(Math.abs(value))}
    </span>
  );
}
