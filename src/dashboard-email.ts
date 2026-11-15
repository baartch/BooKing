export const formatDashboardEmailDate = (value: string): string | null => {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;

  const locales = navigator.languages.length > 0
    ? Array.from(navigator.languages)
    : navigator.language;
  return new Intl.DateTimeFormat(locales, { dateStyle: 'short', timeStyle: 'short' }).format(date);
};

export const initDashboardEmailDates = (scope: ParentNode = document): void => {
  const dates: HTMLElement[] = [];
  if (scope instanceof HTMLElement && scope.matches('[data-dashboard-email-date]')) {
    dates.push(scope);
  }
  dates.push(...Array.from(scope.querySelectorAll<HTMLElement>('[data-dashboard-email-date]')));

  dates.forEach((element) => {
    const value = element.getAttribute('datetime');
    if (!value) return;

    const formatted = formatDashboardEmailDate(value);
    if (formatted !== null) element.textContent = formatted;
  });
};

const startDashboardEmailDates = (): void => initDashboardEmailDates(document);
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startDashboardEmailDates, { once: true });
} else {
  startDashboardEmailDates();
}
