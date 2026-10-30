import $ from './jquery.js';
import { showToast } from './toast.js';

// The jQuery UI widgets of the HR admin pages (layouts/admin.blade.php marks its shell data-admin-ui): Button on .btn
// buttons, Checkboxradio on checkboxes and radios, Selectmenu on selects, Progressbar on [data-progressbar], Sortable on
// [data-sortable-list], and a Dialog that asks before a form marked data-confirm is sent. The native controls stay in the
// page, so forms send the same values and existing change handlers keep working. Their look follows the native state
// (:checked, :disabled) in _widgets.scss, so a script that changes a control directly cannot leave the widget showing
// something else. initAdminWidgets() is safe to run again on a piece of page that was just placed on it.
//
// The Employee and Applicant layouts (marked data-portal-ui) get the same widgets wherever their pages have the same
// controls; the public Careers site, Login, and account creation do not.
const widgetRoots = '[data-admin-ui], [data-portal-ui]';

// Every .btn: buttons, submit inputs, and links styled as buttons, including those that open or close a Bootstrap modal
// or dropdown (Button only adds classes, so Bootstrap still handles their clicks). The labels of .btn-check inputs are
// left to Checkboxradio.
function initButtons($root) {
    $root.find('.btn').not('label').each(function () {
        if (!$(this).button('instance')) {
            $(this).button();
        }
    });
}

// A .btn-check input (a pill or button group) keeps its button look; a .form-check-input gets the checkbox or radio mark.
function initCheckboxes($root) {
    $root.find('input.btn-check, input.form-check-input').filter('[type="checkbox"], [type="radio"]').each(function () {
        const $input = $(this);

        if ($input.checkboxradio('instance') || !this.labels?.length || $input.closest('.form-switch').length) {
            return;
        }

        $input.checkboxradio({ icon: !$input.hasClass('btn-check') });
    });
}

function markSelectedOption($select) {
    const index = $select[0].selectedIndex;

    $select.selectmenu('menuWidget').children('li').each(function () {
        $(this).children('.ui-menu-item-wrapper').toggleClass('is-selected', $(this).data('ui-selectmenu-item')?.index === index);
    });
}

// The button wears the select's own classes (form-select, form-select-sm, w-auto, is-invalid), so it looks like the
// other fields. The select stays in the page, hidden but still checked by the browser when the form is sent, and it
// hears a real change event when an option is picked (auto-submit filters and dependent fields listen for it).
function initSelectMenus($root) {
    $root.find('select').not('[multiple], [size], [data-native-select]').each(function () {
        const select = this;
        const $select = $(this);
        const $modal = $select.closest('.modal');
        let isWidgetChange = false;

        if ($select.selectmenu('instance')) {
            return;
        }

        $select.selectmenu({
            width: false,
            appendTo: $modal.length ? $modal : null,
            position: { my: 'left top', at: 'left bottom', collision: 'flipfit' },
            classes: { 'ui-selectmenu-button': select.className },
            open: () => markSelectedOption($select),
            change: () => {
                isWidgetChange = true;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                isWidgetChange = false;
            },
        });

        $select.addClass('ui-selectmenu-native').css('display', '').attr({ tabindex: '-1', 'aria-hidden': 'true' });

        // A script that sets the value and sends change itself is shown too.
        $select.on('change', () => {
            if (!isWidgetChange) {
                $select.selectmenu('refresh');
            }
        });
    });
}

