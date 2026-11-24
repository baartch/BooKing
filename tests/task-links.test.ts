import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { execFileSync } from "node:child_process";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

const renderNewTaskForm = (): string => {
  const php =
    "define('BASE_PATH', '/booking');" +
    "function renderCsrfField(): void { echo '<input type=\"hidden\" name=\"csrf_token\" value=\"token\">'; }" +
    "function getCsrfToken(): string { return 'token'; }" +
    "$isEdit = false; $editTask = null;" +
    "$formValues = ['title' => '', 'description' => '', 'priority' => 'B', 'due_date' => ''];" +
    "$taskLinks = []; $linkEditorLinks = []; $searchQuery = ''; $cancelUrl = '/cancel'; $activeTeamId = 7;" +
    "require 'app/views/team/tasks/form.php';";
  return execFileSync("php", ["-r", php], { encoding: "utf8" });
};

test("new task form collects selected links locally for task submission", async () => {
  document.body.innerHTML = renderNewTaskForm();
  const collector = document.querySelector<HTMLElement>("#task-link-collector");
  const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]");
  const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]");
  expect(collector).not.toBeNull();
  const editor = modal?.querySelector("[data-link-editor]");
  expect(editor?.getAttribute("data-link-editor-local-only")).toBe("1");
  expect(editor?.getAttribute("data-link-editor-collector-selector")).toBe("#task-link-collector");
  expect(trigger).not.toBeNull();

  const originalFetch = globalThis.fetch;
  const requestedUrls: string[] = [];
  globalThis.fetch = async (input) => {
    requestedUrls.push(String(input));
    return new Response(JSON.stringify({ items: [{ type: "contact", id: 42, label: "Alex Contact", detail: "" }] }));
  };

  try {
    await import("../src/link-editor.ts");
    document.dispatchEvent(new Event("DOMContentLoaded"));
    trigger!.click();
    const search = modal!.querySelector<HTMLInputElement>("[data-link-editor-search]")!;
    search.value = "Alex";
    search.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 300));
    modal!.querySelector<HTMLElement>("[data-result-index]")!.click();
    modal!.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

    expect(Array.from(collector!.querySelectorAll<HTMLInputElement>('input[name="link_items[]"]')).map((input) => input.value)).toEqual(["contact:42"]);
    expect(requestedUrls.every((url) => !url.includes("links_save.php"))).toBe(true);
  } finally {
    globalThis.fetch = originalFetch;
    document.body.innerHTML = "";
  }
});
