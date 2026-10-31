@props(['month'])

{{-- Month and year pickers for a calendar. They sit in a form that carries a hidden "month" field (Y-m); ui-widgets.js writes the choice into it and the form sends itself. --}}
<div class="d-flex gap-2" data-calendar-jump>
    <label class="visually-hidden" for="calendar-jump-month">Month</label>
    <select class="form-select form-select-sm w-auto" id="calendar-jump-month" data-jump="month">
        @foreach (range(1, 12) as $number)
            <option value="{{ $number }}" @selected($month->month === $number)>{{ \Carbon\CarbonImmutable::create(2000, $number, 1)->format('F') }}</option>
        @endforeach
    </select>
    <label class="visually-hidden" for="calendar-jump-year">Year</label>
    <select class="form-select form-select-sm w-auto" id="calendar-jump-year" data-jump="year">
        @foreach (range(min($month->year, now()->year) - 5, max($month->year, now()->year) + 5) as $year)
            <option value="{{ $year }}" @selected($month->year === $year)>{{ $year }}</option>
        @endforeach
    </select>
</div>
