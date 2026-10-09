const STATUS_STYLES = {
  pending: 'bg-warning-light text-warning',
  paid: 'bg-primary-light text-primary',
  shipped: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  delivered: 'bg-primary text-primary-on',
  cancelled: 'bg-danger-light text-danger',
  refunded: 'bg-stroke text-ink',
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
