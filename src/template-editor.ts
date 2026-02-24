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

document.addEventListener("DOMContentLoaded", () => {
  initTemplateEditor();
});