// A long select marked data-combobox (and data-native-select, so Selectmenu leaves it alone) gets a text field that
// filters its options as you type: jQuery UI Autocomplete, fed from the select's own options. The select stays in the
// form, hidden, and holds the value, so the form sends it and checks it (required) as before. Text that is not one of
// the options is cleared when the field is left or Enter is pressed.
function initComboboxes($root) {
    $root.find('select[data-combobox]').each(function () {
        const select = this;
        const $select = $(this);
        const $modal = $select.closest('.modal');

        if ($select.data('comboboxInput')) {
            return;
        }

        const items = () => $select.children('option').filter((index, option) => option.value !== '').map((index, option) => ({
            label: option.text.trim(),
            value: option.text.trim(),
            option,
        })).get();
        const selected = select.value !== '' ? select.options[select.selectedIndex] : null;
        const $input = $('<input>', {
            type: 'text',
            id: `${select.id}-combobox`,
            class: `${select.className} ui-combobox-input`,
            placeholder: $select.children('option[value=""]').text().trim(),
            autocomplete: 'off',
            value: selected ? selected.text.trim() : '',
        }).insertAfter($select);

        const choose = (option) => {
            const value = option ? option.value : '';

            if (select.value !== value) {
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        const keepOnlyAnOption = () => {
            const typed = $input.val().trim().toLowerCase();
            const match = typed ? items().find((item) => item.label.toLowerCase() === typed) : null;

            $input.val(match ? match.label : '');
            choose(match?.option ?? null);
        };

        $(select.labels).attr('for', $input.attr('id'));
        $select.addClass('ui-combobox-native').attr({ tabindex: '-1', 'aria-hidden': 'true' }).data('comboboxInput', $input);

        $input.autocomplete({
            delay: 0,
            minLength: 0,
            appendTo: $modal.length ? $modal : null,
            source: (request, response) => {
                const matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), 'i');

                response(items().filter((item) => matcher.test(item.label)));
            },
            select: (event, ui) => choose(ui.item.option),
            change: (event, ui) => {
                if (!ui.item) {
                    keepOnlyAnOption();
                }
            },
        });

        // A click shows the whole list; Enter on typed text (with the list closed) settles it before the form is sent.
        $input.on('click', () => {
            if (!$input.autocomplete('widget').is(':visible')) {
                $input.autocomplete('search', '');
            }
        });
        $input.on('keydown', (event) => {
            if (event.key === 'Enter' && !$input.autocomplete('widget').is(':visible')) {
                keepOnlyAnOption();
            }
        });
    });
}

// The Recruitment page's panels (resources/views/recruitment/show.blade.php): each [data-accordion] group opens one
// [data-accordion-header] panel at a time. A header marked data-accordion-open (the form a scheduling conflict was
// reported on) starts open; otherwise all start closed.
function initAccordions($root) {
    $root.find('[data-accordion]').each(function () {
        const $accordion = $(this);
        const $headers = $accordion.find('[data-accordion-header]');
        const openIndex = $headers.index($headers.filter('[data-accordion-open]'));

        if ($accordion.accordion('instance') || !$headers.length) {
            return;
        }

        $accordion.accordion({
            header: '[data-accordion-header]',
            collapsible: true,
            active: openIndex >= 0 ? openIndex : false,
            heightStyle: 'content',
            icons: false,
            animate: prefersReducedMotion() ? false : 200,
        });
    });
}

// A document preview ([data-resizable-viewer], the PDF on the Recruitment page) is made taller or shorter by dragging
// its bottom edge (jQuery UI Resizable). Its width stays the card's, so the layout keeps flowing with the window.
function viewerMaxHeight() {
    return Math.max(Math.round(window.innerHeight * 0.9), 400);
}

function initResizableViewers($root) {
    $root.find('[data-resizable-viewer]').each(function () {
        const $viewer = $(this);

        if ($viewer.resizable('instance')) {
            return;
        }

        $viewer.resizable({
            handles: 's',
            minHeight: 240,
            maxHeight: viewerMaxHeight(),
            // The pointer passes over the document while dragging, and the frame would take the mouse and end the drag.
            start: () => $viewer.children('iframe').css('pointer-events', 'none'),
            stop: () => $viewer.children('iframe').css('pointer-events', ''),
        });
    });
}

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

// A short flash in the theme's primary colour on the row that was just moved (jQuery UI's Highlight effect).
function highlightMovedRow($row) {
    if (prefersReducedMotion()) {
        return;
    }

    const primary = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary-rgb').trim();

    $row.stop(true, true).effect('highlight', { color: `rgba(${primary || '25, 138, 88'}, .25)` }, 700);
}

