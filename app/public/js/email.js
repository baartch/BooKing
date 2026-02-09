"use strict";
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
};
const initEmailValidation = () => {
    const inputs = Array.from(document.querySelectorAll("[data-email-input]"));
    if (!inputs.length) {
        return;
    }
    inputs.forEach((input) => {
        const handleChange = () => {
            validateEmailInput(input);
        };
        input.addEventListener("input", handleChange);
        input.addEventListener("blur", handleChange);
        handleChange();
    });
};
const bindWysiEditor = () => {
    initWysiEditor();
    initEmailValidation();
    document.addEventListener("tab:activated", () => {
        initWysiEditor();
        initEmailValidation();
    });
};
document.addEventListener("DOMContentLoaded", () => {
    bindWysiEditor();
});
