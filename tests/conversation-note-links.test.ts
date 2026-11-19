import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { execFileSync } from "node:child_process";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

type NoteFormFixture = {
  formId: string;
  noteEdit: { id: number; links: Array<{ type: string; id: number; label: string }> };
};

const renderNoteForms = (forms: NoteFormFixture[]): string => {
  const php = `
    define('BASE_PATH', '');
    function renderCsrfField(): void {}
    function getCsrfToken(): string { return 'token'; }
    $forms = json_decode(stream_get_contents(STDIN), true);
    $conversationId = 3;
    foreach ($forms as $entry) {
      $noteFormId = $entry['formId'];
      $noteEdit = $entry['noteEdit'];
      require 'app/views/communication/conversation_note_form.php';
    }
  `;
  return execFileSync("php", ["-r", php], { encoding: "utf8", input: JSON.stringify(forms) });
};

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

test("canceling note link edits leaves existing links unchanged", async () => {
  document.body.innerHTML = renderNoteForms([{
    formId: "note-cancel",
    noteEdit: { id: 10, links: [
      { type: "contact", id: 42, label: "Jane" },
      { type: "venue", id: 5, label: "Hall" },
    ] },
  }]);
  await import("../src/link-editor.ts");

  const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
  const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
  const saved: Event[] = [];
  document.addEventListener("link-editor:local-saved", (event) => saved.push(event));

  trigger.click();
  modal.querySelector<HTMLElement>('[data-remove-link="0"]')!.click();
  modal.querySelector<HTMLElement>("[data-link-editor-close]")!.click();

  expect(Array.from(document.querySelectorAll<HTMLInputElement>('input[name="link_items[]"]')).map((input) => input.value)).toEqual(["contact:42", "venue:5"]);
  expect(document.querySelector("#note-cancel-link-pills")!.textContent).toContain("Jane");
  expect(document.querySelector("#note-cancel-link-pills")!.textContent).toContain("Hall");
  expect(saved).toHaveLength(0);

  trigger.click();
  expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Jane");
  expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Hall");
  modal.querySelector<HTMLElement>("[data-link-editor-close]")!.click();
  document.body.innerHTML = "";
});

test("saving one note editor does not alter another editor's link associations", async () => {
  document.body.innerHTML = renderNoteForms([
    { formId: "note-first", noteEdit: { id: 21, links: [{ type: "contact", id: 42, label: "Jane" }] } },
    { formId: "note-second", noteEdit: { id: 22, links: [{ type: "venue", id: 5, label: "Hall" }] } },
  ]);
  await import("../src/link-editor.ts");

  const triggers = Array.from(document.querySelectorAll<HTMLElement>("[data-link-editor-trigger]"));
  const modals = Array.from(document.querySelectorAll<HTMLElement>("[data-link-editor-modal]"));
  triggers[0]!.click();
  modals[0]!.querySelector<HTMLElement>('[data-remove-link="0"]')!.click();
  modals[0]!.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

  expect(Array.from(document.querySelectorAll<HTMLInputElement>("#note-first-links input[name='link_items[]']")).map((input) => input.value)).toEqual([]);
  expect(Array.from(document.querySelectorAll<HTMLInputElement>("#note-second-links input[name='link_items[]']")).map((input) => input.value)).toEqual(["venue:5"]);
  expect(document.querySelector("#note-first-link-pills")!.textContent).toContain("No links yet");
  expect(document.querySelector("#note-second-link-pills")!.textContent).toContain("Hall");
  triggers[1]!.click();
  expect(modals[1]!.querySelector("[data-link-editor-tags]")!.textContent).toContain("Hall");
  document.body.innerHTML = "";
});

test("reinitializes swapped note editors once without cross-form updates", async () => {
  document.body.innerHTML = `<section id="swap-target">${renderNoteForms([
    { formId: "note-old", noteEdit: { id: 30, links: [{ type: "task", id: 9, label: "Old task" }] } },
  ])}</section>${renderNoteForms([
    { formId: "note-stable", noteEdit: { id: 32, links: [{ type: "venue", id: 5, label: "Hall" }] } },
  ])}`;
  await import("../src/link-editor.ts");

  const swapTarget = document.querySelector<HTMLElement>("#swap-target")!;
  swapTarget.innerHTML = renderNoteForms([
    { formId: "note-swapped", noteEdit: { id: 31, links: [{ type: "contact", id: 42, label: "Jane" }] } },
  ]);
  document.dispatchEvent(new CustomEvent("htmx:afterSwap", { detail: { target: swapTarget } }));
  document.dispatchEvent(new CustomEvent("htmx:afterSwap", { detail: { target: swapTarget } }));

  const saved: Event[] = [];
  document.addEventListener("link-editor:local-saved", (event) => saved.push(event));
  document.querySelector<HTMLElement>('[data-link-editor-trigger][data-link-editor-modal-id="link-editor-conversation_note-31"]')!.click();
  document.querySelector<HTMLElement>("#link-editor-conversation_note-31 [data-remove-link='0']")!.click();
  document.querySelector<HTMLElement>("#link-editor-conversation_note-31 [data-link-editor-save]")!.click();

  expect(saved).toHaveLength(1);
  expect(Array.from(document.querySelectorAll<HTMLInputElement>("#note-swapped-links input[name='link_items[]']")).map((input) => input.value)).toEqual([]);
  expect(Array.from(document.querySelectorAll<HTMLInputElement>("#note-stable-links input[name='link_items[]']")).map((input) => input.value)).toEqual(["venue:5"]);
  expect(document.querySelector("#note-swapped-link-pills")!.textContent).toContain("No links yet");
  expect(document.querySelector("#note-stable-link-pills")!.textContent).toContain("Hall");
  document.body.innerHTML = "";
});
