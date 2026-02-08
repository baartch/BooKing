type ParentItem = {
  type: string;
  id: number;
  label: string;
  secondary?: string;
  email?: string;
};

type LookupResponse = {
  items: ParentItem[];
};

const createList = (): HTMLDivElement => {
  const list = document.createElement("div");
  list.className = "dropdown-menu";
  list.setAttribute("role", "menu");
  list.innerHTML = "<div class=\"dropdown-content\"></div>";
  return list;
};

const buildItem = (item: ParentItem): HTMLAnchorElement => {
  const link = document.createElement("a");
  link.className = "dropdown-item";
  link.href = "#";
  const secondary = item.secondary ? `<span class=\"has-text-grey is-size-7\">${item.secondary}</span>` : "";
  link.innerHTML = `<strong>${item.label}</strong>${secondary !== "" ? `<br>${secondary}` : ""}`;
  link.dataset.type = item.type;
  link.dataset.id = String(item.id);
  link.dataset.label = item.label;
  return link;
};

const initParentLookup = (): void => {
  const fields = Array.from(
    document.querySelectorAll<HTMLElement>("[data-parent-lookup]")
  );

  fields.forEach((field) => {
    const input = field.querySelector<HTMLInputElement>("[data-parent-input]");
    const hiddenType = field.querySelector<HTMLInputElement>("[data-parent-type]");
    const hiddenId = field.querySelector<HTMLInputElement>("[data-parent-id]");
    const dropdown = field.querySelector<HTMLDivElement>(".dropdown");
    const lookupUrl = field.dataset.lookupUrl ?? "";
    const mode = field.dataset.lookupMode ?? "parent";

    if (!input || !dropdown || lookupUrl === "") {
      return;
    }

    const menu = createList();
    dropdown.appendChild(menu);
    const content = menu.querySelector<HTMLDivElement>(".dropdown-content");

    if (!content) {
      return;
    }

    const closeDropdown = (): void => {
      dropdown.classList.remove("is-active");
      content.innerHTML = "";
    };

    const openDropdown = (): void => {
      dropdown.classList.add("is-active");
    };

    const setSelection = (item: ParentItem): void => {
      if (mode === "recipient") {
        input.value = item.email ?? item.secondary ?? item.label;
      } else {
        input.value = item.label;
        if (hiddenType && hiddenId) {
          hiddenType.value = item.type;
          hiddenId.value = String(item.id);
        }
      }
      closeDropdown();
    };

    const clearSelection = (): void => {
      if (hiddenType && hiddenId) {
        hiddenType.value = "";
        hiddenId.value = "";
      }
    };

    const fetchItems = async (query: string): Promise<void> => {
      const response = await fetch(
        `${lookupUrl}?q=${encodeURIComponent(query)}`
      );
      if (!response.ok) {
        closeDropdown();
        return;
      }
      const data = (await response.json()) as LookupResponse;
      content.innerHTML = "";
      if (!data.items.length) {
        closeDropdown();
        return;
      }
      data.items.forEach((item) => {
        const link = buildItem(item);
        link.addEventListener("click", (event) => {
          event.preventDefault();
          setSelection(item);
        });
        content.appendChild(link);
      });
      openDropdown();
    };

    let debounceId: number | undefined;
    input.addEventListener("input", () => {
      if (mode === "parent") {
        clearSelection();
      }
      const query = input.value.trim();
      if (query.length < 2) {
        closeDropdown();
        return;
      }
      window.clearTimeout(debounceId);
      debounceId = window.setTimeout(() => {
        fetchItems(query).catch(() => closeDropdown());
      }, 250);
    });

    input.addEventListener("blur", () => {
      window.setTimeout(() => closeDropdown(), 150);
    });

    document.addEventListener("click", (event) => {
      if (!dropdown.contains(event.target as Node)) {
        closeDropdown();
      }
    });
  });
};

document.addEventListener("DOMContentLoaded", initParentLookup);
