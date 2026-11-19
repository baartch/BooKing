import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

const loadRenderer = async () => {
  const module = await import("../src/linked-objects.ts").catch(() => null);
  expect(module?.renderLinkedObjectPills).toBeFunction();
  return module!.renderLinkedObjectPills;
};

test("renders the default empty state", async () => {
  const render = await loadRenderer();
  const container = document.createElement("span");

  render(container, []);

  expect(container.className).toBe("");
  expect(container.textContent).toBe("No links yet");
  expect(container.firstElementChild?.className).toBe("has-text-grey is-size-7");
});

test("renders labels as text and uses anchors only for linked items", async () => {
  const render = await loadRenderer();
  const container = document.createElement("span");

  render(container, [
    { type: "contact", id: 4, label: "<img src=x onerror=alert(1)>", url: "/contact?id=4" },
    { type: "unknown", id: 9, label: "Plain label" },
  ]);

  const [linked, displayOnly] = Array.from(container.children) as HTMLElement[];
  expect(linked.tagName).toBe("A");
  expect(linked.getAttribute("href")).toBe("/contact?id=4");
  expect(linked.querySelector("span:last-child")?.textContent).toBe("<img src=x onerror=alert(1)>");
  expect(linked.querySelector("img")).toBeNull();
  expect(displayOnly.tagName).toBe("SPAN");
  expect(displayOnly.getAttribute("href")).toBeNull();
  expect(displayOnly.textContent).toBe("Plain label");
});

test("uses known type icons, fallback icons, and explicit trusted icons", async () => {
  const render = await loadRenderer();
  const container = document.createElement("span");

  render(container, [
    { type: "contact", id: 1, label: "Contact" },
    { type: "mystery", id: 2, label: "Unknown" },
    { type: "mystery", id: 3, label: "Custom", icon: "fa-star" },
  ]);

  expect(container.children[0]?.querySelector("i")?.className).toBe("fa-solid fa-user");
  expect(container.children[1]?.querySelector("i")?.className).toBe("fa-solid fa-link");
  expect(container.children[2]?.querySelector("i")?.className).toBe("fa-solid fa-star");
});

test("supports a custom empty label and suppresses icons", async () => {
  const render = await loadRenderer();
  const empty = document.createElement("span");
  const noIcons = document.createElement("span");

  render(empty, [], { emptyText: "Nothing connected" });
  render(noIcons, [{ type: "contact", id: 1, label: "Jane" }], { showIcons: false });

  expect(empty.textContent).toBe("Nothing connected");
  expect(noIcons.querySelector("i")).toBeNull();
  expect(noIcons.textContent).toBe("Jane");
});

test("replaces only pill children and leaves the adjacent pencil intact", async () => {
  const render = await loadRenderer();
  const wrapper = document.createElement("div");
  wrapper.innerHTML = '<span class="detail-link-list"><span>Old</span></span><a class="detail-link-edit">Edit</a>';
  const pills = wrapper.querySelector<HTMLElement>(".detail-link-list")!;
  const pencil = wrapper.querySelector(".detail-link-edit");

  render(pills, [{ type: "venue", id: 5, label: "Hall" }]);

  expect(pills.textContent).toContain("Hall");
  expect(pills.textContent).not.toContain("Old");
  expect(wrapper.querySelector(".detail-link-edit")).toBe(pencil);
});
