type LinkEditorItem = {
  type: string;
  id: number;
  label: string;
};

type LinkEditorSearchResult = {
  type: string;
  id: number;
  label: string;
  detail: string;
};

type LinkEditorState = {
  links: LinkEditorItem[];
  conversationId: number | null;
  conversationLabel: string;
  detachConversation: boolean;
};

const initLinkEditorModal = (modal: HTMLElement): void => {
  if (modal.dataset.linkEditorInitialized === "1") {
    return;
  }
  const trigger = document.querySelector<HTMLElement>(`[data-link-editor-trigger][data-link-editor-modal-id="${modal.id}"]`);
  if (!trigger) {
    return;
  }

  trigger.addEventListener("click", (event) => {
    event.preventDefault();
  });
  const editor = modal.querySelector<HTMLElement>("[data-link-editor]");
  if (!editor) {
    return;
  }
  modal.dataset.linkEditorInitialized = "1";

  const searchUrl = editor.dataset.searchUrl ?? "";
  const saveUrl = editor.dataset.saveUrl ?? "";
  const csrfToken = editor.dataset.csrfToken ?? "";
  const sourceType = editor.dataset.sourceType ?? "";
  const sourceId = Number(editor.dataset.sourceId ?? 0);
  const mailboxId = Number(editor.dataset.mailboxId ?? 0);
  const searchTypes = editor.dataset.linkEditorTypes ?? "contact,venue";

  const tagsContainer = editor.querySelector<HTMLElement>("[data-link-editor-tags]");
  const searchInput = editor.querySelector<HTMLInputElement>("[data-link-editor-search]");
  const resultsContainer = editor.querySelector<HTMLElement>("[data-link-editor-results]");
  const dropdown = editor.querySelector<HTMLElement>("[data-link-editor-dropdown]");

  const conversationTag = editor.querySelector<HTMLElement>("[data-link-editor-conversation-tag]");
  const conversationSearch = editor.querySelector<HTMLInputElement>("[data-link-editor-conversation-search]");
  const conversationResults = editor.querySelector<HTMLElement>("[data-link-editor-conversation-results]");
  const conversationDropdown = editor.querySelector<HTMLElement>("[data-link-editor-conversation-dropdown]");

  const saveButton = modal.querySelector<HTMLButtonElement>("[data-link-editor-save]");
  const errorEl = editor.querySelector<HTMLElement>("[data-link-editor-error]");

  if (!tagsContainer || !searchInput || !resultsContainer || !dropdown || !saveButton) {
    return;
  }

  let state: LinkEditorState = {
    links: [],
    conversationId: null,
    conversationLabel: "",
    detachConversation: false,
  };

  let linkDebounce: number | null = null;
  let convDebounce: number | null = null;

  const loadInitialState = (): void => {
    try {
      state.links = JSON.parse(editor.dataset.links ?? "[]") as LinkEditorItem[];
    } catch {
      state.links = [];
    }
    const cid = editor.dataset.conversationId ?? "";
    state.conversationId = cid !== "" ? Number(cid) : null;
    state.conversationLabel = editor.dataset.conversationLabel ?? "";
    state.detachConversation = false;
  };

  const typeIcon = (type: string): string => {
    if (type === "contact") return "fa-solid fa-user";
    if (type === "venue") return "fa-solid fa-location-dot";
    if (type === "email") return "fa-solid fa-envelope";
    return "fa-solid fa-link";
  };

  const renderTags = (): void => {
    tagsContainer.innerHTML = "";
    if (state.links.length === 0) {
      tagsContainer.innerHTML = '<span class="is-size-7 has-text-grey">No links yet</span>';
      return;
    }
    state.links.forEach((link, index) => {
      const tag = document.createElement("div");
      tag.className = "control";
      tag.innerHTML = `<div class="tags has-addons"><span class="tag"><span class="icon is-small mr-1"><i class="${typeIcon(link.type)}"></i></span>${escapeHtml(link.label)}</span><a class="tag is-delete" data-remove-link="${index}"></a></div>`;
      tagsContainer.appendChild(tag);
    });
  };

  const renderConversationTag = (): void => {
    if (!conversationTag) return;
    conversationTag.innerHTML = "";
    if (state.conversationId !== null && state.conversationLabel !== "") {
      conversationTag.innerHTML = `<div class="tags has-addons"><span class="tag"><span class="icon is-small mr-1"><i class="fa-solid fa-comments"></i></span>${escapeHtml(state.conversationLabel)}</span><a class="tag is-delete" data-detach-conversation></a></div>`;
    } else {
      conversationTag.innerHTML = '<span class="is-size-7 has-text-grey">Not assigned to a conversation</span>';
    }
  };

  const escapeHtml = (text: string): string => {
    const el = document.createElement("span");
    el.textContent = text;
    return el.innerHTML;
  };

  const showDropdown = (container: HTMLElement): void => {
    container.classList.add("is-active");
  };

  const hideDropdown = (container: HTMLElement): void => {
    container.classList.remove("is-active");
  };

  const performSearch = async (query: string, types: string, container: HTMLElement, dropdownEl: HTMLElement, onSelect: (item: LinkEditorSearchResult) => void): Promise<void> => {
    if (query.length < 2) {
      hideDropdown(dropdownEl);
      return;
    }

    const params = new URLSearchParams({ q: query, types });
    if (mailboxId > 0) {
      params.set("mailbox_id", String(mailboxId));
    }

    try {
      const response = await fetch(`${searchUrl}?${params.toString()}`);
      if (!response.ok) {
        hideDropdown(dropdownEl);
        return;
      }
      const data = (await response.json()) as { items: LinkEditorSearchResult[] };
      if (!data.items.length) {
        container.innerHTML = '<div class="dropdown-item has-text-grey is-size-7">No results</div>';
        showDropdown(dropdownEl);
        return;
      }
      container.innerHTML = data.items
        .map((item, idx) => {
          const detail = item.detail ? ` <span class="has-text-grey is-size-7">${escapeHtml(item.detail)}</span>` : "";
          return `<a class="dropdown-item is-size-7" data-result-index="${idx}"><span class="icon is-small mr-1"><i class="${typeIcon(item.type)}"></i></span>${escapeHtml(item.label)}${detail}</a>`;
        })
        .join("");
      showDropdown(dropdownEl);

      container.querySelectorAll<HTMLElement>("[data-result-index]").forEach((el) => {
        el.addEventListener("click", (e) => {
          e.preventDefault();
          const index = Number(el.dataset.resultIndex ?? -1);
          const item = data.items[index];
          if (item) {
            onSelect(item);
          }
          hideDropdown(dropdownEl);
        });
      });
    } catch {
      hideDropdown(dropdownEl);
    }
  };

  // Event: open modal
  trigger.addEventListener("click", (e) => {
    e.preventDefault();
    loadInitialState();
    renderTags();
    renderConversationTag();
    searchInput.value = "";
    if (conversationSearch) conversationSearch.value = "";
    if (errorEl) errorEl.classList.add("is-hidden");
    modal.classList.add("is-active");
  });

  // Event: close modal
  modal.querySelectorAll<HTMLElement>("[data-link-editor-close]").forEach((el) => {
    el.addEventListener("click", () => {
      modal.classList.remove("is-active");
    });
  });

  // Event: remove link tag
  tagsContainer.addEventListener("click", (e) => {
    const target = e.target as HTMLElement;
    const removeIndex = target.closest<HTMLElement>("[data-remove-link]")?.dataset.removeLink;
    if (removeIndex === undefined) return;
    state.links.splice(Number(removeIndex), 1);
    renderTags();
  });

  // Event: detach conversation
  if (conversationTag) {
    conversationTag.addEventListener("click", (e) => {
      const target = e.target as HTMLElement;
      if (target.closest("[data-detach-conversation]")) {
        state.conversationId = null;
        state.conversationLabel = "";
        state.detachConversation = true;
        renderConversationTag();
      }
    });
  }

  // Event: search contacts/venues
  searchInput.addEventListener("input", () => {
    if (linkDebounce) window.clearTimeout(linkDebounce);
    linkDebounce = window.setTimeout(() => {
      void performSearch(searchInput.value.trim(), searchTypes, resultsContainer, dropdown, (item) => {
        const exists = state.links.some((l) => l.type === item.type && l.id === item.id);
        if (!exists) {
          state.links.push({ type: item.type, id: item.id, label: item.label });
          renderTags();
        }
        searchInput.value = "";
      });
    }, 250);
  });

  searchInput.addEventListener("blur", () => {
    setTimeout(() => hideDropdown(dropdown), 150);
  });

  // Event: search conversations
  if (conversationSearch && conversationResults && conversationDropdown) {
    conversationSearch.addEventListener("input", () => {
      if (convDebounce) window.clearTimeout(convDebounce);
      convDebounce = window.setTimeout(() => {
        void performSearch(conversationSearch.value.trim(), "conversation", conversationResults, conversationDropdown, (item) => {
          state.conversationId = item.id;
          state.conversationLabel = item.label;
          state.detachConversation = false;
          renderConversationTag();
          conversationSearch.value = "";
        });
      }, 250);
    });

    conversationSearch.addEventListener("blur", () => {
      setTimeout(() => hideDropdown(conversationDropdown), 150);
    });
  }

  // Event: save
  saveButton.addEventListener("click", () => {
    if (errorEl) errorEl.classList.add("is-hidden");
    saveButton.classList.add("is-loading");

    const payload: Record<string, unknown> = {
      csrf_token: csrfToken,
      source_type: sourceType,
      source_id: sourceId,
      mailbox_id: mailboxId,
      links: state.links.map((l) => ({ type: l.type, id: l.id })),
    };

    if (sourceType === "email") {
      if (state.detachConversation) {
        payload.detach_conversation = true;
      } else if (state.conversationId !== null) {
        payload.conversation_id = state.conversationId;
      }
    }

    fetch(saveUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(payload),
    })
      .then(async (response) => {
        if (!response.ok) {
          const text = await response.text().catch(() => "");
          throw new Error(text || `HTTP ${response.status}`);
        }
        return response.json() as Promise<{ ok: boolean }>;
      })
      .then(() => {
        modal.classList.remove("is-active");
        window.location.reload();
      })
      .catch((err: Error) => {
        if (errorEl) {
          const message = err.message && err.message.includes("{")
            ? "Failed to save links. Please try again."
            : err.message || "Failed to save links. Please try again.";
          errorEl.textContent = message;
          errorEl.classList.remove("is-hidden");
        }
      })
      .finally(() => {
        saveButton.classList.remove("is-loading");
      });
  });
};

const initLinkEditorModals = (scope: ParentNode = document): void => {
  scope.querySelectorAll<HTMLElement>("[data-link-editor-modal]").forEach((modal) => {
    if (!modal.id) {
      return;
    }
    initLinkEditorModal(modal);
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initLinkEditorModals();
});

document.addEventListener("tab:activated", () => {
  initLinkEditorModals();
});

document.addEventListener("htmx:afterSwap", (event) => {
  const target = (event as CustomEvent<{ target: HTMLElement }>).detail?.target ?? null;
  if (target) {
    initLinkEditorModals(target);
  }
});
