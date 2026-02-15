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

const initListSearch = (): void => {
  const searchForm = qs<HTMLFormElement>('[data-filter-form]');
  const searchInput = searchForm ? qs<HTMLInputElement>('input[type="text"]', searchForm) : null;
  if (!searchForm || !searchInput) {
    return;
  }

  const focusKey = 'listFilterFocus';

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

const initListSelection = (): void => {
  document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }

    const item = target.closest<HTMLElement>('[data-list-item]');
    if (!item) {
      return;
    }

    const list = item.closest<HTMLElement>('[data-list-selectable]');
    if (!list) {
      return;
    }

    const activeClass = list.dataset.listActiveClass || 'is-active';
    const items = list.querySelectorAll<HTMLElement>('[data-list-item]');
    items.forEach((element) => {
      element.classList.remove(activeClass);
    });

    item.classList.add(activeClass);
  });
};

initListSearch();
initListSelection();
