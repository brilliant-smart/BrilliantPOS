/**
 * Returns today's date in YYYY-MM-DD format using local time.
 *
 * Do NOT use `new Date().toISOString().split('T')[0]` for date defaults —
 * toISOString() returns UTC, so it can show yesterday's date after midnight
 * in timezones ahead of UTC (e.g. UTC+1 in Nigeria).
 */
export function todayLocal(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Parses a YYYY-MM-DD date string as local midnight instead of UTC midnight.
 *
 * `new Date("2026-05-25")` parses as UTC midnight, which shifts the date
 * in timezones behind UTC (e.g. UTC-5 would see May 24 instead of May 25).
 * Use this function to ensure the date is interpreted in the user's local timezone.
 */
export function parseLocalDate(dateStr: string): Date {
  const [y, m, d] = dateStr.split('-').map(Number);
  return new Date(y, m - 1, d);
}