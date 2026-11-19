import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { execFileSync } from "node:child_process";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

const buildComposeForm = (): void => {
  document.body.innerHTML = `
    <form data-email-compose-form>
      <input type="hidden" data-email-conversation-id value="9">
      <div data-email-links>
        <span class="detail-link-list" data-email-links-list data-team-id="7"
          data-contact-url-base="/communication.php" data-venue-url-base="/venues.php"
          data-email-url-base="/communication.php" data-task-url-base="/team.php"
          data-conversation-url-base="/communication.php"></span>
        <span data-email-conversation-pill></span>
      </div>
      <div id="email-compose-link-collector" data-email-link-inputs data-link-editor-collector>
        <input type="hidden" name="link_items[]" value="contact:42" data-link-label="Jane">
      </div>
      <div id="other-link-collector" data-link-editor-collector></div>
    </form>`;
};

const renderComposeForm = (): string => {
  const php = `
    define('BASE_PATH', '/booking');
    function renderCsrfField(): void { echo '<input type="hidden" name="csrf_token" value="token">'; }
    function getCsrfToken(): string { return 'token'; }
    $composeConversationId = 9;
    $teamMailboxes = [];
    $selectedMailbox = ['id' => 8, 'team_id' => 7];
    $folder = 'inbox'; $sortKey = 'date'; $filter = ''; $page = 1; $message = null;
    $composeValues = [
      'schedule_date' => '', 'schedule_time' => '', 'to_emails' => '', 'cc_emails' => '',
      'bcc_emails' => '', 'subject' => '', 'start_new_conversation' => false, 'body' => '',
      'link_items' => [], 'conversation_id' => 9, 'conversation_label' => 'Existing conversation'
    ];
    $baseEmailUrl = '/booking/app/controllers/communication/index.php';
    $baseQuery = ['tab' => 'email'];
    $composeCancelUrl = '/cancel';
    require 'app/views/communication/email_compose_form.php';
  `;
  return execFileSync("php", ["-r", php], { encoding: "utf8" });
};

const fireLocalSaved = (detail: Record<string, unknown>): void => {
  document.dispatchEvent(
    new CustomEvent("link-editor:local-saved", { detail }),
  );
};

test("email compose ignores another collector's local-saved event", async () => {
  buildComposeForm();
  await import("../src/email.ts");
  document.dispatchEvent(new Event("DOMContentLoaded"));

  fireLocalSaved({
    collectorSelector: "#other-link-collector",
    links: [{ type: "venue", id: 55, label: "Hall" }],
    conversationId: 77,
    conversationLabel: "Other conversation",
  });

  expect(document.querySelector<HTMLInputElement>("[data-email-conversation-id]")!.value).toBe("9");
  expect(document.querySelector("[data-email-links-list] .detail-link-pill")!.textContent).toContain("Jane");
  expect((document.querySelector("[data-email-links-list] .detail-link-pill") as HTMLAnchorElement).getAttribute("href"))
    .toBe("/communication.php?tab=contacts&team_id=7&contact_id=42");
  expect(document.querySelector("[data-email-conversation-pill]")!.textContent).not.toContain("Other conversation");
});

test("email compose PHP view keeps one URL-aware component list and a blank-safe conversation pill", () => {
  document.body.innerHTML = renderComposeForm();
  const list = document.querySelector<HTMLElement>("[data-email-links-list]")!;
  const collector = document.querySelector<HTMLElement>("#email-compose-link-collector")!;

  expect(list.dataset.teamId).toBe("7");
  expect(list.dataset.contactUrlBase).toBe("/booking/app/controllers/communication/index.php");
  expect(list.dataset.venueUrlBase).toBe("/booking/app/controllers/venues/index.php");
  expect(list.dataset.emailUrlBase).toBe("/booking/app/controllers/communication/index.php");
  expect(list.dataset.taskUrlBase).toBe("/booking/app/controllers/team/index.php");
  expect(list.dataset.conversationUrlBase).toBe("/booking/app/controllers/communication/index.php");
  expect(document.querySelectorAll("[data-email-links-list]")).toHaveLength(1);
  expect(collector.dataset.linkEditorPillsSelector).toBeUndefined();
  expect(document.querySelector("[data-email-conversation-pill]")!.textContent).toContain("Existing conversation");
  expect(document.querySelector("[data-link-editor-modal-id='link-editor-email-0']")).not.toBeNull();
});