function progressValue($bar, max) {
    const raw = $bar.attr('data-value');
    const value = Number(raw);

    // No value yet means the amount is not known: the bar shows as indeterminate.
    if (raw === undefined || raw === '' || !Number.isFinite(value)) {
        return false;
    }

    return Math.min(Math.max(value, 0), max);
}

// data-value (and data-max, default 100) set the bar; changing data-value later moves it.
function initProgressbars($root) {
    $root.find('[data-progressbar]').each(function () {
        const $bar = $(this);
        const max = Math.max(Number($bar.attr('data-max')) || 100, 1);

        if ($bar.progressbar('instance')) {
            return;
        }

        $bar.progressbar({ max, value: progressValue($bar, max) });

        new MutationObserver(() => $bar.progressbar('value', progressValue($bar, max)))
            .observe(this, { attributes: true, attributeFilter: ['data-value'] });
    });
}

// The offices, colleges, job titles, and job postings have no saved order yet (their tables have no position column),
// so a new order lasts until the page reloads, and the HR Admin is told so once.
function orderChanged($list) {
    if ($list.data('orderNoticeShown')) {
        return;
    }

    $list.data('orderNoticeShown', true);
    showToast('The new order shows on this page only. It is not saved, so it resets when the page reloads.');
}

// Rows move by their handle only (a drag never opens the row), by mouse or touch, or with the arrow keys on a focused handle.
function initSortableLists($root) {
    $root.find('[data-sortable-list]').each(function () {
        const $list = $(this);

        if ($list.sortable('instance') || $list.children('[data-sortable-id]').length < 2) {
            return;
        }

        $list.sortable({
            items: '> [data-sortable-id]',
            handle: '.sortable-handle',
            // jQuery UI ignores a press that starts on any button (its default cancel option); the handle is a
            // button so the arrow keys can reach it, so it is taken out of that list.
            cancel: 'input, textarea, button:not(.sortable-handle), select, option',
            axis: 'y',
            containment: 'parent',
            tolerance: 'pointer',
            cursor: 'grabbing',
            placeholder: 'sortable-placeholder',
            forcePlaceholderSize: true,
            // The row itself is dragged. Its cells are given the widths they have in the table before jQuery UI takes it
            // out of the table's layout (by the start event they would already have shrunk to their content).
            helper: (event, $row) => {
                $row.children().each(function () {
                    $(this).css('width', $(this).outerWidth());
                });

                return $row;
            },
            stop: (event, ui) => ui.item.children().css('width', ''),
            // Sent only when the row really changed place.
            update: (event, ui) => {
                orderChanged($list);
                highlightMovedRow(ui.item);
            },
        });

        $list.on('keydown', '.sortable-handle', function (event) {
            if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
                return;
            }

            event.preventDefault();

            const $row = $(this).closest('[data-sortable-id]');
            const isUp = event.key === 'ArrowUp';
            const $neighbour = isUp ? $row.prev('[data-sortable-id]') : $row.next('[data-sortable-id]');

            if (!$neighbour.length) {
                return;
            }

            if (isUp) {
                $row.insertBefore($neighbour);
            } else {
                $row.insertAfter($neighbour);
            }

            $(this).trigger('focus');
            orderChanged($list);
            highlightMovedRow($row);
        });
    });
}

/**
 * Adds the widgets to the admin controls inside root that do not have them yet.
 */
export function initAdminWidgets(root) {
    const $root = $(root);

    if (!$root.closest(widgetRoots).length) {
        return;
    }

    // Each kind is added on its own, so a control one widget cannot take does not stop the others (the sortable lists
    // used to come last and would be left without Sortable).
    [initSortableLists, initCheckboxes, initButtons, initComboboxes, initSelectMenus, initProgressbars, initAccordions, initResizableViewers].forEach((initWidget) => {
        try {
            initWidget($root);
        } catch (error) {
            console.error(`jQuery UI: ${initWidget.name} failed.`, error);
        }
    });
}

