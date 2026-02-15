type ThemeChoice = 'system' | 'light' | 'dark';

type ThemeSelection = ThemeChoice | null;

const storageKey = 'appearance_theme';

const getStoredTheme = (): ThemeSelection => {
  if (typeof window === 'undefined') {
    return null;
  }

  const stored = window.localStorage.getItem(storageKey);
  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored;
  }

  return null;
};

const applyTheme = (choice: ThemeSelection): void => {
  if (typeof document === 'undefined') {
    return;
  }

  const root = document.documentElement;

  if (choice === 'light' || choice === 'dark') {
    root.setAttribute('data-theme', choice);
  } else {
    root.removeAttribute('data-theme');
  }
};

const setStoredTheme = (choice: ThemeChoice): void => {
  window.localStorage.setItem(storageKey, choice);
};

const updateSelect = (selectEl: HTMLSelectElement, choice: ThemeSelection): void => {
  if (!choice) {
    return;
  }

  selectEl.value = choice;
};

const syncStoredTheme = (): void => {
  const stored = getStoredTheme();

  if (stored) {
    applyTheme(stored);
  } else {
    applyTheme('system');
  }
};

const initAppearance = (): void => {
  const selectEl = document.querySelector<HTMLSelectElement>('[data-appearance-theme]');

  syncStoredTheme();

  if (!selectEl) {
    return;
  }

  updateSelect(selectEl, getStoredTheme() ?? 'system');

  selectEl.addEventListener('change', () => {
    const value = selectEl.value as ThemeChoice;

    if (value !== 'system' && value !== 'light' && value !== 'dark') {
      return;
    }

    setStoredTheme(value);
    applyTheme(value);
  });
};

syncStoredTheme();

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAppearance, { once: true });
  } else {
    initAppearance();
  }
}
