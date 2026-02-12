"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const initLinkEditorModal = () => {
    var _a, _b, _c, _d, _e, _f;
    const modal = document.querySelector("[data-link-editor-modal]");
    const trigger = document.querySelector("[data-link-editor-trigger]");
    const editor = document.querySelector("[data-link-editor]");
    if (!modal || !trigger || !editor) {
        return;
    }
    const searchUrl = (_a = editor.dataset.searchUrl) !== null && _a !== void 0 ? _a : "";
    const saveUrl = (_b = editor.dataset.saveUrl) !== null && _b !== void 0 ? _b : "";
    const csrfToken = (_c = editor.dataset.csrfToken) !== null && _c !== void 0 ? _c : "";
    const sourceType = (_d = editor.dataset.sourceType) !== null && _d !== void 0 ? _d : "";
    const sourceId = Number((_e = editor.dataset.sourceId) !== null && _e !== void 0 ? _e : 0);
    const mailboxId = Number((_f = editor.dataset.mailboxId) !== null && _f !== void 0 ? _f : 0);
    const tagsContainer = editor.querySelector("[data-link-editor-tags]");
    const searchInput = editor.querySelector("[data-link-editor-search]");
    const resultsContainer = editor.querySelector("[data-link-editor-results]");
    const dropdown = editor.querySelector("[data-link-editor-dropdown]");
    const conversationTag = editor.querySelector("[data-link-editor-conversation-tag]");
    const conversationSearch = editor.querySelector("[data-link-editor-conversation-search]");
    const conversationResults = editor.querySelector("[data-link-editor-conversation-results]");
    const conversationDropdown = editor.querySelector("[data-link-editor-conversation-dropdown]");
    const saveButton = modal.querySelector("[data-link-editor-save]");
    const errorEl = editor.querySelector("[data-link-editor-error]");
    if (!tagsContainer || !searchInput || !resultsContainer || !dropdown || !saveButton) {
        return;
    }
    let state = {
        links: [],
        conversationId: null,
        conversationLabel: "",
        detachConversation: false,
    };
    let linkDebounce = null;
    let convDebounce = null;
    const loadInitialState = () => {
        var _a, _b, _c;
        try {
            state.links = JSON.parse((_a = editor.dataset.links) !== null && _a !== void 0 ? _a : "[]");
        }
        catch (_d) {
            state.links = [];
        }
        const cid = (_b = editor.dataset.conversationId) !== null && _b !== void 0 ? _b : "";
        state.conversationId = cid !== "" ? Number(cid) : null;
        state.conversationLabel = (_c = editor.dataset.conversationLabel) !== null && _c !== void 0 ? _c : "";
        state.detachConversation = false;
    };
    const typeIcon = (type) => {
        if (type === "contact")
            return "fa-solid fa-user";
        if (type === "venue")
            return "fa-solid fa-location-dot";
        return "fa-solid fa-link";
    };
    const renderTags = () => {
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
    const renderConversationTag = () => {
        if (!conversationTag)
            return;
        conversationTag.innerHTML = "";
        if (state.conversationId !== null && state.conversationLabel !== "") {
            conversationTag.innerHTML = `<div class="tags has-addons"><span class="tag"><span class="icon is-small mr-1"><i class="fa-solid fa-comments"></i></span>${escapeHtml(state.conversationLabel)}</span><a class="tag is-delete" data-detach-conversation></a></div>`;
        }
        else {
            conversationTag.innerHTML = '<span class="is-size-7 has-text-grey">Not assigned to a conversation</span>';
        }
    };
    const escapeHtml = (text) => {
        const el = document.createElement("span");
        el.textContent = text;
        return el.innerHTML;
    };
    const showDropdown = (container) => {
        container.classList.add("is-active");
    };
    const hideDropdown = (container) => {
        container.classList.remove("is-active");
    };
    const performSearch = (query, types, container, dropdownEl, onSelect) => __awaiter(void 0, void 0, void 0, function* () {
        if (query.length < 2) {
            hideDropdown(dropdownEl);
            return;
        }
        const params = new URLSearchParams({ q: query, types });
        if (mailboxId > 0) {
            params.set("mailbox_id", String(mailboxId));
        }
        try {
            const response = yield fetch(`${searchUrl}?${params.toString()}`);
            if (!response.ok) {
                hideDropdown(dropdownEl);
                return;
            }
            const data = (yield response.json());
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
            container.querySelectorAll("[data-result-index]").forEach((el) => {
                el.addEventListener("click", (e) => {
                    var _a;
                    e.preventDefault();
                    const index = Number((_a = el.dataset.resultIndex) !== null && _a !== void 0 ? _a : -1);
                    const item = data.items[index];
                    if (item) {
                        onSelect(item);
                    }
                    hideDropdown(dropdownEl);
                });
            });
        }
        catch (_a) {
            hideDropdown(dropdownEl);
        }
    });
    // Event: open modal
    trigger.addEventListener("click", (e) => {
        e.preventDefault();
        loadInitialState();
        renderTags();
        renderConversationTag();
        searchInput.value = "";
        if (conversationSearch)
            conversationSearch.value = "";
        if (errorEl)
            errorEl.classList.add("is-hidden");
        modal.classList.add("is-active");
    });
    // Event: close modal
    modal.querySelectorAll("[data-link-editor-close]").forEach((el) => {
        el.addEventListener("click", () => {
            modal.classList.remove("is-active");
        });
    });
    // Event: remove link tag
    tagsContainer.addEventListener("click", (e) => {
        var _a;
        const target = e.target;
        const removeIndex = (_a = target.closest("[data-remove-link]")) === null || _a === void 0 ? void 0 : _a.dataset.removeLink;
        if (removeIndex === undefined)
            return;
        state.links.splice(Number(removeIndex), 1);
        renderTags();
    });
    // Event: detach conversation
    if (conversationTag) {
        conversationTag.addEventListener("click", (e) => {
            const target = e.target;
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
        if (linkDebounce)
            window.clearTimeout(linkDebounce);
        linkDebounce = window.setTimeout(() => {
            void performSearch(searchInput.value.trim(), "contact,venue", resultsContainer, dropdown, (item) => {
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
            if (convDebounce)
                window.clearTimeout(convDebounce);
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
        if (errorEl)
            errorEl.classList.add("is-hidden");
        saveButton.classList.add("is-loading");
        const payload = {
            csrf_token: csrfToken,
            source_type: sourceType,
            source_id: sourceId,
            mailbox_id: mailboxId,
            links: state.links.map((l) => ({ type: l.type, id: l.id })),
        };
        if (sourceType === "email") {
            if (state.detachConversation) {
                payload.detach_conversation = true;
            }
            else if (state.conversationId !== null) {
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
            .then((response) => __awaiter(void 0, void 0, void 0, function* () {
            if (!response.ok) {
                const text = yield response.text().catch(() => "");
                throw new Error(text || `HTTP ${response.status}`);
            }
            return response.json();
        }))
            .then(() => {
            modal.classList.remove("is-active");
            window.location.reload();
        })
            .catch((err) => {
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
document.addEventListener("DOMContentLoaded", () => {
    initLinkEditorModal();
});
document.addEventListener("tab:activated", () => {
    initLinkEditorModal();
});
