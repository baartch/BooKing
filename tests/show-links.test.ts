import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { execFileSync } from "node:child_process";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

type ShowFixture = {
  links?: Array<{ type: string; id: number; label: string }>;
  venueText?: string;
  isEdit?: boolean;
};

const renderShowForm = (fixture: ShowFixture = {}): string => {
  const php = `
    define('BASE_PATH', '/booking');
    function renderCsrfField(): void { echo '<input type="hidden" name="csrf_token" value="token">'; }
    function getCsrfToken(): string { return 'token'; }
    $fixture = json_decode(stream_get_contents(STDIN), true);
    $isEdit = (bool) ($fixture['isEdit'] ?? false);
    $editShow = $isEdit ? ['id' => 21] : null;
    $formValues = [
      'name' => '', 'show_date' => '', 'show_time' => '',
      'venue_text' => (string) ($fixture['venueText'] ?? ''),
      'artist_fee' => '', 'entrance_fee' => '', 'state' => 'provisional', 'notes' => ''
    ];
    $showLinks = $fixture['links'] ?? [];
    $teamSongOptions = [];
    $selectedSongIds = [];
    $searchQuery = '';
    $cancelUrl = '/cancel';
    $activeTeamId = 7;
    require 'app/views/team/shows/form.php';
  `;
  return execFileSync("php", ["-r", php], {
    encoding: "utf8",
    input: JSON.stringify(fixture),
  });
};

test("show form renders icon-free component pills and a collector-scoped refresh target", () => {
  document.body.innerHTML = renderShowForm({
    isEdit: true,
    links: [{ type: "venue", id: 55, label: "Hall" }],
  });

  const list = document.querySelector<HTMLElement>("#show-link-pills")!;
  const collector = document.querySelector<HTMLElement>("#show-link-collector")!;
  expect(list.querySelector(".detail-link-pill .icon")).toBeNull();
  expect(collector.dataset.linkEditorPillsSelector).toBe("#show-link-pills");
  expect(collector.dataset.linkEditorPillsShowIcons).toBe("0");
  expect(
    Array.from(collector.querySelectorAll<HTMLInputElement>('input[name="link_items[]"]')).map((input) => input.value),
  ).toEqual(["venue:55"]);
  document.body.innerHTML = "";
});

test("show venue selection updates fallbacks, prefills blank text, stays icon-free, then clears fallback on removal", async () => {
  document.body.innerHTML = renderShowForm({ isEdit: false });
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () =>
    new Response(
      JSON.stringify({
        items: [{ type: "venue", id: 55, label: "Hall & Garden", detail: "" }],
      }),
      { status: 200 },
    );

  try {
    await import("../src/link-editor.ts");
    await import("../src/show-songs.ts");
    document.dispatchEvent(new Event("DOMContentLoaded"));

    const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
    const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
    trigger.click();
    const search = modal.querySelector<HTMLInputElement>("[data-link-editor-search]")!;
    search.value = "Hall";
    search.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 300));
    modal.querySelector<HTMLElement>("[data-result-index]")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_type")!.value).toBe("venue");
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_id")!.value).toBe("55");
    expect(document.querySelector<HTMLInputElement>("#show_venue_text")!.value).toBe("Hall & Garden");
    expect(document.querySelector("#show-link-pills .detail-link-pill .icon")).toBeNull();
    expect(
      Array.from(document.querySelectorAll<HTMLInputElement>("#show-link-collector input[name='link_items[]']")).map((input) => input.value),
    ).toEqual(["venue:55"]);

    trigger.click();
    expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Hall & Garden");
    modal.querySelector<HTMLElement>("[data-remove-link='0']")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_type")!.value).toBe("");
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_id")!.value).toBe("");
    expect(document.querySelector("#show-link-pills")!.textContent).toContain("No links yet");
    expect(document.querySelector("#show-link-pills .detail-link-pill .icon")).toBeNull();
  } finally {
    globalThis.fetch = originalFetch;
    document.body.innerHTML = "";
  }
});

test("show venue selection does not overwrite nonempty venue text and cancel leaves links untouched", async () => {
  document.body.innerHTML = renderShowForm({ venueText: "Typed venue" });
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () =>
    new Response(
      JSON.stringify({ items: [{ type: "venue", id: 55, label: "Hall", detail: "" }] }),
      { status: 200 },
    );

  try {
    await import("../src/link-editor.ts");
    await import("../src/show-songs.ts");
    document.dispatchEvent(new Event("DOMContentLoaded"));
    const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
    const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
    trigger.click();
    const search = modal.querySelector<HTMLInputElement>("[data-link-editor-search]")!;
    search.value = "Hall";
    search.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 300));
    modal.querySelector<HTMLElement>("[data-result-index]")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

    expect(document.querySelector<HTMLInputElement>("#show_venue_text")!.value).toBe("Typed venue");
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_type")!.value).toBe("venue");
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_id")!.value).toBe("55");
    expect(
      Array.from(document.querySelectorAll<HTMLInputElement>("#show-link-collector input[name='link_items[]']")).map((input) => input.value),
    ).toEqual(["venue:55"]);

    trigger.click();
    modal.querySelector<HTMLElement>("[data-remove-link='0']")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-close]")!.click();
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_type")!.value).toBe("venue");
    expect(document.querySelector<HTMLInputElement>("#show_fallback_link_id")!.value).toBe("55");
    expect(document.querySelectorAll("#show-link-collector input[name='link_items[]']")).toHaveLength(1);
    expect(document.querySelector("#show-link-pills")!.textContent).toContain("Hall");
  } finally {
    globalThis.fetch = originalFetch;
    document.body.innerHTML = "";
  }
});

test("show refresh ignores local-save events from another collector", async () => {
  document.body.innerHTML = renderShowForm();
  await import("../src/show-songs.ts");
  document.dispatchEvent(new Event("DOMContentLoaded"));
  document.dispatchEvent(
    new CustomEvent("link-editor:local-saved", {
      detail: {
        collectorSelector: "#other-link-collector",
        links: [{ type: "venue", id: 55, label: "Hall" }],
      },
    }),
  );

  expect(document.querySelector<HTMLInputElement>("#show_fallback_link_type")!.value).toBe("");
  expect(document.querySelector<HTMLInputElement>("#show_fallback_link_id")!.value).toBe("");
  expect(document.querySelector<HTMLInputElement>("#show_venue_text")!.value).toBe("");
  expect(document.querySelector("#show-link-pills")!.textContent).toContain("No links yet");
  document.body.innerHTML = "";
});
