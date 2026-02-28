import { getStoredTheme } from "./appearance.js";

const initWysiEditor = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>("#email_body");

  if (!textarea) {
    return;
  }

  const wysi = window as typeof window & {
    Wysi?: (options: {
      el: string;
      darkMode?: boolean;
      customTags?: Array<{ tags: string[]; attributes?: string[] }>;
    }) => void;
  };

  if (typeof wysi.Wysi !== "function") {
    return;
  }

  const storedTheme = getStoredTheme();
  const darkModeMql =
    window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
  const prefersDarkMode = darkModeMql && darkModeMql.matches;
  const isDarkMode =
    storedTheme === "dark" || (storedTheme !== "light" && prefersDarkMode);

  wysi.Wysi({
    el: "#email_body",
    darkMode: isDarkMode,
    customTags: [
      {
        tags: ["blockquote"],
        attributes: ["type", "cite"],
      },
    ],
  });
};

const escapeHtml = (value: string): string =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");

const initWysiPasteSanitizer = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>("#email_body");
  if (!textarea) {
    return;
  }

  const wrapper = textarea.previousElementSibling;
  const editor = wrapper?.querySelector<HTMLElement>(".wysi-editor") ?? null;
  if (!editor || editor.dataset.plainPasteBound === "true") {
    return;
  }

  editor.dataset.plainPasteBound = "true";
  editor.addEventListener("paste", (event: ClipboardEvent) => {
    if (!event.clipboardData || event.clipboardData.files.length > 0) {
      return;
    }

    const plainText = event.clipboardData.getData("text/plain");
    if (!plainText) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const normalized = plainText
      .replace(/\r\n?/g, "\n")
      .replace(/\u00A0/g, " ")
      .replace(/[ \t]+\n/g, "\n")
      .replace(/\n{3,}/g, "\n\n");

    const blocks = normalized
      .split(/\n\n+/)
      .map((part) => part.trim())
      .filter((part) => part !== "");

    const html = (blocks.length ? blocks : [""])
      .map((block) => {
        if (block === "") {
          return "<p><br></p>";
        }
        const content = block
          .split("\n")
          .map((line) => escapeHtml(line.trim()))
          .join("<br>");
        return `<p>${content || "<br>"}</p>`;
      })
      .join("");

    document.execCommand("insertHTML", false, html);
    editor.dispatchEvent(new Event("input", { bubbles: true }));
  });
};

const isValidEmail = (email: string): boolean => {
  if (email === "") {
    return true;
  }
  return /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(email);
};

const normalizeEmailList = (value: string): string => {
  const trimmed = value.trim();
  if (trimmed === "") {
    return "";
  }

  const parts = trimmed.split(/[;,]+/).map((item) => item.trim());
  const cleaned = parts.filter((part) => part !== "");
  return cleaned.join(", ");
};

const getLastEmailToken = (value: string): string => {
  const parts = value.split(/[;,]/);
  return (parts[parts.length - 1] ?? "").trim();
};

const replaceLastEmailToken = (value: string, email: string): string => {
  const parts = value.split(/[;,]/).map((item) => item.trim());
  const prefix = parts.slice(0, -1).filter((part) => part !== "");
  return [...prefix, email].join(", ");
};

const validateEmailInput = (input: HTMLInputElement): boolean => {
  const raw = input.value.trim();
  const parts = raw === "" ? [] : raw.split(/[;,]+/).map((item) => item.trim());
  const invalid = parts.some((part) => part !== "" && !isValidEmail(part));
  input.classList.toggle("is-danger", invalid);

  const field = input.closest(".field");
  const help = field?.querySelector<HTMLElement>("[data-email-help]") ?? null;
  const icon = field?.querySelector<HTMLElement>("[data-email-icon]") ?? null;
  if (help) {
    help.classList.toggle("is-hidden", !invalid);
  }
  if (icon) {
    icon.classList.toggle("is-hidden", !invalid);
  }

  return !invalid;
};

type RecipientItem = {
  id: number;
  type: string;
  name: string;
  label: string;
  email: string;
  source?: string;
};

