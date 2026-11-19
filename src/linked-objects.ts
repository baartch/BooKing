export type LinkedObjectItem = {
  type: string;
  id: number;
  label: string;
  url?: string;
  icon?: string;
};

const TYPE_ICONS: Readonly<Record<string, string>> = Object.freeze({
  contact: "fa-user",
  venue: "fa-location-dot",
  email: "fa-envelope",
  task: "fa-list-check",
  show: "fa-music",
  conversation: "fa-comments",
});

export function renderLinkedObjectPills(
  container: HTMLElement,
  items: LinkedObjectItem[],
  options?: { emptyText?: string; showIcons?: boolean },
): void {
  const doc = container.ownerDocument;
  if (items.length === 0) {
    const empty = doc.createElement("span");
    empty.className = "has-text-grey is-size-7";
    empty.textContent = options?.emptyText ?? "No links yet";
    container.replaceChildren(empty);
    return;
  }

  const pills = items.map((item) => {
    const pill = doc.createElement(item.url ? "a" : "span");
    pill.className = "detail-link-pill";
    if (item.url) {
      pill.setAttribute("href", item.url);
    }

    if (options?.showIcons !== false) {
      const icon = doc.createElement("span");
      icon.className = "icon is-small";
      const glyph = doc.createElement("i");
      const typeIcon = Object.prototype.hasOwnProperty.call(TYPE_ICONS, item.type)
        ? TYPE_ICONS[item.type]
        : "fa-link";
      glyph.className = `fa-solid ${item.icon || typeIcon}`;
      icon.appendChild(glyph);
      pill.appendChild(icon);
    }

    const label = doc.createElement("span");
    label.textContent = item.label;
    pill.appendChild(label);
    return pill;
  });

  container.replaceChildren(...pills);
}
