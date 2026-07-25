const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

/** Formats an ISO date/datetime string as "21 July, 2009" (UTC-based, avoids local-timezone day shift). */
export function formatDate(value?: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getUTCDate()} ${MONTH_NAMES[date.getUTCMonth()]}, ${date.getUTCFullYear()}`;
}
