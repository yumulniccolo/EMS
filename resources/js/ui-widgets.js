import $ from './jquery.js';

// The jQuery UI widgets used across the pages: a Datepicker on every date field, Autocomplete on the employee search
// boxes, and Sortable on the application's education and work history rows. Each one is added to markup that already
// works without it, so the forms still submit the same values (dates stay yyyy-mm-dd).

function initDatepickers() {
    $('input[type="date"]').each(function () {
        const $input = $(this);
        const min = $input.attr('min');
        const max = $input.attr('max');
        const isBirthdate = /birth/i.test($input.attr('name') ?? '');

        // jQuery UI draws its own calendar, so the browser's date control is swapped for a text field with the same value.
        $input.attr({ type: 'text', autocomplete: 'off', placeholder: 'YYYY-MM-DD', 'data-datepicker': '' }).removeAttr('min').removeAttr('max');

        $input.datepicker({
            dateFormat: 'yy-mm-dd',
            firstDay: 0,
            changeMonth: true,
            changeYear: true,
            yearRange: isBirthdate ? 'c-80:c+0' : 'c-10:c+10',
            minDate: min || null,
            maxDate: isBirthdate && !max ? 0 : (max || null),
            showOtherMonths: true,
            onSelect: () => $input.trigger('input').trigger('change'),
        });
    });
}

// Two date fields that make a range (From/To, First day/Last day, the report period) know about each other: the second
// cannot be picked before the first, both calendars show two months, and the days in between are shaded.
const rangePairs = [
    ['[name="from"]', '[name="to"]'],
    ['[name="start_date"]', '[name="end_date"]'],
    ['[data-period-from]', '[data-period-to]'],
];

function initDateRanges() {
    rangePairs.forEach(([fromSelector, toSelector]) => {
        $(fromSelector).filter('[data-datepicker]').each(function () {
            const $from = $(this);
            const $to = $from.closest('form, section, .card, body').find(toSelector).filter('[data-datepicker]').first();

            if (!$to.length) {
                return;
            }

            const baseMin = $from.datepicker('option', 'minDate');
            const sync = () => {
                $to.datepicker('option', 'minDate', $from.val() || baseMin);
                $from.datepicker('option', 'maxDate', $to.val() || null);
            };
            const shade = (date) => {
                const day = $.datepicker.formatDate('yy-mm-dd', date);
                const inRange = $from.val() && $to.val() && day >= $from.val() && day <= $to.val();

                return [true, inRange ? 'ui-range-day' : '', ''];
            };

            [$from, $to].forEach(($picker) => {
                $picker.datepicker('option', { numberOfMonths: 2, beforeShowDay: shade });
                $picker.on('change', sync);
            });
            sync();
        });
    });
}

// A range in one field (components/date-range.blade.php): the first click picks the start and keeps the calendar open, the
// second picks the end and closes it. The hidden from/to fields are filled, and the filter form is sent once, only then.
function initRangeFilters() {
    $('input[data-date-range]').each(function () {
        const $input = $(this);
        const $from = $input.siblings('[data-range-from]');
        const $to = $input.siblings('[data-range-to]');
        let isChoosingEnd = false;
        let isReopening = false;

        const showRange = () => {
            const from = $from.val();
            const to = $to.val();

            $input.val(from ? (to && to !== from ? `${from} to ${to}` : from) : '');
        };
        const finish = () => {
            isChoosingEnd = false;
            showRange();
            $input.trigger('change');
        };

        $input.datepicker({
            dateFormat: 'yy-mm-dd',
            numberOfMonths: 2,
            changeMonth: true,
            changeYear: true,
            showOtherMonths: true,
            defaultDate: $from.val() || null,
            beforeShow: () => {
                if (!isReopening) {
                    isChoosingEnd = false;
                }

                isReopening = false;
            },
            beforeShowDay: (date) => {
                const day = $.datepicker.formatDate('yy-mm-dd', date);
                const from = $from.val();
                const to = $to.val() || from;

                return [true, from && day >= from && day <= to ? 'ui-range-day' : '', ''];
            },
            onSelect: (text) => {
                if (!isChoosingEnd) {
                    $from.val(text);
                    $to.val('');
                    $input.val(text);
                    isChoosingEnd = true;
                    isReopening = true;
                    window.setTimeout(() => $input.datepicker('show'), 0);

                    return;
                }

                const [start, end] = text < $from.val() ? [text, $from.val()] : [$from.val(), text];

                $from.val(start);
                $to.val(end);
                finish();
            },
            // Closing the calendar after only the first click means a single day.
            onClose: () => {
                if (isChoosingEnd && !isReopening) {
                    $to.val($from.val());
                    finish();
                }
            },
        });

        $input.on('keydown', (event) => {
            if (event.key === 'Backspace' || event.key === 'Delete') {
                event.preventDefault();
                $from.val('');
                $to.val('');
                finish();
            }
        });
    });
}

function initCalendarJump() {
    $('[data-calendar-jump]').each(function () {
        const $jump = $(this);

        // The form's own change handler sends it after this runs, so the hidden month field is up to date by then.
        $jump.find('select').on('change', () => {
            const month = String($jump.find('[data-jump="month"]').val()).padStart(2, '0');

            $jump.closest('form').find('input[name="month"]').val(`${$jump.find('[data-jump="year"]').val()}-${month}`);
        });
    });
}

function initEmployeeSuggestions() {
    $('input[data-suggest-url]').each(function () {
        const $input = $(this);
        const url = $input.attr('data-suggest-url');

        $input.autocomplete({
            minLength: 2,
            delay: 250,
            source: (request, response) => {
                $.getJSON(url, { term: request.term }).done(response).fail(() => response([]));
            },
            select: (event, ui) => {
                $input.val(ui.item.value);
                $input.closest('form').each((index, form) => form.requestSubmit());

                return false;
            },
        });
    });
}

// The rows keep their form field names by position (education[0][school], ...), so after a drag the names are
// renumbered to match the new order. The first education row is the one that must be filled in.
function initSortableRows() {
    $('[data-sortable-rows]').each(function () {
        const $list = $(this);
        const requiredName = $list.attr('data-sortable-required');

        $list.sortable({
            items: '> .sortable-row',
            handle: '.sort-handle',
            axis: 'y',
            tolerance: 'pointer',
            update: () => {
                $list.children('.sortable-row').each(function (index) {
                    $(this).find('[name]').each(function () {
                        $(this).attr('name', $(this).attr('name').replace(/\[\d+\]/, `[${index}]`));
                    });
                });

                if (requiredName) {
                    const suffix = requiredName.replace(/^[^\]]+\[\d+\]/, '');

                    $list.find(`[name$="${suffix}"]`).prop('required', false).removeClass('is-required');
                    $list.find(`[name="${requiredName}"]`).prop('required', true);
                }
            },
        });
    });
}

$(function () {
    initDatepickers();
    initDateRanges();
    initRangeFilters();
    initCalendarJump();
    initEmployeeSuggestions();
    initSortableRows();
});