/**
 * Shows the current value and enabled state of the selects, checkboxes, and radios inside root, for a script that
 * changed them directly.
 */
export function refreshAdminWidgets(root) {
    $(root).find('select').addBack('select').each(function () {
        if ($(this).selectmenu('instance')) {
            $(this).selectmenu('option', 'disabled', this.disabled).selectmenu('refresh');
        }
    });

    $(root).find('.ui-checkboxradio').addBack('.ui-checkboxradio').checkboxradio('refresh');
}

// ---------------------------------------------------------------- confirmation before a form is sent

// A form marked data-confirm="Message" asks first. data-confirm-title, data-confirm-button (the action's label), and
// data-confirm-cancel (the label that keeps things as they are) word the question. Cancel, the close icon, and Escape
// all leave the form unsent; the action sends it once, with the button that was clicked, and its buttons stay off
// while the next page loads.
const confirmedForms = new WeakSet();

function sendConfirmed(form, submitter) {
    confirmedForms.add(form);

    try {
        form.requestSubmit(submitter ?? undefined);
    } finally {
        confirmedForms.delete(form);
    }

    $(form.elements).filter('button:not([type]), button[type="submit"], input[type="submit"]')
        .prop('disabled', true)
        .attr('data-confirm-sent', '');
}

function askToConfirm(form, submitter) {
    const $form = $(form);
    const $modal = $form.closest('.modal');
    const $dialog = $('<div>').append($('<p>', { class: 'mb-0', text: $form.attr('data-confirm') }));
    let isSending = false;

    $dialog.dialog({
        title: $form.attr('data-confirm-title') || 'Please confirm',
        modal: true,
        draggable: false,
        resizable: false,
        height: 'auto',
        width: 448,
        // Inside a Bootstrap modal the dialog joins it, so the modal's focus trap lets it take focus.
        appendTo: $modal.length ? $modal : 'body',
        closeText: 'Cancel',
        classes: { 'ui-dialog': 'ems-dialog', 'ui-dialog-titlebar-close': 'btn-close' },
        buttons: [
            {
                text: $form.attr('data-confirm-cancel') || 'Cancel',
                class: 'btn btn-outline-secondary',
                click: () => $dialog.dialog('close'),
            },
            {
                text: $form.attr('data-confirm-button') || 'Confirm',
                class: 'btn btn-danger',
                click: () => {
                    if (isSending) {
                        return;
                    }

                    isSending = true;
                    sendConfirmed(form, submitter);
                    $dialog.dialog('close');
                },
            },
        ],
        close: () => $dialog.dialog('destroy').remove(),
    });

    // Escape closes the dialog only, not a Bootstrap modal underneath it.
    $dialog.dialog('widget').on('keydown', (event) => {
        if (event.key === 'Escape') {
            event.stopPropagation();
        }
    });
}

$(function () {
    const $root = $(widgetRoots);

    if (!$root.length) {
        return;
    }

    initAdminWidgets($root);

    // Caught on the shell on the way down (capture), so a submission waiting for the answer reaches no other submit
    // listener: not the form's own (the profile's reopen-this-window note) nor the document's (the Recruitment window).
    $root.each((index, rootElement) => rootElement.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm]') || confirmedForms.has(form)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        askToConfirm(form, event.submitter ?? null);
    }, true));

    // A document preview may not be made taller than the window allows (it changes with the window).
    $(window).on('resize', () => {
        $root.find('[data-resizable-viewer].ui-resizable').resizable('option', 'maxHeight', viewerMaxHeight());
    });

    // Modal forms are filled in by scripts just before they open.
    $root.on('show.bs.modal', '.modal', function () {
        refreshAdminWidgets(this);
    });

    // Coming back to the page from the browser's history shows it as it was left, with the sent form's buttons off.
    $(window).on('pageshow', (event) => {
        if (event.originalEvent.persisted) {
            $('[data-confirm-sent]').prop('disabled', false).removeAttr('data-confirm-sent');
        }
    });
});
