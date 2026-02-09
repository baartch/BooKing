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
const initWysiEditor = () => {
    const textarea = document.querySelector("#email_body");
    if (!textarea) {
        return;
    }
    const wysi = window;
    if (typeof wysi.Wysi !== "function") {
        return;
    }
    const darkModeMql = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
    const prefersDarkMode = darkModeMql && darkModeMql.matches;
    wysi.Wysi({
        el: "#email_body",
        darkMode: prefersDarkMode,
    });
};
const isValidEmail = (email) => {
    if (email === "") {
        return true;
    }
    return /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(email);
};
const normalizeEmailList = (value) => {
    const trimmed = value.trim();
    if (trimmed === "") {
        return "";
    }
    const parts = trimmed.split(/[;,]+/).map((item) => item.trim());
    const cleaned = parts.filter((part) => part !== "");
    return cleaned.join(", ");
};
const getLastEmailToken = (value) => {
    var _a;
    const parts = value.split(/[;,]/);
    return ((_a = parts[parts.length - 1]) !== null && _a !== void 0 ? _a : "").trim();
};
const replaceLastEmailToken = (value, email) => {
    const parts = value.split(/[;,]/).map((item) => item.trim());
    const prefix = parts.slice(0, -1).filter((part) => part !== "");
    return [...prefix, email].join(", ");
};
const validateEmailInput = (input) => {
    var _a, _b;
    const raw = input.value.trim();
    const parts = raw === "" ? [] : raw.split(/[;,]+/).map((item) => item.trim());
    const invalid = parts.some((part) => part !== "" && !isValidEmail(part));
    input.classList.toggle("is-danger", invalid);
    const field = input.closest(".field");
    const help = (_a = field === null || field === void 0 ? void 0 : field.querySelector("[data-email-help]")) !== null && _a !== void 0 ? _a : null;
    const icon = (_b = field === null || field === void 0 ? void 0 : field.querySelector("[data-email-icon]")) !== null && _b !== void 0 ? _b : null;
    if (help) {
        help.classList.toggle("is-hidden", !invalid);
    }
    if (icon) {
        icon.classList.toggle("is-hidden", !invalid);
    }
    return !invalid;
};
const initEmailValidation = () => {
    const inputs = Array.from(document.querySelectorAll("[data-email-input]"));
    if (!inputs.length) {
        return;
    }
    inputs.forEach((input) => {
        if (input.dataset.emailValidationBound === "true") {
            return;
        }
        input.dataset.emailValidationBound = "true";
        const handleChange = () => {
            validateEmailInput(input);
        };
        input.addEventListener("input", handleChange);
        input.addEventListener("blur", handleChange);
        handleChange();
    });
};
const initRecipientLookup = () => {
    const lookups = Array.from(document.querySelectorAll("[data-email-lookup]"));
    lookups.forEach((lookup) => {
        var _a;
        if (lookup.dataset.lookupBound === "true") {
            return;
        }
        lookup.dataset.lookupBound = "true";
        const input = lookup.querySelector("[data-email-input]");
        const menu = lookup.querySelector(".dropdown-menu");
        const content = lookup.querySelector(".dropdown-content");
        const lookupUrl = (_a = lookup.dataset.lookupUrl) !== null && _a !== void 0 ? _a : "";
        if (!input || !menu || !content || lookupUrl === "") {
            return;
        }
        let activeRequest = 0;
        let debounceId = null;
        let currentItems = [];
        let selectedIndex = -1;
        const clearResults = () => {
            content.innerHTML = "";
            menu.classList.add("is-hidden");
            lookup.classList.remove("is-active");
            currentItems = [];
            selectedIndex = -1;
        };
        const selectItem = (index) => {
            const items = Array.from(content.querySelectorAll(".dropdown-item"));
            items.forEach((item) => item.classList.remove("is-active"));
            if (index >= 0 && index < items.length) {
                selectedIndex = index;
                items[index].classList.add("is-active");
                items[index].scrollIntoView({ block: "nearest" });
                return;
            }
            selectedIndex = -1;
        };
        const showResults = (items) => {
            currentItems = items;
            selectedIndex = -1;
            if (!items.length) {
                content.innerHTML = '<div class="dropdown-item">No results found</div>';
            }
            else {
                content.innerHTML = items
                    .map((item, index) => {
                    var _a;
                    const email = item.email ? ` <span class="has-text-grey">${item.email}</span>` : "";
                    const source = item.source ? ` <span class="tag is-light ml-2">${item.source}</span>` : "";
                    return `<a class="dropdown-item" data-index="${index}" data-label="${item.label}" data-email="${(_a = item.email) !== null && _a !== void 0 ? _a : ''}">${item.label}${email}${source}</a>`;
                })
                    .join("");
            }
            menu.classList.remove("is-hidden");
            lookup.classList.add("is-active");
        };
        const performSearch = (query) => __awaiter(void 0, void 0, void 0, function* () {
            const requestId = ++activeRequest;
            if (query.length < 2) {
                clearResults();
                return;
            }
            try {
                const response = yield fetch(`${lookupUrl}?q=${encodeURIComponent(query)}`);
                if (!response.ok) {
                    clearResults();
                    return;
                }
                const data = (yield response.json());
                if (requestId !== activeRequest) {
                    return;
                }
                showResults(data.items);
            }
            catch (_a) {
                clearResults();
            }
        });
        const appendSelection = (item) => {
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
            const target = event.target.closest(".dropdown-item");
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
                    selectItem(selectedIndex < currentItems.length - 1 ? selectedIndex + 1 : 0);
                    break;
                case "ArrowUp":
                    event.preventDefault();
                    selectItem(selectedIndex > 0 ? selectedIndex - 1 : currentItems.length - 1);
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
            const target = event.target;
            if (!lookup.contains(target)) {
                clearResults();
            }
        });
    });
};
const bindWysiEditor = () => {
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
