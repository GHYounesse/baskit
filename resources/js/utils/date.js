const formatter = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
});

/**
 * Format an ISO timestamp as a short, unambiguous date, e.g. "Oct 5, 2026".
 * Uses a fixed locale so it reads the same for every visitor.
 */
export function formatDate(value) {
  return formatter.format(new Date(value));
}
