import { beforeEach, describe, expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

GlobalRegistrator.register();

type Source = {
  id: string;
  url: string;
  color: string;
  extraParams: { team_id: number };
  failure?: () => void;
  success?: () => void;
};

type CalendarOptions = {
  initialView: string;
  headerToolbar: { right: string };
  eventSources: Source[];
  loading: (loading: boolean) => void;
  eventsSet: (events: unknown[]) => void;
  [key: string]: unknown;
};

class FakeCalendar {
  static instances: FakeCalendar[] = [];
  readonly options: CalendarOptions;
  readonly sources = new Map<string, Source>();

  constructor(_el: HTMLElement, options: CalendarOptions) {
    this.options = options;
    options.eventSources.forEach((source) => this.sources.set(source.id, source));
    FakeCalendar.instances.push(this);
  }

  render(): void {}

  addEventSource(source: Source): void {
    this.sources.set(source.id, source);
  }

  getEventSourceById(id: string): { remove: () => void } | null {
    if (!this.sources.has(id)) return null;
    return { remove: () => this.sources.delete(id) };
  }
}

const buildCalendar = (teamMarkup: string): HTMLElement => {
  document.body.innerHTML = `<div data-dashboard-calendar data-calendar-events-url="/calendar-events.php">
    ${teamMarkup}<div data-calendar></div>
    <div data-calendar-error></div><div data-calendar-loading></div><div data-calendar-empty></div>
  </div>`;
  return document.querySelector<HTMLElement>("[data-dashboard-calendar]")!;
};

describe("dashboard calendar initialization", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    FakeCalendar.instances = [];
    (window as unknown as { FullCalendar: { Calendar: typeof FakeCalendar } }).FullCalendar = { Calendar: FakeCalendar };
  });

  test("defaults to month view with week and day choices", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<span hidden data-calendar-team data-team-id="7" data-team-name="Brass"></span>');

    initDashboardCalendar(root);

    expect(FakeCalendar.instances).toHaveLength(1);
    expect(FakeCalendar.instances[0].options.initialView).toBe("dayGridMonth");
    expect(FakeCalendar.instances[0].options.headerToolbar.right).toContain("dayGridMonth");
    expect(FakeCalendar.instances[0].options.headerToolbar.right).toContain("timeGridWeek");
    expect(FakeCalendar.instances[0].options.headerToolbar.right).toContain("timeGridDay");
    expect(root.querySelector("[data-calendar-team-toggle]")).toBeNull();
  });

  test("creates a separate source for each team with its authorized team id", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar(`
      <label><input type="checkbox" checked data-calendar-team-toggle data-team-id="7" data-team-name="Brass"></label>
      <label><input type="checkbox" checked data-calendar-team-toggle data-team-id="8" data-team-name="Strings"></label>
    `);

    initDashboardCalendar(root);

    expect([...FakeCalendar.instances[0].sources.values()].map(({ id, url, extraParams }) => ({ id, url, extraParams }))).toEqual([
      { id: "team-7", url: "/calendar-events.php", extraParams: { team_id: 7 } },
      { id: "team-8", url: "/calendar-events.php", extraParams: { team_id: 8 } },
    ]);
    expect(new Set([...FakeCalendar.instances[0].sources.values()].map(({ color }) => color)).size).toBe(2);
  });

  test("removes and restores a team's source without duplicates", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<label><input type="checkbox" checked data-calendar-team-toggle data-team-id="7" data-team-name="Brass"></label>');

    initDashboardCalendar(root);
    const calendar = FakeCalendar.instances[0];
    const toggle = root.querySelector<HTMLInputElement>("[data-calendar-team-toggle]")!;

    toggle.checked = false;
    toggle.dispatchEvent(new Event("change", { bubbles: true }));
    expect(calendar.sources.has("team-7")).toBe(false);

    toggle.checked = true;
    toggle.dispatchEvent(new Event("change", { bubbles: true }));
    toggle.dispatchEvent(new Event("change", { bubbles: true }));
    expect(calendar.sources.size).toBe(1);
  });

  test("does not initialize the same calendar element twice", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<span hidden data-calendar-team data-team-id="7" data-team-name="Brass"></span>');

    initDashboardCalendar(root);
    initDashboardCalendar(root);

    expect(FakeCalendar.instances).toHaveLength(1);
  });

  test("shows an empty state after enabled sources finish with no events", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<span hidden data-calendar-team data-team-id="7" data-team-name="Brass"></span>');
    initDashboardCalendar(root);
    const calendar = FakeCalendar.instances[0];

    calendar.options.loading(true);
    calendar.options.eventsSet([]);
    expect(root.querySelector<HTMLElement>("[data-calendar-empty]")!.classList.contains("is-hidden")).toBe(true);
    calendar.options.loading(false);
    expect(root.querySelector<HTMLElement>("[data-calendar-empty]")!.textContent).toContain("No shows or tasks");
  });

  test("reports a failed team source without hiding the failure", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<span hidden data-calendar-team data-team-id="7" data-team-name="Brass"></span>');
    initDashboardCalendar(root);
    FakeCalendar.instances[0].sources.get("team-7")!.failure?.();

    const error = root.querySelector<HTMLElement>("[data-calendar-error]")!;
    expect(error.textContent).toContain("Brass");
    expect(error.classList.contains("is-hidden")).toBe(false);
  });

  test("explains when every team is switched off", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar('<label><input type="checkbox" checked data-calendar-team-toggle data-team-id="7" data-team-name="Brass"></label>');
    initDashboardCalendar(root);
    const toggle = root.querySelector<HTMLInputElement>("[data-calendar-team-toggle]")!;
    toggle.checked = false;
    toggle.dispatchEvent(new Event("change", { bubbles: true }));

    expect(root.querySelector<HTMLElement>("[data-calendar-empty]")!.textContent).toContain("Select at least one team");
  });

  test("shows a useful error when the FullCalendar script is unavailable", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    delete (window as unknown as { FullCalendar?: unknown }).FullCalendar;
    const root = buildCalendar('<span hidden data-calendar-team data-team-id="7" data-team-name="Brass"></span>');
    initDashboardCalendar(root);

    expect(root.querySelector<HTMLElement>("[data-calendar-error]")!.textContent).toContain("Calendar failed to load");
    expect(FakeCalendar.instances).toHaveLength(0);
  });

  test("renders an explicit state and does not initialize when there are no teams", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar("");
    initDashboardCalendar(root);

    expect(root.querySelector<HTMLElement>("[data-calendar-empty]")!.textContent).toContain("No teams are available");
    expect(FakeCalendar.instances).toHaveLength(0);
  });

  test("keeps a partial feed failure visible when another team succeeds", async () => {
    const { initDashboardCalendar } = await import("../src/dashboard-calendar.js");
    const root = buildCalendar(`
      <label><input type="checkbox" checked data-calendar-team-toggle data-team-id="7" data-team-name="Brass"></label>
      <label><input type="checkbox" checked data-calendar-team-toggle data-team-id="8" data-team-name="Strings"></label>
    `);
    initDashboardCalendar(root);
    const sources = FakeCalendar.instances[0].sources;
    sources.get("team-7")!.failure?.();
    sources.get("team-8")!.success?.();

    const error = root.querySelector<HTMLElement>("[data-calendar-error]")!;
    expect(error.textContent).toContain("Brass");
    expect(error.classList.contains("is-hidden")).toBe(false);
  });
});
