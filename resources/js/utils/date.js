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

const dayFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
const dayYearFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const weekdayFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: 'UTC' });

/**
 * For calendar dates sent as "YYYY-MM-DD". They are read as UTC and shown as
 * UTC, so the day never slips by one for viewers in other time zones.
 */
export function formatDay(value, { year = false } = {}) {
  const date = new Date(`${value}T00:00:00Z`);

  return (year ? dayYearFormatter : dayFormatter).format(date);
}

export function formatWeekday(value) {
  return weekdayFormatter.format(new Date(`${value}T00:00:00Z`));
}
