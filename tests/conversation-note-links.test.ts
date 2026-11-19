import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { execFileSync } from "node:child_process";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

test("note link pills and dialog retain unsaved selections across reopening", async () => {
  const php = `
    define('BASE_PATH', '');
    function renderCsrfField(): void {}
    function getCsrfToken(): string { return 'token'; }
    $noteFormId = 'note-new';
    $conversationId = 3;
    $noteEdit = ['id' => 0, 'links' => [
      ['type' => 'contact', 'id' => 42, 'label' => 'Jane'],
      ['type' => 'venue', 'id' => 5, 'label' => 'Hall']
    ]];
    require 'app/views/communication/conversation_note_form.php';
  `;
  document.body.innerHTML = execFileSync("php", ["-r", php], { encoding: "utf8" });
  await import("../src/link-editor.ts");
  const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
  const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
  const save = modal.querySelector<HTMLElement>("[data-link-editor-save]")!;
  trigger.click();
  expect(modal.classList.contains("is-active")).toBe(true);
  modal.querySelector<HTMLElement>('[data-remove-link="0"]')!.click();
  save.click();
  expect(Array.from(document.querySelectorAll<HTMLInputElement>('input[name="link_items[]"]')).map((input) => input.value)).toEqual(["venue:5"]);
  expect(Array.from(document.querySelectorAll(".detail-link-pill")).map((pill) => pill.textContent?.trim())).toEqual(["Hall"]);
  trigger.click();
  expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Hall");
  expect(modal.querySelector("[data-link-editor-tags]")!.textContent).not.toContain("Jane");
  modal.querySelector<HTMLElement>('[data-remove-link="0"]')!.click();
  save.click();
  expect(document.querySelectorAll('input[name="link_items[]"]').length).toBe(0);
  expect(document.querySelectorAll(".detail-link-pill").length).toBe(0);
  expect(document.body.textContent).toContain("No links yet");
  document.body.innerHTML = "";
});

test("searching and selecting a show adds its pill and keeps it in the dialog", async () => {
  const php = `
    define('BASE_PATH', '');
    function renderCsrfField(): void {}
    function getCsrfToken(): string { return 'token'; }
    $noteFormId = 'note-new';
    $conversationId = 3;
    $noteEdit = null;
    require 'app/views/communication/conversation_note_form.php';
  `;
  document.body.innerHTML = execFileSync("php", ["-r", php], { encoding: "utf8" });
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response(JSON.stringify({ items: [{ type: "show", id: 8, label: "Summer <show>", detail: "" }] }), { status: 200 });
  try {
    const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
    const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
    trigger.click();
    const search = modal.querySelector<HTMLInputElement>("[data-link-editor-search]")!;
    search.value = "Summer";
    search.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 350));
    modal.querySelector<HTMLElement>("[data-result-index]")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();
    expect(document.querySelector<HTMLInputElement>('input[name="link_items[]"]')!.value).toBe("show:8");
    expect(document.querySelector(".detail-link-pill")!.textContent).toContain("Summer <show>");
    expect(document.querySelector(".detail-link-pill .fa-music")).not.toBeNull();
    trigger.click();
    expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Summer <show>");
  } finally {
    globalThis.fetch = originalFetch;
    document.body.innerHTML = "";
  }
});