type LinkItem = {
  id: number;
  type: string;
  name: string;
};

let selectedLinkItems: LinkItem[] = [];

const renderLinkItems = (): void => {
  const container = document.querySelector<HTMLElement>("[data-email-links]");
  const list = document.querySelector<HTMLElement>("[data-email-links-list]");
  const inputs = document.querySelector<HTMLElement>(
    "[data-email-link-inputs]",
  );
  if (!container || !list || !inputs) {
    return;
  }

  list.innerHTML = "";
  inputs.innerHTML = "";

  if (selectedLinkItems.length === 0) {
    container.classList.add("is-hidden");
    return;
  }

  container.classList.remove("is-hidden");

  selectedLinkItems.forEach((item, index) => {
    const wrapper = document.createElement("span");
    wrapper.className = "email-link-item";
    wrapper.textContent = item.name;

    const removeButton = document.createElement("button");
    removeButton.type = "button";
    removeButton.className = "delete is-small";
    removeButton.dataset.index = String(index);
    removeButton.setAttribute("aria-label", `Remove ${item.name}`);
    wrapper.appendChild(removeButton);

    list.appendChild(wrapper);
    if (index < selectedLinkItems.length - 1) {
      list.appendChild(document.createTextNode(", "));
    }

    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = "link_items[]";
    hidden.value = `${item.type}:${item.id}`;
    inputs.appendChild(hidden);
  });
};

const addLinkItem = (item: LinkItem): void => {
  if (!item.type || item.id <= 0 || item.name.trim() === "") {
    return;
  }
  if (
    selectedLinkItems.some(
      (existing) => existing.type === item.type && existing.id === item.id,
    )
  ) {
    return;
  }
  selectedLinkItems = [...selectedLinkItems, item];
  renderLinkItems();
};

const removeLinkItem = (index: number): void => {
  if (index < 0 || index >= selectedLinkItems.length) {
    return;
  }
  selectedLinkItems = selectedLinkItems.filter(
    (_, itemIndex) => itemIndex !== index,
  );
  renderLinkItems();
};

const initLinkList = (): void => {
  const list = document.querySelector<HTMLElement>("[data-email-links-list]");
  if (!list || list.dataset.linkListBound === "true") {
    return;
  }
  list.dataset.linkListBound = "true";
  list.addEventListener("click", (event) => {
    const target = event.target as HTMLElement;
    if (!target.classList.contains("delete")) {
      return;
    }
    const index = Number(target.dataset.index);
    if (Number.isNaN(index)) {
      return;
    }
    removeLinkItem(index);
  });
  renderLinkItems();
};

const initQuoteToggle = (): void => {
  const detailBlocks = document.querySelectorAll<HTMLElement>(
    "[data-email-detail]",
  );
  if (!detailBlocks.length) {
    return;
  }

  detailBlocks.forEach((detailBlock) => {
    const body = detailBlock.querySelector<HTMLElement>(
      "[data-email-detail-body]",
    );
    const toggle = detailBlock.querySelector<HTMLButtonElement>(
      "[data-email-quote-toggle]",
    );
    if (!body || !toggle) {
      return;
    }

    const updateLabel = (isCollapsed: boolean): void => {
      toggle.innerHTML = isCollapsed
        ? '<span class="icon is-small"><i class="fa-solid fa-quote-left"></i></span>'
        : '<span class="icon is-small"><i class="fa-solid fa-quote-right"></i></span>';
      toggle.dataset.emailQuoteState = isCollapsed ? "collapsed" : "expanded";
    };

    const hasQuotes = body.querySelector("blockquote[type=\"cite\"]");
    const toggleWrapper = toggle.closest<HTMLElement>(
      ".email-detail-quote-toggle",
    );
    if (!hasQuotes) {
      toggleWrapper?.classList.add("is-hidden");
      return;
    }

    toggleWrapper?.classList.remove("is-hidden");
    body.classList.add("is-quotes-collapsed");
    updateLabel(true);

    if (toggle.dataset.quoteToggleBound === "true") {
      return;
    }
    toggle.dataset.quoteToggleBound = "true";

    toggle.addEventListener("click", () => {
      const collapsed = body.classList.toggle("is-quotes-collapsed");
      updateLabel(collapsed);
    });
  });
};

