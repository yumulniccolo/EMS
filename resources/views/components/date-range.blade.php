@props(['id', 'from' => null, 'to' => null, 'fromName' => 'from', 'toName' => 'to', 'placeholder' => 'Select dates'])

{{--
    One field for a date range. jQuery UI's Datepicker (resources/js/ui-widgets.js) opens a two-month calendar: the first click is
    the start, the second the end, and then a filter form sends itself once. The two hidden fields carry the values the server
    reads, as yyyy-mm-dd, so the controller sees the same from and to it always did. Backspace or Delete clears the range.
--}}
@php
    $from = $from ?: null;
    $to = $to ?: null;
    $shown = $from ? ($to && $to !== $from ? "{$from} to {$to}" : $from) : '';
@endphp
<input type="text" class="form-control date-range-input" id="{{ $id }}" value="{{ $shown }}" placeholder="{{ $placeholder }}"
    autocomplete="off" readonly data-date-range {{ $attributes }}>
<input type="hidden" name="{{ $fromName }}" value="{{ $from }}" data-range-from>
<input type="hidden" name="{{ $toName }}" value="{{ $to }}" data-range-to>
