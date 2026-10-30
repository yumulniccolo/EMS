import $ from './jquery.js';

// A filter form marked data-auto-submit sends itself when a choice changes, and shortly after typing stops in its
// search box. The server does the filtering, so the page, its pagination, and its address always agree.
const typingPause = 400;

$(function () {
    $('form[data-auto-submit]').each(function () {
        const form = this;
        const $search = $(form).find('input[type="search"]');
        let timer = null;

        $(form).on('change', 'select, input[type="checkbox"], input[type="date"], input[data-datepicker], input[data-date-range], input[type="month"]', () => form.requestSubmit());

        // A search box with name suggestions (data-suggest-url) is not reloaded while typing, so its list can stay open;
        // it sends the form when a suggestion is picked or Enter is pressed.
        $search.not('[data-suggest-url]').on('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => form.requestSubmit(), typingPause);
        });

        // After the page reloads with a search term, keep typing where the cursor was.
        if ($search.length && $search.val()) {
            const search = $search[0];
            const end = search.value.length;

            search.focus();
            search.setSelectionRange(end, end);
        }
    });
});