const initEmailValidation = (): void => {
  const inputs = Array.from(
    document.querySelectorAll<HTMLInputElement>("[data-email-input]"),
  );
  if (!inputs.length) {
    return;
  }

  inputs.forEach((input) => {
    if (input.dataset.emailValidationBound === "true") {
      return;
    }
    input.dataset.emailValidationBound = "true";

    const handleChange = (): void => {
      validateEmailInput(input);
    };

    input.addEventListener("input", handleChange);
    input.addEventListener("blur", handleChange);
    handleChange();
  });
};

const initMailboxSwitch = (): void => {
  const selects = Array.from(
    document.querySelectorAll<HTMLSelectElement>("[data-mailbox-switch]"),
  );

  selects.forEach((select) => {
    if (select.dataset.mailboxSwitchBound === "true") {
      return;
    }
    select.dataset.mailboxSwitchBound = "true";

    select.addEventListener("change", () => {
      const form = select.closest("form");
      if (form instanceof HTMLFormElement) {
        form.submit();
      }
    });
  });
};

const initRecipientToggle = (): void => {
  const toggleButton = document.querySelector<HTMLButtonElement>(
    "[data-email-recipient-toggle-button]",
  );
  const extraFields = document.querySelector<HTMLElement>(
    "[data-email-recipient-extra]",
  );

  if (!toggleButton || !extraFields) {
    return;
  }

  if (toggleButton.dataset.recipientToggleBound === "true") {
    return;
  }
  toggleButton.dataset.recipientToggleBound = "true";

  const updateState = (isExpanded: boolean): void => {
    extraFields.classList.toggle("is-hidden", !isExpanded);
    toggleButton.setAttribute("aria-expanded", isExpanded ? "true" : "false");
    const icon = toggleButton.querySelector("i");
    if (icon) {
      icon.classList.toggle("fa-chevron-down", !isExpanded);
      icon.classList.toggle("fa-chevron-up", isExpanded);
    }
  };

  const hasPrefill = extraFields.querySelector<HTMLInputElement>(
    "input[value]:not([value=''])",
  );
  updateState(Boolean(hasPrefill));

  toggleButton.addEventListener("click", (event) => {
    event.preventDefault();
    updateState(extraFields.classList.contains("is-hidden"));
  });
};

const initSendMenu = (): void => {
  const dropdown = document.querySelector<HTMLElement>("[data-email-send-menu]");
  const trigger = dropdown?.querySelector<HTMLElement>(
    ".dropdown-trigger button",
  );

  if (!dropdown || !trigger) {
    return;
  }

  if (dropdown.dataset.emailSendMenuBound === "true") {
    return;
  }
  dropdown.dataset.emailSendMenuBound = "true";

  const closeMenu = (): void => {
    dropdown.classList.remove("is-active");
  };

  trigger.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    dropdown.classList.toggle("is-active");
  });

  dropdown.querySelectorAll<HTMLElement>(".dropdown-item").forEach((item) => {
    item.addEventListener("click", () => {
      closeMenu();
    });
  });

  document.addEventListener("click", (event) => {
    if (!dropdown.contains(event.target as Node)) {
      closeMenu();
    }
  });
};

