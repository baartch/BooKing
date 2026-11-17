import { beforeEach, describe, expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

if (!GlobalRegistrator.isRegistered) {
  GlobalRegistrator.register({ url: "http://localhost/" });
}

const communicationPath = "/app/controllers/communication/index.php";

describe("mailbox restoration navigation", () => {
  beforeEach(() => {
    window.location.href = `http://localhost${communicationPath}`;
    window.localStorage.clear();
    document.body.innerHTML = `
      <ul>
        <li class="is-active"><a href="#" data-tab="conversations">Conversations</a></li>
        <li><a href="#" data-tab="email">eMail</a></li>
      </ul>
      <div data-tab-panel="conversations"></div>
      <div data-tab-panel="email" class="is-hidden">
        <a data-mailbox-avatar data-mailbox-id="7" class="is-active"
          href="${communicationPath}?tab=email&mailbox_id=7"></a>
      </div>
    `;
  });

  test("opening communication stays on conversations with a stored mailbox", async () => {
    window.localStorage.setItem("email:selectedMailboxId", "7");
    await import("../src/email.js");
    document.dispatchEvent(new Event("DOMContentLoaded"));

    expect(window.location.pathname + window.location.search).toBe(communicationPath);
  });

  test("selecting the email tab restores the stored mailbox", async () => {
    window.history.replaceState({}, "", `${communicationPath}?tab=conversations`);
    window.localStorage.setItem("email:selectedMailboxId", "7");
    await import("../src/tabs.js");
    document.querySelector<HTMLAnchorElement>('[data-tab="email"]')!.click();
    await Promise.resolve();

    expect(window.location.pathname + window.location.search).toBe(
      `${communicationPath}?tab=email&mailbox_id=7`,
    );
  });

  test("an explicit email message is not redirected to the stored mailbox", async () => {
    window.history.replaceState({}, "", `${communicationPath}?tab=email&message_id=42`);
    window.localStorage.setItem("email:selectedMailboxId", "7");
    document.querySelector('[data-tab-panel="email"]')!.classList.remove("is-hidden");
    document.dispatchEvent(new CustomEvent("tab:activated", { detail: { tab: "email" } }));
    await Promise.resolve();

    expect(window.location.search).toBe("?tab=email&message_id=42");
  });
});
