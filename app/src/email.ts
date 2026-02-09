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

const validateEmailInput = (input: HTMLInputElement): void => {
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
};

const initEmailValidation = (): void => {
  const inputs = Array.from(
    document.querySelectorAll<HTMLInputElement>("[data-email-input]")
  );
  if (!inputs.length) {
    return;
  }

  inputs.forEach((input) => {
    const handleChange = (): void => {
      validateEmailInput(input);
    };

    input.addEventListener("input", handleChange);
    input.addEventListener("blur", handleChange);
    handleChange();
  });
};

const bindWysiEditor = (): void => {
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