const initScheduleModal = (): void => {
  if (document.body.dataset.scheduleModalBound === "true") {
    return;
  }
  document.body.dataset.scheduleModalBound = "true";

  const resolveModalState = () => {
    const modal = document.querySelector<HTMLElement>(
      "[data-email-schedule-modal]",
    );
    const form = document.querySelector<HTMLFormElement>(
      "[data-email-compose-form]",
    );
    if (!modal || !form) {
      return null;
    }

    const dateField = form.querySelector<HTMLInputElement>(
      "[name=\"schedule_date\"]",
    );
    const timeField = form.querySelector<HTMLInputElement>(
      "[name=\"schedule_time\"]",
    );
    const datePicker = modal.querySelector<HTMLInputElement>(
      "[data-email-schedule-date]",
    );
    const timePicker = modal.querySelector<HTMLInputElement>(
      "[data-email-schedule-time]",
    );

    if (!dateField || !timeField || !datePicker || !timePicker) {
      return null;
    }

    return {
      modal,
      form,
      dateField,
      timeField,
      datePicker,
      timePicker,
    };
  };

  const openModal = (): void => {
    const state = resolveModalState();
    if (!state) {
      return;
    }
    state.datePicker.value = state.dateField.value;
    state.timePicker.value = state.timeField.value;
    state.datePicker.classList.remove("is-danger");
    state.timePicker.classList.remove("is-danger");
    state.modal.classList.add("is-active");
  };

  const closeModal = (): void => {
    const state = resolveModalState();
    if (!state) {
      return;
    }
    state.modal.classList.remove("is-active");
  };

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-trigger]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();
    openModal();
  });

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-close]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();
    closeModal();
  });

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-submit]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();

    const state = resolveModalState();
    if (!state) {
      return;
    }

    state.dateField.value = state.datePicker.value;
    state.timeField.value = state.timePicker.value;

    if (!state.dateField.value || !state.timeField.value) {
      state.datePicker.classList.toggle("is-danger", !state.datePicker.value);
      state.timePicker.classList.toggle("is-danger", !state.timePicker.value);
      return;
    }

    const actionField = document.createElement("input");
    actionField.type = "hidden";
    actionField.name = "action";
    actionField.value = "schedule_send";
    state.form.appendChild(actionField);
    state.form.submit();
    actionField.remove();
  });
};

