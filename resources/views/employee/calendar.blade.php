@extends('layouts.employee')

@section('title', 'Calendar · '.config('app.name'))

@php
    // Leave is drawn dashed while Pending and solid once Approved. Rejected and cancelled requests are listed only on My Leave.
    $eventStatuses = ['Pending', 'Approved'];
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $prev = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
@endphp

{{--
    The signed-in employee's own leave and the events HR shared with everyone, on the same month grid the HR calendar uses.
    Their requests (and cancelling a Pending one) are on My Leave. The status filter hides chips in the browser
    (resources/js/page-behaviors.js).
--}}
@section('content')
    <div class="d-grid gap-4" data-page="calendar" data-calendar-scope="own">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Calendar</h1>
                <p class="text-body-secondary mt-1 mb-0">Your pending and approved leave, and events HR shared with everyone.</p>
            </div>
        </div>

        <div class="card employees-filters">
            <div class="row g-3 align-items-start">
                <fieldset class="col-lg-9">
                    <legend class="form-label small fw-semibold mb-2">Leave Status</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($eventStatuses as $status)
                            <input type="checkbox" class="btn-check" id="calendar-status-{{ strtolower($status) }}" value="{{ strtolower($status) }}"
                                autocomplete="off" checked data-calendar-status-filter>
                            <label class="btn btn-sm btn-outline-primary" for="calendar-status-{{ strtolower($status) }}">{{ $status }}</label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="col-lg-3 d-flex justify-content-lg-end align-self-lg-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-calendar-clear>Clear Filters</button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 pt-3 border-top small" aria-label="Legend" role="list">
                <span class="d-inline-flex align-items-center gap-1 type-leave" role="listitem"><span class="calendar-swatch is-tentative"></span> Pending leave</span>
                <span class="d-inline-flex align-items-center gap-1 type-leave" role="listitem"><span class="calendar-swatch is-confirmed"></span> Approved leave</span>
                <span class="d-inline-flex align-items-center gap-1 type-other" role="listitem"><span class="calendar-swatch is-confirmed"></span> HR event</span>
            </div>
        </div>

        <div class="row g-4 align-items-start">
        <div class="col-xl-8">
        <section class="card employee-form-card" aria-labelledby="calendar-month-title">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h2 class="fs-5 mb-0" id="calendar-month-title" aria-live="polite">{{ $month->format('F Y') }}</h2>
                <div class="d-flex flex-wrap gap-2">
                    <form method="get" action="{{ route('employee.calendar') }}" data-auto-submit>
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <x-calendar-jump :month="$month" />
                    </form>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('employee.calendar', ['month' => $prev]) }}" aria-label="Previous month"><x-icon name="chevron-left" /></a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('employee.calendar') }}">Today</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('employee.calendar', ['month' => $next]) }}" aria-label="Next month"><x-icon name="chevron-right" /></a>
                </div>
            </div>

            <div class="calendar-weekdays" aria-hidden="true">
                @foreach ($weekdays as $weekday)
                    <span>{{ $weekday }}</span>
                @endforeach
            </div>
            <div class="calendar-grid" role="grid" aria-label="{{ $month->format('F Y') }}">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        <div class="calendar-day{{ $day['inMonth'] ? '' : ' is-outside' }}{{ $day['isToday'] ? ' is-today' : '' }}" role="gridcell"
                            data-calendar-day="{{ $day['date']->toDateString() }}">
                            <span class="calendar-day-number">{{ $day['date']->day }}</span>
                            <div class="calendar-day-events">
                                @foreach ($day['events'] as $event)
                                    <span class="calendar-event type-other is-confirmed" title="{{ $event->title }} · {{ $event->typeLabel() }}{{ $event->venue ? ' · '.$event->venue : '' }}"
                                        data-title="{{ $event->title }} ({{ $event->typeLabel() }})" data-status-label="Scheduled" data-place="{{ $event->venue }}"
                                        data-when="{{ $event->starts_at->format('l, M j, Y g:i A') }}{{ $event->ends_at ? ' to '.$event->ends_at->format('g:i A') : '' }}">
                                        <span class="calendar-event-label">{{ $event->starts_at->format('g:i A') }} {{ $event->title }}</span>
                                    </span>
                                @endforeach

                                @foreach ($day['leave'] as $leave)
                                    <span class="calendar-event type-leave {{ $leave->status === \App\Enums\LeaveStatus::Approved ? 'is-confirmed' : 'is-tentative' }}"
                                        data-calendar-status="{{ $leave->status->value }}" title="{{ $leave->leave_type->label() }} ({{ $leave->status->label() }})"
                                        data-title="{{ $leave->leave_type->label() }}" data-status-label="{{ $leave->status->label() }}"
                                        data-when="{{ $leave->start_date->format('M j, Y') }}{{ $leave->end_date->isSameDay($leave->start_date) ? '' : ' to '.$leave->end_date->format('M j, Y') }}">
                                        <span class="calendar-event-label">{{ $leave->leave_type->label() }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
        </div>

        <x-calendar-day-panel details />
        </div>
    </div>
@endsection
