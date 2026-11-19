import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

const renderImmediateSaveModal = () => {
  document.body.innerHTML = `
    <form>
      <div data-link-editor-collector><input type="hidden" name="link_items[]" value="old:1"></div>
    </form>
    <a href="#" data-link-editor-trigger data-link-editor-modal-id="link-editor-contact-11"></a>
    <div class="modal" data-link-editor-modal id="link-editor-contact-11">
      <div data-link-editor data-source-type="contact" data-source-id="11" data-mailbox-id="23"
        data-save-url="/links/save" data-csrf-token="csrf-token"
        data-links='[{"type":"venue","id":8,"label":"Hall"}]' data-link-editor-types="contact,venue">
        <div data-link-editor-tags></div>
        <input data-link-editor-search>
        <div data-link-editor-dropdown><div data-link-editor-results></div></div>
        <p class="help is-danger is-hidden" data-link-editor-error></p>
      </div>
      <button type="button" data-link-editor-save></button>
      <button type="button" data-link-editor-close></button>
    </div>`;
};

const waitForSave = async () => new Promise((resolve) => setTimeout(resolve, 0));

test("immediate-save POST preserves source, mailbox, CSRF, and links before closing and reloading", async () => {
  const originalFetch = globalThis.fetch;
  const originalReload = Object.getOwnPropertyDescriptor(window.location, "reload");
  const calls = [];
  let reloads = 0;
  globalThis.fetch = async (...args) => {
    calls.push(args);
    return new Response(JSON.stringify({ ok: true }), { status: 200 });
  };
  Object.defineProperty(window.location, "reload", { configurable: true, value: () => reloads++ });

  try {
    renderImmediateSaveModal();
    await import("../src/link-editor.ts");
    document.querySelector("[data-link-editor-trigger]").click();
    const modal = document.querySelector("[data-link-editor-modal]");
    modal.querySelector("[data-link-editor-save]").click();
    await waitForSave();

    expect(calls).toHaveLength(1);
    expect(calls[0][0]).toBe("/links/save");
    expect(calls[0][1].method).toBe("POST");
    expect(calls[0][1].headers["X-CSRF-Token"]).toBe("csrf-token");
    expect(JSON.parse(calls[0][1].body)).toEqual({
      csrf_token: "csrf-token",
      source_type: "contact",
      source_id: 11,
      mailbox_id: 23,
      links: [{ type: "venue", id: 8 }],
    });
    expect(modal.classList.contains("is-active")).toBe(false);
    expect(reloads).toBe(1);
  } finally {
    globalThis.fetch = originalFetch;
    if (originalReload) Object.defineProperty(window.location, "reload", originalReload);
    document.body.innerHTML = "";
  }
});

test("failed immediate-save POST keeps the modal open and displays an error without reloading", async () => {
  const originalFetch = globalThis.fetch;
  const originalReload = Object.getOwnPropertyDescriptor(window.location, "reload");
  let reloads = 0;
  globalThis.fetch = async () => new Response(JSON.stringify({ error: "denied" }), { status: 403 });
  Object.defineProperty(window.location, "reload", { configurable: true, value: () => reloads++ });

  try {
    renderImmediateSaveModal();
    await import("../src/link-editor.ts");
    document.querySelector("[data-link-editor-trigger]").click();
    const modal = document.querySelector("[data-link-editor-modal]");
    modal.querySelector("[data-link-editor-save]").click();
    await waitForSave();

    const error = modal.querySelector("[data-link-editor-error]");
    expect(modal.classList.contains("is-active")).toBe(true);
    expect(error.classList.contains("is-hidden")).toBe(false);
    expect(error.textContent).toBe("Failed to save links. Please try again.");
    expect(reloads).toBe(0);
  } finally {
    globalThis.fetch = originalFetch;
    if (originalReload) Object.defineProperty(window.location, "reload", originalReload);
    document.body.innerHTML = "";
  }
});
