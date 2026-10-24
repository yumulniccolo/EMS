import $ from './jquery.js';

// Small behaviors shared by the HR pages. Everything is attached with delegated handlers on the document, and applyDependentFields()
// is run on a page or on a piece of page that was just placed on it (the Recruitment application window), so it also works there.

// A table row marked data-row-href opens that address when clicked anywhere on it (links and buttons inside still work on their own).
$(document).on('click', 'tr[data-row-href]', function (event) {
    if ($(event.target).closest('a, button, input, select, label').length) {
        return;
    }

    window.location.assign(this.getAttribute('data-row-href'));
});

// A field marked data-mode-field="x" is shown only while the form's mode (face to face or online) select is x.
// A field marked data-part-time-fields is shown only while the employment type select is Part-time (a full-time contract has no end).
function showOnly($fields, isShown) {
    $fields.toggleClass('d-none', !isShown);
    $fields.find('input, select, textarea').prop('disabled', !isShown);
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

// Open on the Recruitment list shows the application in a window over the list. The window is the application page itself, fetched and
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

    body.innerHTML = '';

    if (!main) {
        body.innerHTML = `<p class="mb-0">The application could not be shown here. <a href="${url}">Open it as a page</a>.</p>`;

        return;
    }

    area?.querySelectorAll(':scope > .alert').forEach((alert) => body.append(document.importNode(alert, true)));
    main.querySelectorAll('a.btn-outline-secondary[href$="/recruitment"]').forEach((link) => link.closest('div')?.remove());
    body.append(document.importNode(main, true));
    body.dataset.url = url;
    applyDependentFields(body);
}

$(document).on('click', 'a[data-application-modal]', async function (event) {
    event.preventDefault();

    const modalElement = document.getElementById('application-modal');
    const url = this.getAttribute('href');

    changed = false;
    modalElement.querySelector('[data-application-modal-body]').innerHTML = '<p class="text-body-secondary mb-0">Loading...</p>';
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (response.redirected && new URL(response.url).pathname === '/login') {
            window.location.assign(url);

            return;
        }

        showInModal(await response.text(), url);
    } catch {
        modalElement.querySelector('[data-application-modal-body]').innerHTML = `<p class="mb-0">The application could not be loaded. <a href="${url}">Open it as a page</a>.</p>`;
    }
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
