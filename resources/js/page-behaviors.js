import { initAdminWidgets, refreshAdminWidgets } from './admin-widgets.js';
import $ from './jquery.js';

// Small behaviors shared by the HR pages. Everything is attached with delegated handlers on the document, and applyDependentFields()
// is run on a page or on a piece of page that was just placed on it (the Recruitment application window), so it also works there.

// A table row marked data-row-href opens that address when clicked anywhere on it (links and buttons inside still work on their own).
// A row also marked data-row-modal opens it in the Recruitment application window instead (see below).
function isInteractiveClick(event) {
    return $(event.target).closest('a, button, input, select, textarea, label, summary').length > 0;
}

$(document).on('click', 'tr[data-row-href]:not([data-row-modal])', function (event) {
    if (isInteractiveClick(event)) {
        return;
    }

    window.location.assign(this.getAttribute('data-row-href'));
});

// A field marked data-mode-field="x" is shown only while the form's mode (face to face or online) select is x.
// A field marked data-part-time-fields is shown only while the employment type select is Part-time (a full-time contract has no end).
function showOnly($fields, isShown) {
    $fields.toggleClass('d-none', !isShown);
    $fields.find('input, select, textarea').prop('disabled', !isShown);
    refreshAdminWidgets($fields);
}

function applyDependentFields(root) {
    $(root).find('form').each(function () {
        const $form = $(this);
        const mode = $form.find('[data-mode-select] select').val();
        const employmentType = $form.find('[data-employment-type] select').val();

        if (mode !== undefined) {
            $form.find('[data-mode-field]').each(function () {
                showOnly($(this), this.getAttribute('data-mode-field') === mode);
            });
        }

        if (employmentType !== undefined) {
            showOnly($form.find('[data-part-time-fields]'), employmentType === 'part_time');
        }
    });
}

$(document).on('change', '[data-mode-select] select, [data-employment-type] select', function () {
    applyDependentFields($(this).closest('form').parent());
});

$(function () {
    applyDependentFields(document);
});

// A row (or an Open link) on the Recruitment list shows the application in a window over the list. The window is the application page itself, fetched and
// placed inside it. Forms in the window are sent with fetch (with the application page as the referrer, so the server's redirect and
// any validation message come back to the application), and the window shows the answer. The list is refreshed when the window closes.
let changed = false;

function showInModal(html, url) {
    const modalElement = document.getElementById('application-modal');
    const body = modalElement.querySelector('[data-application-modal-body]');
    const page = new DOMParser().parseFromString(html, 'text/html');
    const area = page.querySelector('.app-content');
    const main = page.querySelector('[data-application-content]')
        ?? (area ? Array.from(area.children).find((element) => !element.classList.contains('alert')) : null);

    // Emptied with jQuery, so the widgets of the previous content are taken down with it (their menus sit outside it).
    $(body).empty();

    if (!main) {
        $(body).html(`<p class="mb-0">The application could not be shown here. <a href="${url}">Open it as a page</a>.</p>`);

        return;
    }

    area?.querySelectorAll(':scope > .alert').forEach((alert) => body.append(document.importNode(alert, true)));
    main.querySelectorAll('a.btn-outline-secondary[href$="/recruitment"]').forEach((link) => link.closest('div')?.remove());
    body.append(document.importNode(main, true));
    body.dataset.url = url;
    initAdminWidgets(body);
    applyDependentFields(body);
}

async function openApplicationModal(url) {
    const modalElement = document.getElementById('application-modal');

    changed = false;
    $(modalElement).find('[data-application-modal-body]').html('<p class="text-body-secondary mb-0">Loading...</p>');
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (response.redirected && new URL(response.url).pathname === '/login') {
            window.location.assign(url);

            return;
        }

        showInModal(await response.text(), url);
    } catch {
        $(modalElement).find('[data-application-modal-body]').html(`<p class="mb-0">The application could not be loaded. <a href="${url}">Open it as a page</a>.</p>`);
    }
}

$(document).on('click', 'a[data-application-modal]', function (event) {
    event.preventDefault();
    openApplicationModal(this.getAttribute('href'));
});

$(document).on('click', 'tr[data-row-modal]', function (event) {
    if (isInteractiveClick(event)) {
        return;
    }

    openApplicationModal(this.getAttribute('data-row-href'));
});

$(document).on('submit', '#application-modal form', async function (event) {
    event.preventDefault();

    const form = this;
    const body = form.closest('[data-application-modal-body]');
    const url = body.dataset.url;
    const method = (form.getAttribute('method') || 'get').toUpperCase();

    form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });

    try {
        const response = await fetch(form.getAttribute('action') || url, {
            method,
            body: method === 'GET' ? undefined : new FormData(form),
            credentials: 'same-origin',
            referrer: url,
            redirect: 'follow',
        });

        if (response.redirected && new URL(response.url).pathname === '/login') {
            window.location.assign(url);

            return;
        }

        changed = true;
        showInModal(await response.text(), url);
    } catch {
        form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false; });
        body.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">That did not go through. Please try again.</div>');
    }
});

$(document).on('hidden.bs.modal', '#application-modal', function () {
    if (changed) {
        window.location.reload();
    }
});

// A table row that opens a modal (data-bs-toggle="modal" on the row) also opens with Enter or Space.
$(document).on('keydown', 'tr[data-bs-toggle="modal"]', function (event) {
    if ((event.key === 'Enter' || event.key === ' ') && event.target === this) {
        event.preventDefault();
        window.bootstrap.Modal.getOrCreateInstance(document.querySelector(this.getAttribute('data-bs-target'))).show();
    }
});

