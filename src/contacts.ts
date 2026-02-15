export {};

const qs = <T extends Element>(selector: string, scope: ParentNode = document): T | null =>
  scope.querySelector(selector) as T | null;

const createDebounce = (callback: () => void, delay: number) => {
  let timerId: number | null = null;

  const clearTimer = (): void => {
    if (timerId !== null) {
      window.clearTimeout(timerId);
      timerId = null;
    }
  };

  const trigger = (): void => {
    clearTimer();
    timerId = window.setTimeout(callback, delay);
  };

  const flush = (): void => {
    clearTimer();
    callback();
  };

  return { trigger, flush, clear: clearTimer };
};

const initContactsSearch = (): void => {
  const searchForm = qs<HTMLFormElement>('[data-filter-form]');
  const searchInput = searchForm ? qs<HTMLInputElement>('input[name="q"]', searchForm) : null;
  if (!searchForm || !searchInput) {
    return;
  }

  const focusKey = 'contactsSearchFocus';

  const submitSearch = (): void => {
    sessionStorage.setItem(focusKey, '1');
    searchForm.submit();
  };

  const debounce = createDebounce(submitSearch, 500);

  if (sessionStorage.getItem(focusKey) === '1') {
    searchInput.focus();
    const valueLength = searchInput.value.length;
    searchInput.setSelectionRange(valueLength, valueLength);
    sessionStorage.removeItem(focusKey);
  }

  searchInput.addEventListener('input', () => {
    debounce.trigger();
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      debounce.flush();
    }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
      event.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });
};

const initContactsPage = (): void => {
  initContactsSearch();
};

initContactsPage();
