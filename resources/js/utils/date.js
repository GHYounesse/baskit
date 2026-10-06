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

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
});

/**
 * Like formatDate, with the time: "Oct 5, 2026, 3:42 PM".
 */
export function formatDateTime(value) {
  return dateTimeFormatter.format(new Date(value));
}
