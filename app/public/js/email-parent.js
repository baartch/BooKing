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
const createList = () => {
    const list = document.createElement("div");
    list.className = "dropdown-menu";
    list.setAttribute("role", "menu");
    list.innerHTML = "<div class=\"dropdown-content\"></div>";
    return list;
};
const buildItem = (item) => {
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
const initParentLookup = () => {
    const fields = Array.from(document.querySelectorAll("[data-parent-lookup]"));
    fields.forEach((field) => {
        var _a, _b;
        const input = field.querySelector("[data-parent-input]");
        const hiddenType = field.querySelector("[data-parent-type]");
        const hiddenId = field.querySelector("[data-parent-id]");
        const dropdown = field.querySelector(".dropdown");
        const lookupUrl = (_a = field.dataset.lookupUrl) !== null && _a !== void 0 ? _a : "";
        const mode = (_b = field.dataset.lookupMode) !== null && _b !== void 0 ? _b : "parent";
        if (!input || !dropdown || lookupUrl === "") {
            return;
        }
        const menu = createList();
        dropdown.appendChild(menu);
        const content = menu.querySelector(".dropdown-content");
        if (!content) {
            return;
        }
        const closeDropdown = () => {
            dropdown.classList.remove("is-active");
            content.innerHTML = "";
        };
        const openDropdown = () => {
            dropdown.classList.add("is-active");
        };
        const setSelection = (item) => {
            var _a, _b;
            if (mode === "recipient") {
                input.value = (_b = (_a = item.email) !== null && _a !== void 0 ? _a : item.secondary) !== null && _b !== void 0 ? _b : item.label;
            }
            else {
                input.value = item.label;
                if (hiddenType && hiddenId) {
                    hiddenType.value = item.type;
                    hiddenId.value = String(item.id);
                }
            }
            closeDropdown();
        };
        const clearSelection = () => {
            if (hiddenType && hiddenId) {
                hiddenType.value = "";
                hiddenId.value = "";
            }
        };
        const fetchItems = (query) => __awaiter(void 0, void 0, void 0, function* () {
            const response = yield fetch(`${lookupUrl}?q=${encodeURIComponent(query)}`);
            if (!response.ok) {
                closeDropdown();
                return;
            }
            const data = (yield response.json());
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
        });
        let debounceId;
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
            if (!dropdown.contains(event.target)) {
                closeDropdown();
            }
        });
    });
};
document.addEventListener("DOMContentLoaded", initParentLookup);
