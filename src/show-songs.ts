/**
 * Show setlist editor.
 *
 * Server-renders one row per saved song plus a trailing empty row inside
 * [data-show-songs-editor]. This module makes the editor interactive:
 * - grow: picking a song in the last row appends a new empty row
 * - duplicate lockout: a song picked in one select is disabled in the others
 * - removal: the × on a row removes it (clearing a pick to the empty option
 *   does the same); a trailing empty row always remains
 * - reorder: native HTML5 drag-and-drop between filled rows (desktop mouse,
 *   mirroring the pattern in src/email.ts)
 *
 * The selects are named `show_songs[]`, so DOM order is the posted order —
 * no hidden inputs to rebuild.
 */

const editorSelector = "[data-show-songs-editor]";
const rowSelector = "[data-show-song-row]";
const selectSelector = "select.show-song-select";
const removeSelector = ".show-song-remove";

export const initShowSongsEditor = (scope: ParentNode = document): void => {
  scope.querySelectorAll<HTMLElement>(editorSelector).forEach((editor) => {
    if (editor.dataset.showSongsBound === "true") {
      return;
    }
    editor.dataset.showSongsBound = "true";
    bindEditor(editor);
    recompute(editor);
  });
};

const rowFromEvent = (event: Event, editor: HTMLElement): HTMLElement | null => {
  if (!(event.target instanceof Element)) {
    return null;
  }
  const row = event.target.closest<HTMLElement>(rowSelector);
  return row && editor.contains(row) ? row : null;
};

const rowHasValue = (row: HTMLElement): boolean => {
  const select = row.querySelector<HTMLSelectElement>(selectSelector);
  return select !== null && select.value !== "";
};

const clearCues = (editor: HTMLElement): void => {
  editor.querySelectorAll<HTMLElement>(rowSelector).forEach((row) => {
    row.classList.remove("is-selected", "is-active");
  });
};

/**
 * Duplicate lockout + draggable flags. Must run after every mutation.
 */
const recompute = (editor: HTMLElement): void => {
  const selects = Array.from(editor.querySelectorAll<HTMLSelectElement>(selectSelector));
  const takenValues = selects
    .map((select) => select.value)
    .filter((value) => value !== "");

  selects.forEach((select) => {
    Array.from(select.options).forEach((option) => {
      if (option.value === "") {
        return;
      }
      option.disabled = option.value !== select.value && takenValues.includes(option.value);
    });
  });

  editor.querySelectorAll<HTMLElement>(rowSelector).forEach((row) => {
    row.draggable = rowHasValue(row);
  });
};

const cloneRowAsEmpty = (row: HTMLElement): HTMLElement => {
  const clone = row.cloneNode(true) as HTMLElement;
  clone.classList.remove("is-selected", "is-active");
  const select = clone.querySelector<HTMLSelectElement>(selectSelector);
  if (select) {
    select.value = "";
    Array.from(select.options).forEach((option) => {
      option.disabled = false;
    });
  }
  return clone;
};

/**
 * Keep exactly one trailing empty row: append one if the last row has a
 * value (or if no rows remain).
 */
const ensureTrailingEmptyRow = (editor: HTMLElement): void => {
  const rows = editor.querySelectorAll<HTMLElement>(rowSelector);
  if (rows.length === 0) {
    // Server contract guarantees one row at bind time; the zero-row case can
    // only occur after user removals, so a template was captured in bindEditor.
    const captured = (editor as HTMLElement & { __emptyRowTemplate?: HTMLElement | null }).__emptyRowTemplate;
    if (captured) {
      editor.appendChild(cloneRowAsEmpty(captured));
    }
    return;
  }
  if (!rowHasValue(rows[rows.length - 1])) {
    return;
  }
  editor.appendChild(cloneRowAsEmpty(rows[rows.length - 1]));
};

const bindEditor = (editor: HTMLElement): void => {
  // Capture a pristine empty row as the clone source for the zero-rows case.
  const rows = editor.querySelectorAll<HTMLElement>(rowSelector);
  (editor as HTMLElement & { __emptyRowTemplate?: HTMLElement | null }).__emptyRowTemplate =
    rows.length > 0 ? cloneRowAsEmpty(rows[rows.length - 1]) : null;

  let draggedRow: HTMLElement | null = null;

  editor.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.matches(selectSelector)) {
      return;
    }
    const row = rowFromEvent(event, editor);
    if (!row) {
      return;
    }

    if ((target as HTMLSelectElement).value === "") {
      // Clearing a pick is removal (the trailing empty row stays).
      if (editor.querySelectorAll(rowSelector).length > 1) {
        row.remove();
      }
    }
    ensureTrailingEmptyRow(editor);
    recompute(editor);
  });

  editor.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }
    const removeButton = event.target.closest<HTMLElement>(removeSelector);
    if (!removeButton || !editor.contains(removeButton)) {
      return;
    }
    const row = rowFromEvent(event, editor);
    if (!row) {
      return;
    }
    row.remove();
    ensureTrailingEmptyRow(editor);
    recompute(editor);
  });

  editor.addEventListener("dragstart", (event) => {
    const dragEvent = event as DragEvent;
    const row = rowFromEvent(event, editor);
    if (!row || !rowHasValue(row)) {
      event.preventDefault();
      return;
    }
    draggedRow = row;
    if (dragEvent.dataTransfer) {
      dragEvent.dataTransfer.effectAllowed = "move";
      dragEvent.dataTransfer.setData("text/plain", "");
    }
    row.classList.add("is-selected");
  });

  editor.addEventListener("dragover", (event) => {
    const dragEvent = event as DragEvent;
    const row = rowFromEvent(event, editor);
    // The trailing empty row is a valid target: dropping onto it places the
    // dragged song in the last filled position.
    if (!draggedRow || !row || row === draggedRow) {
      return;
    }
    event.preventDefault();
    if (dragEvent.dataTransfer) {
      dragEvent.dataTransfer.dropEffect = "move";
    }
    clearCues(editor);
    draggedRow.classList.add("is-selected");
    row.classList.add("is-active");
  });

  editor.addEventListener("drop", (event) => {
    const dragEvent = event as DragEvent;
    const row = rowFromEvent(event, editor);
    clearCues(editor);
    if (!draggedRow || !row || row === draggedRow) {
      draggedRow = null;
      return;
    }
    event.preventDefault();
    if (dragEvent.dataTransfer) {
      dragEvent.dataTransfer.dropEffect = "move";
    }
    row.parentNode?.insertBefore(draggedRow, row);
    draggedRow = null;
    recompute(editor);
  });

  editor.addEventListener("dragend", () => {
    draggedRow = null;
    clearCues(editor);
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initShowSongsEditor();
});

document.addEventListener("tab:activated", () => {
  initShowSongsEditor();
});

document.addEventListener("htmx:afterSwap", (event) => {
  const customEvent = event as CustomEvent<{ target?: HTMLElement; elt?: HTMLElement }>;
  const target =
    customEvent.detail?.target ??
    customEvent.detail?.elt ??
    ((event.target as HTMLElement | null) ?? null);
  if (target) {
    initShowSongsEditor(target);
  }
});
