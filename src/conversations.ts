const STORAGE_KEY = 'conversations.closedExpanded';

const initClosedConversations = (): void => {
  const container = document.querySelector<HTMLElement>('[data-closed-conversations]');
  if (!container) {
    return;
  }

  const toggle = container.querySelector<HTMLButtonElement>('[data-closed-toggle]');
  const icon = container.querySelector<HTMLElement>('[data-closed-toggle-icon]');
  const body = container.querySelector<HTMLElement>('[data-closed-body]');
  const search = container.querySelector<HTMLInputElement>('[data-closed-search]');
  const empty = container.querySelector<HTMLElement>('[data-closed-empty]');
  const items = Array.from(container.querySelectorAll<HTMLElement>('[data-closed-item]'));

  if (!toggle || !body) {
    return;
  }

  const setExpanded = (expanded: boolean): void => {
    body.classList.toggle('is-hidden', !expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    icon?.classList.toggle('fa-chevron-right', !expanded);
    icon?.classList.toggle('fa-chevron-down', expanded);
    try {
      window.localStorage.setItem(STORAGE_KEY, expanded ? '1' : '0');
    } catch {
      // Storage unavailable; state is not persisted.
    }
  };

  let initial = false;
  try {
    initial = window.localStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    initial = false;
  }
  if (body.querySelector('a.is-active')) {
    initial = true;
  }
  setExpanded(initial);

  toggle.addEventListener('click', () => {
    setExpanded(body.classList.contains('is-hidden'));
  });

  search?.addEventListener('input', () => {
    const terms = search.value.trim().toLowerCase().split(/\s+/).filter((term) => term !== '');
    let visible = 0;
    items.forEach((item) => {
      const text = item.dataset.searchText ?? '';
      const matches = terms.every((term) => text.includes(term));
      item.classList.toggle('is-hidden', !matches);
      if (matches) {
        visible += 1;
      }
    });
    empty?.classList.toggle('is-hidden', visible > 0);
  });
};

initClosedConversations();
