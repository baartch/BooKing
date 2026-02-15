type HistoryMode = 'push' | 'replace';

type ActivateOptions = {
  updateHistory: boolean;
  historyMode?: HistoryMode;
};

const initTabs = (): void => {
  const tabs = Array.from(document.querySelectorAll<HTMLElement>('[data-tab]'));
  const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));

  if (tabs.length === 0 || panels.length === 0) {
    return;
  }

  const getTabName = (tabEl: HTMLElement): string => tabEl.getAttribute('data-tab') ?? '';

  const isValidTab = (tabName: string): boolean =>
    tabName !== '' && tabs.some((tab) => getTabName(tab) === tabName);

  const showPanels = (target: string): void => {
    let activated = false;

    panels.forEach((panel) => {
      const isActive = panel.getAttribute('data-tab-panel') === target;
      panel.classList.toggle('is-hidden', !isActive);
      if (isActive) {
        activated = true;
      }
    });

    if (activated) {
      document.dispatchEvent(new CustomEvent('tab:activated', { detail: { tab: target } }));
    }
  };

  const setActiveTab = (target: string): void => {
    tabs.forEach((tabEl) => {
      const isActive = getTabName(tabEl) === target;
      tabEl.classList.toggle('is-active', isActive);
      tabEl.setAttribute('aria-selected', isActive ? 'true' : 'false');

      const parent = tabEl.closest('li');
      if (parent) {
        parent.classList.toggle('is-active', isActive);
      }
    });

    showPanels(target);
  };

  const updateUrl = (target: string, mode: HistoryMode): void => {
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('tab', target);

    if (mode === 'push') {
      window.history.pushState({ tab: target }, '', url.toString());
    } else {
      window.history.replaceState({ tab: target }, '', url.toString());
    }
  };

  const activateTab = (target: string, options: ActivateOptions): void => {
    if (!isValidTab(target)) {
      return;
    }

    setActiveTab(target);

    if (options.updateHistory) {
      updateUrl(target, options.historyMode ?? 'push');
    }
  };

  tabs.forEach((tabEl) => {
    tabEl.addEventListener('click', (event) => {
      event.preventDefault();
      const target = getTabName(tabEl);
      if (!isValidTab(target)) {
        return;
      }
      activateTab(target, { updateHistory: true, historyMode: 'push' });
    });
  });

  window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    const tabFromUrl = url.searchParams.get('tab') ?? '';
    if (isValidTab(tabFromUrl)) {
      activateTab(tabFromUrl, { updateHistory: false });
    }
  });

  // Initial activation: prefer URL param, otherwise server-rendered active tab.
  const url = new URL(window.location.href);
  const tabFromUrl = url.searchParams.get('tab') ?? '';

  if (isValidTab(tabFromUrl)) {
    activateTab(tabFromUrl, { updateHistory: false });
    return;
  }

  const activeTabEl = tabs.find((tabEl) => tabEl.closest('li')?.classList.contains('is-active') ?? false);
  const initial = activeTabEl ? getTabName(activeTabEl) : getTabName(tabs[0]);
  activateTab(initial, { updateHistory: false });
};

initTabs();
