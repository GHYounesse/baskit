const STATUS_STYLES = {
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  paid: 'bg-primary-light text-primary',
  shipped: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

export default function OrderStatusBadge({ status }) {
  return (
    <span
      className={`inline-block rounded-full px-2.5 py-1 text-xs font-medium capitalize ${
        STATUS_STYLES[status] ?? 'bg-stroke text-ink-muted'
      }`}
    >
      {status}
    </span>
  );
}