test("recipient lookup keeps newly selected links when rendering empty and populated collectors", async () => {
  document.body.innerHTML = renderComposeForm();
  const originalFetch = globalThis.fetch;
  const hugerteWindow = window as Window & { hugerte?: unknown };
  const originalHugerte = hugerteWindow.hugerte;
  hugerteWindow.hugerte = { init: () => undefined, get: () => null };
  globalThis.fetch = async (input) => {
    const url = String(input);
    const item = url.includes("q=Ha")
      ? {
          id: 55,
          type: "venue",
          name: "Hall",
          label: "Hall",
          email: "hall@example.test",
          source: "Team 7",
        }
      : {
          id: 42,
          type: "contact",
          name: "Jane Doe",
          label: "Jane Doe",
          email: "jane@example.test",
          source: "Team 7",
        };
    return new Response(JSON.stringify({ items: [item] }), { status: 200 });
  };

  try {
    await import("../src/email.ts");
    document.dispatchEvent(new Event("DOMContentLoaded"));
    const recipientInput = document.querySelector<HTMLInputElement>("#email_to")!;
    const lookupContact = async (query: string): Promise<void> => {
      recipientInput.value = query;
      recipientInput.dispatchEvent(new Event("input", { bubbles: true }));
      await new Promise((resolve) => setTimeout(resolve, 300));
      document.querySelector<HTMLElement>("[data-email-lookup] .dropdown-item[data-index]")!.click();
    };

    await lookupContact("Jane");
    expect(
      Array.from(document.querySelectorAll<HTMLInputElement>("#email-compose-link-collector input[name='link_items[]']")).map((input) => input.value),
    ).toEqual(["contact:42"]);
    expect(document.querySelector("[data-email-links-list] .detail-link-pill")!.textContent).toContain("Jane Doe");

    await lookupContact("jane@example.test, Hall");
    expect(
      Array.from(document.querySelectorAll<HTMLInputElement>("#email-compose-link-collector input[name='link_items[]']")).map((input) => input.value),
    ).toEqual(["contact:42", "venue:55"]);
    expect(Array.from(document.querySelectorAll("[data-email-links-list] .detail-link-pill")).map((pill) => pill.textContent?.trim()))
      .toEqual(["Jane Doe", "Hall"]);
  } finally {
    globalThis.fetch = originalFetch;
    hugerteWindow.hugerte = originalHugerte;
    document.body.innerHTML = "";
  }
});