// A modal marked data-open-on-load opens as the page loads (a form inside it came back with errors).
$(() => {
    document.querySelectorAll('.modal[data-open-on-load]').forEach((element) => {
        window.bootstrap.Modal.getOrCreateInstance(element).show();
    });
});

// Calendar filters: the Event Type and Status pills hide the chips (data-calendar-type, data-calendar-status) that are not selected.
function applyCalendarFilters() {
    const selected = (selector) => $(selector).filter(':checked').map((_, input) => input.value).get();
    const types = $('[data-calendar-type-filter]').length ? selected('[data-calendar-type-filter]') : null;
    const statuses = $('[data-calendar-status-filter]').length ? selected('[data-calendar-status-filter]') : null;

    $('.calendar-event').each(function () {
        const type = this.getAttribute('data-calendar-type');
        const status = this.getAttribute('data-calendar-status');
        const hidden = (types !== null && type !== null && !types.includes(type))
            || (statuses !== null && status !== null && !statuses.includes(status));

        $(this).toggleClass('d-none', hidden);
    });
}

$(document).on('change', '[data-calendar-type-filter], [data-calendar-status-filter]', applyCalendarFilters);
$(document).on('click', '[data-calendar-clear]', () => {
    $('[data-calendar-type-filter], [data-calendar-status-filter]').prop('checked', true);
    applyCalendarFilters();
});

// Tabs marked data-remember-tab reopen on the tab they were left on (a form on a tab sends the page back to this one).
$(() => {
    document.querySelectorAll('[data-remember-tab]').forEach((nav) => {
        const key = `tab:${nav.getAttribute('data-remember-tab')}`;

        try {
            const saved = sessionStorage.getItem(key);
            const button = saved ? nav.querySelector(`[data-bs-target="${saved}"]`) : null;

            if (button) {
                window.bootstrap.Tab.getOrCreateInstance(button).show();
            }
        } catch {
            // Storage can be blocked; the first tab opens.
        }

        nav.addEventListener('shown.bs.tab', (event) => {
            try {
                sessionStorage.setItem(key, event.target.getAttribute('data-bs-target'));
            } catch {
                // Nothing to remember it with.
            }
        });
    });
});

// A link to a page's #security opens its Security tab when it has one (the header's Account menu links there).
$(() => {
    const button = window.location.hash === '#security' ? document.querySelector('[data-bs-target="#profile-security"]') : null;

    if (button) {
        window.bootstrap.Tab.getOrCreateInstance(button).show();
    }
});

// The calendar's selected-day panel (components/calendar-day-panel.blade.php): click a day on the month grid and its events are
// listed on the right, built from the chips already on that day. Choosing an event clicks its chip (the HR details window) or, on
// the employee calendar, fills the details card.
function fillDayPanel($day) {
    const $panel = $('[data-day-panel]');

    if (!$panel.length || !$day.length) {
        return;
    }

    const date = $day.attr('data-calendar-day');
    const heading = new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    const $chips = $day.find('.calendar-event').not('.d-none');
    const $list = $panel.find('[data-day-events]').empty();

    $('.calendar-day.is-selected').removeClass('is-selected');
    $day.addClass('is-selected');
    $panel.find('[data-day-title]').text(heading);
    $panel.find('[data-day-empty]').toggleClass('d-none', $chips.length > 0);
    $panel.find('[data-day-details]').addClass('d-none');
    $panel.find('[data-day-details-empty]').removeClass('d-none');

    $chips.each(function () {
        const chip = this;
        const $item = $('<button>', { type: 'button', class: 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2' });

        $item.append($('<span>', { text: $(chip).find('.calendar-event-label').text() }));

        const status = chip.getAttribute('data-status') ?? chip.getAttribute('data-calendar-status');

        if (status) {
            $item.append($('<span>', { class: 'ems-chip text-capitalize', text: status }));
        }

        $item.on('click', () => {
            if (chip.tagName === 'BUTTON') {
                chip.click();

                return;
            }

            $list.children().removeClass('active');
            $item.addClass('active');
            $panel.find('[data-day-detail="title"]').text(chip.getAttribute('data-title') ?? $(chip).find('.calendar-event-label').text());
            $panel.find('[data-day-detail="when"]').text(chip.getAttribute('data-when') ?? heading);
            $panel.find('[data-day-detail="status"]').text(chip.getAttribute('data-status-label') ?? '—');
            $panel.find('[data-day-detail="place"]').text(chip.getAttribute('data-place') || '—');
            $panel.find('[data-day-details]').removeClass('d-none');
            $panel.find('[data-day-details-empty]').addClass('d-none');
        });

        $list.append($item);
    });
}

function selectedCalendarDay() {
    return $('.calendar-day.is-selected').first();
}

$(document).on('click', '.calendar-day[data-calendar-day]', function () {
    fillDayPanel($(this));
});

$(document).on('change', '[data-calendar-type-filter], [data-calendar-status-filter]', () => fillDayPanel(selectedCalendarDay()));
$(document).on('click', '[data-calendar-clear]', () => fillDayPanel(selectedCalendarDay()));

$(() => {
    const $days = $('.calendar-day[data-calendar-day]');

    if (!$days.length || !$('[data-day-panel]').length) {
        return;
    }

    fillDayPanel($days.filter('.is-today').first().length ? $days.filter('.is-today').first() : $days.not('.is-outside').first());
});
