import { expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { readFileSync } from "node:fs";
import { join } from "node:path";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

test("outgoing arrows stay orange in normal, dark, and selected rows", () => {
  const style = document.createElement("style");
  style.textContent = readFileSync(join(import.meta.dir, "../app/public/css/communication.css"), "utf8");
  document.head.appendChild(style);
  document.body.innerHTML = '<ul class="conversation-list"><li><a><i class="conversation-arrow-outgoing"></i></a></li></ul>';
  const link = document.querySelector(".conversation-list a")!;
  const arrow = document.querySelector(".conversation-arrow-outgoing")!;
  const orange = () => {
    const color = getComputedStyle(arrow).color;
    const hex = color.match(/^#([\da-f]{6})$/i)?.[1];
    const rgb = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
    const values = hex ? [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16)) : rgb?.slice(1).map(Number);
    expect(values).toBeDefined();
    expect(values![0]).toBeGreaterThan(values![1]);
    expect(values![1]).toBeGreaterThan(values![2]);
  };
  try {
    document.documentElement.setAttribute("data-theme", "light");
    orange();
    document.documentElement.setAttribute("data-theme", "dark");
    orange();
    link.classList.add("is-active");
    orange();
  } finally {
    document.documentElement.removeAttribute("data-theme");
    document.body.innerHTML = "";
    style.remove();
  }
});
