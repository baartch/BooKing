import { describe, expect, test } from "bun:test";
import { readFileSync } from "node:fs";
import { Window } from "happy-dom";

function renderSidebar(withMailbox: boolean): Document {
  const php = `
    $teamMailboxes = ${withMailbox ? "[['id' => 7, 'display_name' => 'Office', 'user_id' => 1]]" : '[]'};
    $selectedMailbox = null;
    $mailboxIndicators = [];
    $baseEmailUrl = '/communication';
    $baseQuery = ['tab' => 'email', 'mailbox_id' => 7];
    $notice = '';
    $errors = [];
    require 'app/views/communication/email_sidebar.php';
  `;
  const result = Bun.spawnSync(["php", "-r", php]);
  expect(result.exitCode).toBe(0);
  const window = new Window();
  window.document.head.innerHTML = `<style>${readFileSync("app/public/css/communication.css", "utf8")}</style>`;
  window.document.body.innerHTML = result.stdout.toString();
  return window.document as unknown as Document;
}

describe("email sidebar compose action", () => {
  test("shows an accessible icon-only compose link at the right of the mailbox row", () => {
    const document = renderSidebar(true);
    const avatar = document.querySelector('[data-mailbox-avatar]')!;
    const compose = document.querySelector<HTMLAnchorElement>('a[aria-label="New eMail"]')!;
    expect(compose).not.toBeNull();
    expect(compose.querySelector("i")).not.toBeNull();
    expect(compose.textContent!.trim()).toBe("");
    expect(compose.getAttribute("href")).toBe("/communication?tab=email&mailbox_id=7&compose=1");
    const row = avatar.parentElement!.parentElement!;
    expect(row.contains(compose)).toBe(true);
    expect(document.defaultView!.getComputedStyle(row).display).toBe("flex");
    expect(document.defaultView!.getComputedStyle(compose).marginLeft).toBe("auto");
  });

  test("keeps the compose action available when no mailboxes are assigned", () => {
    const document = renderSidebar(false);
    expect(document.querySelector('a[aria-label="New eMail"]')).not.toBeNull();
    expect(document.body.textContent).toContain("No mailboxes assigned.");
  });
});
