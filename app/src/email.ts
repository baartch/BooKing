const initWysiEditor = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>("#email_body");

  if (!textarea) {
    return;
  }

  const wysi = window as typeof window & {
    Wysi?: (options: { el: string; darkMode?: boolean }) => void;
  };

  if (typeof wysi.Wysi !== "function") {
    return;
  }

  const darkModeMql =
    window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
  const prefersDarkMode = darkModeMql && darkModeMql.matches;
  wysi.Wysi({
    el: "#email_body",
    darkMode: prefersDarkMode,
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
  label: string;
  email: string;
  source?: string;
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
              ? ` <span class="tag ml-2">${item.source}</span>`
              : "";
            return `<a class="dropdown-item" data-index="${index}" data-label="${item.label}" data-email="${item.email ?? ""}">${item.label}${email}${source}</a>`;
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
      if (Number.isNaN(index) || !currentItems[index]) {
        return;
      }

      appendSelection(currentItems[index]);
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
          if (!currentItems[index]) {
            return;
          }
          appendSelection(currentItems[index]);
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
  initEmailValidation();
  initRecipientLookup();
  document.addEventListener("tab:activated", () => {
    initWysiEditor();
    initEmailValidation();
    initRecipientLookup();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  bindWysiEditor();
});