const initRecipientLookup = (): void => {
  const lookups = Array.from(
    document.querySelectorAll<HTMLElement>("[data-email-lookup]"),
  );

  lookups.forEach((lookup) => {
    if (lookup.dataset.lookupBound === "true") {
      return;
    }
    lookup.dataset.lookupBound = "true";

    const input = lookup.querySelector<HTMLInputElement>("[data-email-input]");
    const menu = lookup.querySelector<HTMLDivElement>(".dropdown-menu");
    const content = lookup.querySelector<HTMLDivElement>(".dropdown-content");
    const lookupUrl = lookup.dataset.lookupUrl ?? "";

    if (!input || !menu || !content || lookupUrl === "") {
      return;
    }

    let activeRequest = 0;
    let debounceId: number | null = null;
    let currentItems: RecipientItem[] = [];
    let selectedIndex = -1;

    const clearResults = (): void => {
      content.innerHTML = "";
      menu.classList.add("is-hidden");
      lookup.classList.remove("is-active");
      currentItems = [];
      selectedIndex = -1;
    };

    const selectItem = (index: number): void => {
      const items = Array.from(
        content.querySelectorAll<HTMLElement>(".dropdown-item"),
      );
      items.forEach((item) => item.classList.remove("is-active"));

      if (index >= 0 && index < items.length) {
        selectedIndex = index;
        items[index].classList.add("is-active");
        items[index].scrollIntoView({ block: "nearest" });
        return;
      }

      selectedIndex = -1;
    };

    const showResults = (items: RecipientItem[]): void => {
      currentItems = items;
      selectedIndex = -1;

      if (!items.length) {
        content.innerHTML = '<div class="dropdown-item">No results found</div>';
      } else {
        content.innerHTML = items
          .map((item, index) => {
            const email = item.email
              ? ` <span class="has-text-grey">${item.email}</span>`
              : "";
            const source = item.source
              ? ` <span class="tag email-recipient-badge ml-2">${item.source}</span>`
              : "";
            return `<a class="dropdown-item" data-index="${index}" data-id="${item.id}" data-type="${item.type}" data-name="${item.name}" data-label="${item.label}" data-email="${item.email ?? ""}">${item.label}${email}${source}</a>`;
          })
          .join("");
      }
      menu.classList.remove("is-hidden");
      lookup.classList.add("is-active");
    };

    const performSearch = async (query: string): Promise<void> => {
      const requestId = ++activeRequest;
      if (query.length < 2) {
        clearResults();
        return;
      }

      try {
        const response = await fetch(
          `${lookupUrl}?q=${encodeURIComponent(query)}`,
        );
        if (!response.ok) {
          clearResults();
          return;
        }
        const data = (await response.json()) as { items: RecipientItem[] };
        if (requestId !== activeRequest) {
          return;
        }
        showResults(data.items);
      } catch {
        clearResults();
      }
    };

    const appendSelection = (item: RecipientItem): void => {
      const email = item.email || "";
      if (email === "") {
        return;
      }
      const updated = replaceLastEmailToken(input.value, email);
      input.value = normalizeEmailList(updated);
      clearResults();
      validateEmailInput(input);
    };

    input.addEventListener("input", () => {
      if (debounceId) {
        window.clearTimeout(debounceId);
      }
      const term = getLastEmailToken(input.value);
      debounceId = window.setTimeout(() => {
        void performSearch(term);
      }, 250);
    });

    input.addEventListener("blur", () => {
      window.setTimeout(() => {
        clearResults();
      }, 150);
    });

    content.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement).closest(
        ".dropdown-item",
      ) as HTMLElement | null;
      if (!target || !target.dataset.index) {
        return;
      }

      const index = Number(target.dataset.index);
      const item = Number.isNaN(index) ? undefined : currentItems[index];
      if (!item) {
        return;
      }

      appendSelection(item);
      if (item.id && item.type && item.name) {
        addLinkItem({
          id: item.id,
          type: item.type,
          name: item.name,
        });
      }
    });

    input.addEventListener("keydown", (event) => {
      if (!currentItems.length) {
        return;
      }

      switch (event.key) {
        case "ArrowDown":
          event.preventDefault();
          selectItem(
            selectedIndex < currentItems.length - 1 ? selectedIndex + 1 : 0,
          );
          break;
        case "ArrowUp":
          event.preventDefault();
          selectItem(
            selectedIndex > 0 ? selectedIndex - 1 : currentItems.length - 1,
          );
          break;
        case "Enter": {
          event.preventDefault();
          const index = selectedIndex >= 0 ? selectedIndex : 0;
          const item = currentItems[index];
          if (!item) {
            return;
          }
          appendSelection(item);
          if (item.id && item.type && item.name) {
            addLinkItem({
              id: item.id,
              type: item.type,
              name: item.name,
            });
          }
          break;
        }
        case "Escape":
          clearResults();
          break;
      }
    });

    document.addEventListener("click", (event) => {
      const target = event.target as Node;
      if (!lookup.contains(target)) {
        clearResults();
      }
    });
  });
};

const bindWysiEditor = (): void => {
  initWysiEditor();
  initWysiPasteSanitizer();
  initQuoteToggle();
  initEmailValidation();
  initRecipientLookup();
  initLinkList();
  initMailboxSwitch();
  initRecipientToggle();
  initSendMenu();
  initScheduleModal();
  document.addEventListener("tab:activated", () => {
    initWysiEditor();
    initWysiPasteSanitizer();
    initQuoteToggle();
    initEmailValidation();
    initRecipientLookup();
    initLinkList();
    initMailboxSwitch();
    initRecipientToggle();
    initSendMenu();
    initScheduleModal();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  bindWysiEditor();
});

document.addEventListener("htmx:afterSwap", (event) => {
  const target = (event as CustomEvent<{ target: HTMLElement }>).detail?.target ?? null;
  if (!target) {
    return;
  }
  if (target.matches("[data-email-detail]") || target.querySelector("[data-email-detail]")) {
    initQuoteToggle();
  }
  if (
    target.matches("[data-email-compose-form]") ||
    target.querySelector("[data-email-compose-form]")
  ) {
    initWysiEditor();
    initWysiPasteSanitizer();
    initEmailValidation();
    initRecipientLookup();
    initLinkList();
    initMailboxSwitch();
    initRecipientToggle();
    initSendMenu();
    initScheduleModal();
  }
});
