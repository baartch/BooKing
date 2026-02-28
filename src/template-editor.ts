import { getStoredTheme } from "./appearance.js";

const initTemplateEditor = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>(
    "#template_body",
  );

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
    el: "#template_body",
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

const initTemplatePasteSanitizer = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>("#template_body");
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

document.addEventListener("DOMContentLoaded", () => {
  initTemplateEditor();
  initTemplatePasteSanitizer();
});
