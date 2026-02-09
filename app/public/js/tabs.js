"use strict";
const initTabs = () => {
    var _a;
    const tabs = Array.from(document.querySelectorAll('[data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
    if (tabs.length === 0 || panels.length === 0) {
        return;
    }
    const getTabName = (tabEl) => { var _a; return (_a = tabEl.getAttribute('data-tab')) !== null && _a !== void 0 ? _a : ''; };
    const isValidTab = (tabName) => tabName !== '' && tabs.some((tab) => getTabName(tab) === tabName);
    const showPanels = (target) => {
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
    const setActiveTab = (target) => {
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
    const updateUrl = (target, mode) => {
        const url = new URL(window.location.href);
        url.search = '';
        url.searchParams.set('tab', target);
        if (mode === 'push') {
            window.history.pushState({ tab: target }, '', url.toString());
        }
        else {
            window.history.replaceState({ tab: target }, '', url.toString());
        }
    };
    const activateTab = (target, options) => {
        var _a;
        if (!isValidTab(target)) {
            return;
        }
        setActiveTab(target);
        if (options.updateHistory) {
            updateUrl(target, (_a = options.historyMode) !== null && _a !== void 0 ? _a : 'push');
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
        var _a;
        const url = new URL(window.location.href);
        const tabFromUrl = (_a = url.searchParams.get('tab')) !== null && _a !== void 0 ? _a : '';
        if (isValidTab(tabFromUrl)) {
            activateTab(tabFromUrl, { updateHistory: false });
        }
    });
    // Initial activation: prefer URL param, otherwise server-rendered active tab.
    const url = new URL(window.location.href);
    const tabFromUrl = (_a = url.searchParams.get('tab')) !== null && _a !== void 0 ? _a : '';
    if (isValidTab(tabFromUrl)) {
        activateTab(tabFromUrl, { updateHistory: false });
        return;
    }
    const activeTabEl = tabs.find((tabEl) => { var _a, _b; return (_b = (_a = tabEl.closest('li')) === null || _a === void 0 ? void 0 : _a.classList.contains('is-active')) !== null && _b !== void 0 ? _b : false; });
    const initial = activeTabEl ? getTabName(activeTabEl) : getTabName(tabs[0]);
    activateTab(initial, { updateHistory: false });
};
initTabs();
