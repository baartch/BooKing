import { beforeEach, describe, expect, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { initHugerte } from "../src/hugerte-editor.js";

if (!GlobalRegistrator.isRegistered) GlobalRegistrator.register();

describe("HugeRTE embedded images", () => {
  let handlers: Record<string, (event: any) => void>;
  let textarea: HTMLTextAreaElement;
  let savedContent: string;
  let alerts: string[];

  beforeEach(() => {
    document.body.innerHTML = '<form><textarea id="template_body"></textarea></form>';
    textarea = document.querySelector("#template_body")!;
    handlers = {};
    savedContent = "";
    alerts = [];
    window.alert = (message?: string) => alerts.push(message ?? "");
    const editor = {
      on: (event: string, callback: (event: any) => void) => { handlers[event] = callback; },
      save: () => { textarea.value = savedContent; },
      editorUpload: {
        blobCache: {
          getByUri: (uri: string) => uri === "blob:https://example.test/image"
            ? { blob: () => new Blob([], { type: "image/png" }), base64: () => "AQID" }
            : undefined,
        },
      },
    };
    (window as { hugerte?: unknown }).hugerte = {
      init: (options: { setup: (editor: unknown) => void }) => {
        options.setup(editor);
        return Promise.resolve();
      },
    };
    initHugerte({ selector: "#template_body" });
  });

  test("source view exposes embedded image data instead of a temporary blob", () => {
    const event = {
      source_view: true,
      content: '<p>Text</p><img src="blob:https://example.test/image" alt="hello"><img src="https://example.test/photo.png">',
    };
    handlers.GetContent?.(event);
    expect(event.content).toBe('<p>Text</p><img src="data:image/png;base64,AQID" alt="hello"><img src="https://example.test/photo.png">');
  });

  test("blocks a form submission when an image still has an unrecoverable blob URL", () => {
    savedContent = '<p>Changed</p><img src="blob:https://example.test/missing">';
    const event = new Event("submit", { cancelable: true });
    handlers.Submit(event);
    expect(event.defaultPrevented).toBe(true);
    expect(alerts.length).toBe(1);
  });

  test("allows submitting embedded and ordinary image URLs", () => {
    savedContent = '<img src="data:image/png;base64,AQID"><img src="https://example.test/photo.png">';
    const event = new Event("submit", { cancelable: true });
    handlers.Submit(event);
    expect(event.defaultPrevented).toBe(false);
    expect(alerts).toEqual([]);
  });
});
