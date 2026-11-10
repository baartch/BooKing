type CalendarTeam = {
  id: number;
  name: string;
  color: string;
  toggle?: HTMLInputElement;
};

type CalendarEventSource = {
  id: string;
  url: string;
  color: string;
  extraParams: { team_id: number };
  failure: () => void;
  success: () => void;
};

type CalendarEvent = {
  title: string;
  extendedProps?: {
    kind?: string;
    teamName?: string;
    description?: string;
    detailUrl?: string;
  };
};

type CalendarEventClickInfo = {
  event: CalendarEvent;
  el: HTMLElement;
  jsEvent: { preventDefault: () => void };
};

type CalendarOptions = {
  initialView: string;
  headerToolbar: { left: string; center: string; right: string };
  editable: boolean;
  eventSources: CalendarEventSource[];
  loading: (loading: boolean) => void;
  eventsSet: (events: CalendarEvent[]) => void;
  eventContent: (info: { event: CalendarEvent }) => { domNodes: Node[] };
  eventClick: (info: CalendarEventClickInfo) => void;
};

type CalendarInstance = {
  render: () => void;
  addEventSource: (source: CalendarEventSource) => unknown;
  getEventSourceById: (id: string) => { remove: () => void } | null;
};

type FullCalendarGlobal = {
  Calendar: new (element: HTMLElement, options: CalendarOptions) => CalendarInstance;
};

declare global {
  interface Window {
    FullCalendar?: FullCalendarGlobal;
  }
}

const TEAM_COLORS = [
  '#3273dc',
  '#d95f02',
  '#1b9e77',
  '#8e63ce',
  '#c0395a',
  '#008c95',
  '#8a6d3b',
  '#58636e',
];
const boundDialogs = new WeakSet<HTMLDialogElement>();
const dialogReturnFocus = new WeakMap<HTMLDialogElement, HTMLElement>();

export const initDashboardCalendar = (scope: ParentNode = document): void => {
  const roots: HTMLElement[] = [];
  if (scope instanceof HTMLElement && scope.matches('[data-dashboard-calendar]')) {
    roots.push(scope);
  }
  roots.push(...Array.from(scope.querySelectorAll<HTMLElement>('[data-dashboard-calendar]')));
  roots.forEach((root) => initializeCalendar(root));
};

const initializeCalendar = (root: HTMLElement): void => {
  if (root.dataset.calendarInitialized === 'true') return;
  root.dataset.calendarInitialized = 'true';

  const calendarElement = root.querySelector<HTMLElement>('[data-calendar]');
  const errorElement = root.querySelector<HTMLElement>('[data-calendar-error]');
  const loadingElement = root.querySelector<HTMLElement>('[data-calendar-loading]');
  const emptyElement = root.querySelector<HTMLElement>('[data-calendar-empty]');
  if (!calendarElement) return;
  calendarElement.tabIndex = -1;

  const showError = (message: string): void => {
    if (!errorElement) return;
    errorElement.textContent = message;
    errorElement.classList.toggle('is-hidden', message === '');
  };

  const toggles = Array.from(root.querySelectorAll<HTMLInputElement>('[data-calendar-team-toggle]'));
  const teams: CalendarTeam[] = toggles.length > 0
    ? toggles.map((toggle, index) => ({
        id: Number(toggle.dataset.teamId),
        name: toggle.dataset.teamName ?? 'Team',
        color: TEAM_COLORS[index % TEAM_COLORS.length],
        toggle,
      }))
    : Array.from(root.querySelectorAll<HTMLElement>('[data-calendar-team]')).map((team, index) => ({
        id: Number(team.dataset.teamId),
        name: team.dataset.teamName ?? 'Team',
        color: TEAM_COLORS[index % TEAM_COLORS.length],
      }));

  const validTeams = teams.filter((team) => Number.isInteger(team.id) && team.id > 0);
  let isLoading = false;
  let eventCount = 0;
  const failedTeams = new Set<number>();

  const updateFeedback = (): void => {
    if (loadingElement) loadingElement.classList.toggle('is-hidden', !isLoading);
    if (failedTeams.size > 0) {
      const failedNames = validTeams.filter((team) => failedTeams.has(team.id)).map((team) => team.name);
      showError(`Couldn't load calendar events for ${failedNames.join(', ')}.`);
    } else {
      showError('');
    }

    if (!emptyElement) return;
    const activeCount = validTeams.filter((team) => !team.toggle || team.toggle.checked).length;
    let message = '';
    if (validTeams.length === 0) {
      message = 'No teams are available for this calendar.';
    } else if (activeCount === 0) {
      message = 'Select at least one team to see calendar events.';
    } else if (!isLoading && eventCount === 0 && failedTeams.size === 0) {
      message = 'No shows or tasks in this date range.';
    }
    emptyElement.textContent = message;
    emptyElement.classList.toggle('is-hidden', message === '' || isLoading);
  };

  if (validTeams.length === 0) {
    updateFeedback();
    return;
  }

  const fullCalendar = window.FullCalendar;
  if (!fullCalendar?.Calendar) {
    showError('Calendar failed to load. Refresh the page to try again.');
    return;
  }

  const url = root.dataset.calendarEventsUrl;
  if (!url) {
    showError('Calendar events are unavailable. Refresh the page to try again.');
    return;
  }

  const sources = new Map<number, CalendarEventSource>();
  const makeSource = (team: CalendarTeam): CalendarEventSource => ({
    id: `team-${team.id}`,
    url,
    color: team.color,
    extraParams: { team_id: team.id },
    failure: () => {
      failedTeams.add(team.id);
      updateFeedback();
    },
    success: () => {
      failedTeams.delete(team.id);
      updateFeedback();
    },
  });
  validTeams.forEach((team) => sources.set(team.id, makeSource(team)));

  const initialSources = validTeams
    .filter((team) => !team.toggle || team.toggle.checked)
    .map((team) => sources.get(team.id)!);

  const calendar = new fullCalendar.Calendar(calendarElement, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    editable: false,
    eventSources: initialSources,
    eventContent: (info) => {
      const kind = info.event.extendedProps?.kind === 'show' ? 'Show' : 'Task';
      const kindLabel = document.createElement('span');
      kindLabel.className = 'dashboard-calendar-event-kind';
      kindLabel.textContent = `${kind} · `;
      const title = document.createElement('span');
      title.className = 'dashboard-calendar-event-title';
      title.textContent = info.event.title;
      return { domNodes: [kindLabel, title] };
    },
    eventClick: (info) => openEventPreview(root, calendarElement, info),
    loading: (loading) => {
      isLoading = loading;
      updateFeedback();
    },
    eventsSet: (events) => {
      eventCount = events.length;
      updateFeedback();
    },
  });

  validTeams.forEach((team) => {
    const toggle = team.toggle;
    if (!toggle) return;

    const swatch = document.createElement('span');
    swatch.className = 'dashboard-calendar-team-swatch';
    swatch.setAttribute('aria-hidden', 'true');
    swatch.style.backgroundColor = team.color;
    toggle.parentElement?.insertBefore(swatch, toggle);

    toggle.addEventListener('change', () => {
      const sourceId = `team-${team.id}`;
      if (toggle.checked) {
        if (!calendar.getEventSourceById(sourceId)) {
          calendar.addEventSource(sources.get(team.id)!);
        }
      } else {
        calendar.getEventSourceById(sourceId)?.remove();
        failedTeams.delete(team.id);
      }
      updateFeedback();
    });
  });

  calendar.render();
};

