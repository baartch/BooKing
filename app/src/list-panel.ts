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

const focusRowLink = (row: HTMLElement): void => {
  const link = row.getAttribute('data-row-link');
  if (!link) {
    return;
  }

  const detailTarget = row.getAttribute('data-row-target');
  const swap = row.getAttribute('data-row-swap') || 'innerHTML';
  const pushUrl = row.getAttribute('data-row-push-url');
  const htmx = (window as Window & {
    htmx?: { ajax: (method: string, url: string, config?: Record<string, unknown>) => void }
  }).htmx;

  if (detailTarget && htmx) {
    const config: Record<string, unknown> = {
      target: detailTarget,
      swap,
    };

    config.pushUrl = pushUrl !== null ? (pushUrl !== '' ? pushUrl : link) : link;

    htmx.ajax('GET', link, config);
    return;
  }

  window.location.href = link;
};

const initListSelection = (): void => {
  document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }

    const row = target.closest<HTMLElement>('[data-list-item]');
    if (!row) {
      return;
    }

    const list = row.closest<HTMLElement>('[data-list-selectable]');
    if (!list) {
      return;
    }

    const interactiveElement = target.closest('a, button, input, select, textarea, label');
    if (!interactiveElement && row.hasAttribute('data-row-link')) {
      event.preventDefault();
      focusRowLink(row);
    }

    const activeClass = list.dataset.listActiveClass || 'is-active';
    const items = list.querySelectorAll<HTMLElement>('[data-list-item]');
    items.forEach((element) => {
      element.classList.remove(activeClass);
    });

    row.classList.add(activeClass);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }

    if (!target.matches('[data-list-item]')) {
      return;
    }

    if (!target.hasAttribute('data-row-link')) {
      return;
    }

    event.preventDefault();
    focusRowLink(target);
  });
};

initListSearch();
initListSelection();
