# Where to Use jQuery UI in the EMS

The EMS already uses jQuery UI for calendar drag-and-drop; adding Datepicker, Autocomplete and Sortable on a few pages gives a clear jQuery UI showcase without overdoing it.

## Already in the system

jQuery UI 1.14 is bundled through Vite (`resources/js/jquery-ui.js`, with its CSS imported in `resources/scss/app.scss`), with no CDN. It is used in one place: `resources/js/calendar.js`.

- **Draggable:** interview and event chips can be dragged.
- **Droppable:** each calendar day from today onward accepts a chip, then opens the move modal. Past days refuse drops.

Everything else (modals, tabs, date fields, selects) is native HTML or Bootstrap, so there is room to add jQuery UI where it helps.

## Recommended places

| Widget | Where | Why it fits | Effort |
| --- | --- | --- | --- |
| Datepicker | Leave filing (`leave/_mine`), employee form, offers (`recruitment/_offer-form`), calendar event form, report period, audit date range | One look for every date field, with min and max dates (for example no events in the past) | Low |
| Autocomplete | Employee search boxes on `employees/index`, `employee/directory`, `leave/index`, `recruitment/index` | Suggests names while typing, using the existing `q` inputs and one small JSON route | Medium |
| Sortable | Responsibilities, qualifications and requirements lists in `job-positions/form`; education and work history rows in `applicant/apply` | Drag to reorder instead of retyping | Medium |
| Dialog | Confirmations for archive, deactivate account and reject | A consistent confirm box; Bootstrap modals stay for forms | Low |
| Tooltip | Masked government IDs, status pills, calendar dots on phones | Explains short or hidden values on hover and focus | Low |
| Progressbar / Slider (optional) | Profile completeness; weekly hours for part-time staff | Small, visible touches if one more widget is wanted | Low |

Not recommended: **Accordion** and **Tabs**. Bootstrap tabs already run the employee profiles, and a second tab system would clash.

## Priority and cautions

Do Datepicker, Autocomplete and Sortable first. With the existing drag-and-drop, that shows four jQuery UI features in real pages, each doing a useful job.

- Keep every asset in Vite; never add a CDN link.
- Check the widgets in dark theme and at phone width, since jQuery UI's base theme can clash with Bootstrap 5.3.
- Keep keyboard use working (arrow keys in Autocomplete, a non-drag way to reorder).
- Skip jQuery UI where a native or Bootstrap control is already good enough.
- The Autocomplete route is new backend code, so it needs a Pest test, including that employees see only what their role allows.