const openEventPreview = (
  root: HTMLElement,
  calendarElement: HTMLElement,
  info: CalendarEventClickInfo,
): void => {
  info.jsEvent.preventDefault();

  const dialogScope = root.parentElement ?? document;
  const dialog = dialogScope.querySelector<HTMLDialogElement>('[data-calendar-dialog]');
  const title = dialog?.querySelector<HTMLElement>('[data-calendar-dialog-title]');
  const team = dialog?.querySelector<HTMLElement>('[data-calendar-dialog-team]');
  const descriptionLabel = dialog?.querySelector<HTMLElement>('[data-calendar-dialog-description-label]');
  const description = dialog?.querySelector<HTMLElement>('[data-calendar-dialog-description]');
  const detailLink = dialog?.querySelector<HTMLAnchorElement>('[data-calendar-dialog-link]');
  const closeButton = dialog?.querySelector<HTMLButtonElement>('[data-calendar-dialog-close]');
  if (!dialog || !title || !team || !descriptionLabel || !description || !detailLink || !closeButton) return;

  if (!boundDialogs.has(dialog)) {
    dialog.addEventListener('close', () => {
      const target = dialogReturnFocus.get(dialog);
      dialogReturnFocus.delete(dialog);
      if (target?.isConnected) target.focus();
      else calendarElement.focus();
    });
    closeButton.addEventListener('click', () => dialog.close());
    boundDialogs.add(dialog);
  }

  const kind = info.event.extendedProps?.kind === 'show' ? 'show' : 'task';
  const eventDescription = info.event.extendedProps?.description ?? '';
  title.textContent = info.event.title;
  team.textContent = info.event.extendedProps?.teamName ?? '';
  team.hidden = team.textContent === '';
  descriptionLabel.textContent = kind === 'show' ? 'Notes' : 'Description';
  descriptionLabel.hidden = eventDescription === '';
  description.textContent = eventDescription;
  description.hidden = eventDescription === '';

  const detailUrl = info.event.extendedProps?.detailUrl ?? '';
  detailLink.hidden = true;
  if (detailUrl.startsWith('/') && !detailUrl.startsWith('//')) {
    detailLink.href = detailUrl;
    detailLink.textContent = `View ${kind} details`;
    detailLink.hidden = false;
  }

  dialogReturnFocus.set(dialog, info.el);
  if (!dialog.open) dialog.showModal();
  if (!detailLink.hidden) detailLink.focus();
  else closeButton.focus();
};

const startCalendars = (): void => initDashboardCalendar(document);
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startCalendars, { once: true });
} else {
  startCalendars();
}