test("email compose modal submits local links, attaches a conversation, reopens, and detaches cleanly", async () => {
  document.body.innerHTML = renderComposeForm();
  const originalFetch = globalThis.fetch;
  const hugerteWindow = window as Window & { hugerte?: unknown };
  const originalHugerte = hugerteWindow.hugerte;
  hugerteWindow.hugerte = { init: () => undefined, get: () => null };
  globalThis.fetch = async (input) => {
    const url = String(input);
    const item = url.includes("types=conversation")
      ? { type: "conversation", id: 77, label: "New conversation", detail: "" }
      : { type: "venue", id: 55, label: "Hall & Garden", detail: "" };
    return new Response(JSON.stringify({ items: [item] }), { status: 200 });
  };

  try {
    await import("../src/email.ts");
    await import("../src/link-editor.ts");
    document.dispatchEvent(new Event("DOMContentLoaded"));

    const trigger = document.querySelector<HTMLElement>("[data-link-editor-trigger]")!;
    const modal = document.querySelector<HTMLElement>("[data-link-editor-modal]")!;
    trigger.click();
    const linkSearch = modal.querySelector<HTMLInputElement>("[data-link-editor-search]")!;
    linkSearch.value = "Hall";
    linkSearch.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 300));
    modal.querySelector<HTMLElement>("[data-link-editor-results] [data-result-index]")!.click();

    const conversationSearch = modal.querySelector<HTMLInputElement>("[data-link-editor-conversation-search]")!;
    conversationSearch.value = "New";
    conversationSearch.dispatchEvent(new Event("input", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 300));
    modal.querySelector<HTMLElement>("[data-link-editor-conversation-results] [data-result-index]")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

    expect(
      Array.from(document.querySelectorAll<HTMLInputElement>("#email-compose-link-collector input[name='link_items[]']")).map((input) => input.value),
    ).toEqual(["venue:55"]);
    expect(document.querySelector<HTMLInputElement>("[data-email-conversation-id]")!.value).toBe("77");
    expect(document.querySelector("[data-email-links-list] .detail-link-pill")!.textContent).toContain("Hall & Garden");
    expect((document.querySelector("[data-email-links-list] .detail-link-pill") as HTMLAnchorElement).getAttribute("href"))
      .toBe("/booking/app/controllers/venues/index.php?venue_id=55");
    expect(document.querySelector("[data-email-conversation-pill]")!.textContent).toContain("New conversation");

    trigger.click();
    expect(modal.querySelector("[data-link-editor-tags]")!.textContent).toContain("Hall & Garden");
    expect(modal.querySelector("[data-link-editor-conversation-tag]")!.textContent).toContain("New conversation");
    modal.querySelector<HTMLElement>("[data-remove-link='0']")!.click();
    modal.querySelector<HTMLElement>("[data-detach-conversation]")!.click();
    modal.querySelector<HTMLElement>("[data-link-editor-save]")!.click();

    expect(document.querySelectorAll("#email-compose-link-collector input[name='link_items[]']")).toHaveLength(0);
    expect(document.querySelector("[data-email-links-list]")!.textContent).toContain("No links yet");
    expect(document.querySelector<HTMLInputElement>("[data-email-conversation-id]")!.value).toBe("");
    expect(document.querySelector("[data-email-conversation-pill]")!.textContent?.trim()).toBe("");
    trigger.click();
    expect(modal.querySelector("[data-link-editor-conversation-tag]")!.textContent).toContain("Not assigned to a conversation");
    expect(modal.querySelector("[data-link-editor-conversation-tag]")!.textContent).not.toContain("New conversation");
    modal.querySelector<HTMLElement>("[data-link-editor-close]")!.click();
  } finally {
    globalThis.fetch = originalFetch;
    hugerteWindow.hugerte = originalHugerte;
    document.body.innerHTML = "";
  }
});

test("email compose attaches and detaches a conversation and clears an empty link selection", async () => {
  buildComposeForm();
  await import("../src/email.ts");
  document.dispatchEvent(new Event("DOMContentLoaded"));

  const collector = document.querySelector<HTMLElement>("#email-compose-link-collector")!;
  collector.innerHTML = '<input type="hidden" name="link_items[]" value="venue:55" data-link-label="Hall &amp; Garden">';
  fireLocalSaved({
    collectorSelector: "#email-compose-link-collector",
    links: [{ type: "venue", id: 55, label: "Hall & Garden" }],
    conversationId: 77,
    conversationLabel: "Conversation 77",
  });

  expect(document.querySelector<HTMLInputElement>("[data-email-conversation-id]")!.value).toBe("77");
  expect(document.querySelector("[data-email-conversation-pill]")!.textContent).toContain("Conversation 77");
  expect(document.querySelector("[data-email-conversation-pill] .fa-comments")).not.toBeNull();
  expect(document.querySelector("[data-email-links-list] .detail-link-pill")!.textContent).toContain("Hall & Garden");
  expect((document.querySelector("[data-email-links-list] .detail-link-pill") as HTMLAnchorElement).getAttribute("href"))
    .toBe("/venues.php?venue_id=55");

  collector.replaceChildren();
  fireLocalSaved({
    collectorSelector: "#email-compose-link-collector",
    links: [],
    detachConversation: true,
  });

  expect(document.querySelector<HTMLInputElement>("[data-email-conversation-id]")!.value).toBe("");
  expect(document.querySelector("[data-email-conversation-pill]")!.textContent?.trim()).toBe("");
  expect(document.querySelector("[data-email-links-list]")!.textContent).toContain("No links yet");
  expect(collector.querySelectorAll('input[name="link_items[]"]')).toHaveLength(0);
});
