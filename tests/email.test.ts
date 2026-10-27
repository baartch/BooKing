import { describe, expect, test, beforeEach } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

GlobalRegistrator.register();

describe("confirmEmptySend", () => {
  let confirmMessages: string[];

  const buildForm = (subject: string, body: string): HTMLFormElement => {
    document.body.innerHTML = "";

    const form = document.createElement("form");
    form.setAttribute("data-email-compose-form", "");

    const subjectInput = document.createElement("input");
    subjectInput.id = "email_subject";
    subjectInput.value = subject;

    const bodyTextarea = document.createElement("textarea");
    bodyTextarea.id = "email_body";
    bodyTextarea.value = body;

    form.append(subjectInput, bodyTextarea);
    document.body.append(form);

    return form;
  };

  /**
   * Fakes window.hugerte so that getHugerteEditor("#email_body") resolves.
   * The fake editor's save() mirrors TinyMCE/HugeRTE behavior: it syncs the
   * editor content back into the underlying textarea.
   */
  const setEditorContent = (content: string | null): void => {
    (window as { hugerte?: unknown }).hugerte = {
      get: (id: string) => {
        if (id !== "email_body") {
          return null;
        }
        const textarea =
          document.querySelector<HTMLTextAreaElement>("#email_body");
        return {
          save: () => {
            if (textarea) {
              textarea.value = content ?? "";
            }
          },
        };
      },
    };
  };

  beforeEach(() => {
    confirmMessages = [];
    window.confirm = (message?: string): boolean => {
      confirmMessages.push(message ?? "");
      return false;
    };
  });

  test("does not confirm when the editor holds body content (schedule pre-check)", async () => {
    const { confirmEmptySend } = await import("../src/email.js");

    const form = buildForm("Subject", "");
    setEditorContent("<p>Real body from the editor</p>");

    expect(confirmEmptySend(form)).toBe(true);
    expect(confirmMessages).toEqual([]);
  });

  test("still confirms when body is truly empty", async () => {
    const { confirmEmptySend } = await import("../src/email.js");

    const form = buildForm("Subject", "");
    setEditorContent(null);

    expect(confirmEmptySend(form)).toBe(false);
    expect(confirmMessages).toEqual(["Body is empty. Send anyway?"]);
  });
});