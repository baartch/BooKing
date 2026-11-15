import { beforeEach, describe, expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { readFileSync } from "node:fs";
import { join } from "node:path";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

type DashboardEmailModule = {
  formatDashboardEmailDate: (value: string) => string | null;
  initDashboardEmailDates: (scope?: ParentNode) => void;
};

const loadDashboardEmailModule = async (): Promise<DashboardEmailModule | null> => {
  try {
    return await import("../src/dashboard-email.js") as DashboardEmailModule;
  } catch {
    return null;
  }
};

const setBrowserLocales = (locales: string[]): void => {
  Object.defineProperty(navigator, "languages", { configurable: true, value: locales });
};

const dashboardEmailStyles = (() => {
  try {
    return readFileSync(join(import.meta.dir, "../app/public/css/dashboard-unread-emails.css"), "utf8");
  } catch {
    return null;
  }
})();

describe("dashboard email date formatting", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    setBrowserLocales(["en-US"]);
  });

  test("uses browser locale and local time zone for timestamps", async () => {
    const emailModule = await loadDashboardEmailModule();
    expect(emailModule?.formatDashboardEmailDate).toBeFunction();
    if (!emailModule) return;

    const value = "2026-12-13T07:43:00+00:00";
    const instant = new Date(value);
    const localTimeZone = new Intl.DateTimeFormat().resolvedOptions().timeZone;
    setBrowserLocales(["en-US"]);
    const usExpected = new Intl.DateTimeFormat(["en-US"], { dateStyle: "short", timeStyle: "short" }).format(instant);
    expect(emailModule.formatDashboardEmailDate(value)).toBe(usExpected);
    setBrowserLocales(["de-DE"]);
    const deExpected = new Intl.DateTimeFormat(["de-DE"], { dateStyle: "short", timeStyle: "short" }).format(instant);
    expect(emailModule.formatDashboardEmailDate(value)).toBe(deExpected);
    expect(usExpected).not.toBe(deExpected);

    if (localTimeZone !== "UTC") {
      const utcExpected = new Intl.DateTimeFormat("en-US", {
        dateStyle: "short",
        timeStyle: "short",
        timeZone: "UTC",
      }).format(instant);
      setBrowserLocales(["en-US"]);
      expect(emailModule.formatDashboardEmailDate(value)).not.toBe(utcExpected);
    }
  });

  test("returns null for invalid date values", async () => {
    const emailModule = await loadDashboardEmailModule();
    expect(emailModule?.formatDashboardEmailDate).toBeFunction();
    if (!emailModule) return;

    expect(emailModule.formatDashboardEmailDate("not-a-date")).toBeNull();
  });

  test("formats only marked dashboard dates and can initialize repeatedly", async () => {
    const emailModule = await loadDashboardEmailModule();
    expect(emailModule?.initDashboardEmailDates).toBeFunction();
    if (!emailModule) return;

    const scope = document.createElement("section");
    scope.innerHTML = `
      <time data-dashboard-email-date datetime="2026-12-13T07:43:00+00:00">Fallback date</time>
      <time datetime="2026-12-13T07:43:00+00:00">Leave this unchanged</time>
      <time data-dashboard-email-date datetime="invalid-date">Invalid fallback</time>
    `;
    setBrowserLocales(["en-US"]);
    const expected = new Intl.DateTimeFormat(["en-US"], { dateStyle: "short", timeStyle: "short" })
      .format(new Date("2026-12-13T07:43:00+00:00"));

    emailModule.initDashboardEmailDates(scope);
    emailModule.initDashboardEmailDates(scope);

    expect(scope.querySelector("time[data-dashboard-email-date]")?.textContent).toBe(expected);
    expect(scope.querySelector("time:not([data-dashboard-email-date])")?.textContent).toBe("Leave this unchanged");
    expect(scope.querySelector("time[datetime='invalid-date']")?.textContent).toBe("Invalid fallback");
  });

  test("keeps unread email tables within their panel and wraps long cell content", () => {
    expect(dashboardEmailStyles).not.toBeNull();
    if (!dashboardEmailStyles) return;

    const style = document.createElement("style");
    style.textContent = dashboardEmailStyles;
    document.head.append(style);
    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="dashboard-unread-email-table">
        <div class="table-container table-container--dropdowns">
          <table><tbody><tr><td class="sender">verylongsenderaddress@example.com</td></tr></tbody></table>
        </div>
      </div>
    `;
    document.body.append(wrapper);
    const container = wrapper.querySelector<HTMLElement>(".table-container")!;
    const table = wrapper.querySelector<HTMLTableElement>("table")!;
    const sender = wrapper.querySelector<HTMLElement>(".sender")!;

    expect(getComputedStyle(container).overflowX).toBe("auto");
    expect(getComputedStyle(table).tableLayout).toBe("fixed");
    expect(getComputedStyle(sender).overflowWrap).toBe("anywhere");
  });
});
