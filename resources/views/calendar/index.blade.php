@extends('layouts.admin')

@section('title', 'Calendar · '.config('app.name'))

@php
    use App\Enums\InterviewMode;
    use App\Enums\InterviewStatus;

    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $prev = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
@endphp

{{--
    The HR calendar, from the database: demo interviews (blue), HR events (teal), and approved employee leave (purple). Drag an
    interview or event that has not happened to another day (never before today): a form then asks for the reason and the time. Click
    an interview to reject, mark completed, move to the next stage, change the interviewer, reschedule, or cancel it; the applicant
    information is in a collapsible section. A dashed red outline marks an interviewer who is double-booked. Handled by
    resources/js/calendar.js (jQuery UI drag and drop).
--}}
@section('content')
    <div class="d-grid gap-4" data-page="calendar" data-today="{{ now()->toDateString() }}">
        <div>
            <h1 class="page-title">Calendar</h1>
            <p class="text-body-secondary mt-1 mb-0">Demo interviews, HR events, and employee leave. Drag an upcoming interview or event to move it.</p>
        </div>

        @if (session('conflicts'))
            <div class="alert alert-warning mb-0" role="alert">
                <strong>That overlaps something, so nothing was changed.</strong>
                <ul class="mb-1">@foreach (session('conflicts')['messages'] as $message) <li>{{ $message }}</li> @endforeach</ul>
                @if (! empty(session('conflicts')['free'])) Free at that time: {{ collect(session('conflicts')['free'])->pluck('name')->implode(', ') }}. @endif
                Try again with another time or interviewer.
            </div>
        @endif

        <div class="card employees-filters">
            <div class="row g-3 align-items-start">
                <fieldset class="col-lg-5">
                    <legend class="form-label small fw-semibold mb-2">Event Type</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['demo-interview' => 'Applicant Demo Interview', 'other' => 'HR Event', 'leave' => 'Employee Leave'] as $value => $label)
                            <input type="checkbox" class="btn-check" id="calendar-type-{{ $value }}" value="{{ $value }}"
                                autocomplete="off" checked data-calendar-type-filter>
                            <label class="btn btn-sm btn-outline-primary" for="calendar-type-{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="col-lg-5">
                    <legend class="form-label small fw-semibold mb-2">Event Status</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['scheduled' => 'Scheduled', 'completed' => 'Completed', 'approved' => 'Approved', 'cancelled' => 'Cancelled'] as $value => $label)
                            <input type="checkbox" class="btn-check" id="calendar-status-{{ $value }}" value="{{ $value }}"
                                autocomplete="off" checked data-calendar-status-filter>
                            <label class="btn btn-sm btn-outline-primary" for="calendar-status-{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="col-lg-2 d-flex justify-content-lg-end align-self-lg-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-calendar-clear>Clear Filters</button>
                </div>
            </div>

            <form method="get" class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top" data-auto-submit>
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="show" value="{{ $show }}">
                <label class="visually-hidden" for="cal-who">Interviewer</label>
                <select class="form-select form-select-sm w-auto" id="cal-who" name="who"><option value="all">All interviews</option><option value="mine" @selected($who === 'mine')>My interviews</option></select>
                <label class="visually-hidden" for="cal-college">College</label>
                <select class="form-select form-select-sm w-auto" id="cal-college" name="college"><option value="">All colleges</option>@foreach ($colleges as $id => $name)<option value="{{ $id }}" @selected((string) $collegeId === (string) $id)>{{ $name }}</option>@endforeach</select>
            </form>

            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 pt-3 border-top small" aria-label="Legend" role="list">
                <span class="d-inline-flex align-items-center gap-1 type-demo-interview" role="listitem"><span class="calendar-swatch is-confirmed"></span> Applicant demo interview</span>
                <span class="d-inline-flex align-items-center gap-1 type-other" role="listitem"><span class="calendar-swatch is-confirmed"></span> HR event</span>
                <span class="d-inline-flex align-items-center gap-1 type-leave" role="listitem"><span class="calendar-swatch is-confirmed"></span> Employee leave</span>
            </div>
        </div>

        <div class="row g-4 align-items-start">
        <div class="col-xl-8">
        <section class="card employee-form-card" aria-labelledby="calendar-month-title">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h2 class="fs-5 mb-0" id="calendar-month-title" aria-live="polite">{{ $month->format('F Y') }}</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" data-calendar-add-event>
                        <x-icon name="plus" />
                        Add Event
                    </button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $prev, 'who' => $who, 'show' => $show, 'college' => $collegeId]) }}" aria-label="Previous month"><x-icon name="chevron-left" /></a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['who' => $who]) }}">Today</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $next, 'who' => $who, 'show' => $show, 'college' => $collegeId]) }}" aria-label="Next month"><x-icon name="chevron-right" /></a>
                </div>
            </div>

            <div class="calendar-weekdays" aria-hidden="true">@foreach ($weekdays as $weekday) <span>{{ $weekday }}</span> @endforeach</div>
            <div class="calendar-grid" role="grid" aria-label="{{ $month->format('F Y') }}">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        <div class="calendar-day{{ $day['inMonth'] ? '' : ' is-outside' }}{{ $day['isToday'] ? ' is-today' : '' }}" role="gridcell"
                            data-calendar-day="{{ $day['date']->toDateString() }}" @if ($day['canDrop']) data-droppable @endif>
                            <span class="calendar-day-number">{{ $day['date']->day }}</span>
                            <div class="calendar-day-events">
                                @foreach ($day['interviews'] as $interview)
                                    @php
                                        $application = $interview->application;
                                        $profile = $application->applicant->applicantProfile;
                                        $isScheduled = $interview->status === InterviewStatus::Scheduled;
                                        $canMove = $isScheduled && $interview->starts_at->isFuture();
                                    @endphp
                                    <button type="button"
                                        class="calendar-event type-demo-interview {{ $isScheduled ? 'is-tentative' : ($interview->status === InterviewStatus::Completed ? 'is-confirmed' : 'text-decoration-line-through') }}{{ in_array($interview->id, $conflictIds, true) ? ' border-danger' : '' }}"
                                        data-calendar-chip="interview" data-calendar-type="demo-interview" data-calendar-status="{{ $interview->status->value }}" @if ($canMove) data-movable @endif
                                        data-title="{{ $profile->full_name }}" data-subtitle="{{ $application->jobTitle->title }} · {{ $application->jobTitle->college?->code }}"
                                        data-status="{{ $interview->status->label() }}" data-scheduled="{{ $isScheduled ? '1' : '0' }}" data-started="{{ $interview->starts_at->isPast() ? '1' : '0' }}"
                                        data-date="{{ $interview->starts_at->toDateString() }}" data-start="{{ $interview->starts_at->format('H:i') }}" data-end="{{ $interview->ends_at->format('H:i') }}"
                                        data-when="{{ $interview->starts_at->format('l, M j, Y g:i A') }} to {{ $interview->ends_at->format('g:i A') }}"
                                        data-place="{{ $interview->mode === InterviewMode::Online ? 'Online: '.$interview->meeting_link : $interview->venue }}"
                                        data-interviewer="{{ $interview->interviewer->name }}" data-interviewer-id="{{ $interview->interviewer_id }}"
                                        data-conflict="{{ in_array($interview->id, $conflictIds, true) ? '1' : '0' }}"
                                        data-requested="{{ $interview->reschedule_requested_at ? 'Asked to reschedule: '.$interview->reschedule_reason.' (available: '.$interview->reschedule_available_days.')' : '' }}"
                                        data-email="{{ $application->applicant->email }}" data-phone="{{ $profile->phone }}"
                                        data-education="{{ collect($application->education ?? [])->map(fn ($row) => trim(($row['school'] ?? '').' '.($row['course'] ?? '')))->filter()->implode('; ') }}"
                                        data-application-url="{{ route('recruitment.show', $application) }}"
                                        data-reschedule-url="{{ route('recruitment.interviews.reschedule', $interview) }}"
                                        data-interviewer-url="{{ route('recruitment.interviews.interviewer', $interview) }}"
                                        data-cancel-url="{{ route('recruitment.interviews.cancel', $interview) }}"
                                        data-complete-url="{{ route('recruitment.interviews.complete', $interview) }}"
                                        data-reject-url="{{ route('recruitment.reject', $application) }}">
                                        <span class="calendar-event-label">{{ $interview->starts_at->format('g:i A') }} {{ $profile->last_name }}</span>
                                    </button>
                                @endforeach

                                @foreach ($day['events'] as $event)
                                    @php($isScheduled = $event->status === 'scheduled')
                                    <button type="button" class="calendar-event type-other {{ $isScheduled ? 'is-confirmed' : 'text-decoration-line-through' }}"
                                        data-calendar-chip="event" data-calendar-type="other" data-calendar-status="{{ $event->status }}" @if ($isScheduled && $event->starts_at->isFuture()) data-movable @endif
                                        data-title="{{ $event->title }}" data-subtitle="{{ $event->typeLabel() }}" data-status="{{ ucfirst($event->status) }}" data-scheduled="{{ $isScheduled ? '1' : '0' }}"
                                        data-date="{{ $event->starts_at->toDateString() }}" data-start="{{ $event->starts_at->format('H:i') }}" data-end="{{ $event->ends_at?->format('H:i') }}"
                                        data-when="{{ $event->starts_at->format('l, M j, Y g:i A') }}{{ $event->ends_at ? ' to '.$event->ends_at->format('g:i A') : '' }}"
                                        data-place="{{ $event->venue }}" data-notes="{{ $event->notes }}" data-type="{{ $event->type }}" data-everyone="{{ $event->for_everyone ? '1' : '0' }}"
                                        data-update-url="{{ route('calendar.events.update', $event) }}"
                                        data-reschedule-url="{{ route('calendar.events.move', $event) }}"
                                        data-cancel-url="{{ route('calendar.events.cancel', $event) }}">
                                        <span class="calendar-event-label">{{ $event->starts_at->format('g:i A') }} {{ $event->title }}</span>
                                    </button>
                                @endforeach

                                @foreach ($day['leave'] as $leave)
                                    <span class="calendar-event type-leave is-confirmed" data-calendar-type="leave" data-calendar-status="approved" title="{{ $leave->employee->full_name }}: {{ $leave->leave_type->label() }}">
                                        <span class="calendar-event-label">{{ $leave->employee->last_name }} (leave)</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
        </div>

        <x-calendar-day-panel />
        </div>
    </div>

    {{-- An interview or event: its details, and what HR can do with it. --}}
    <div class="modal fade" id="calendar-detail-modal" tabindex="-1" aria-labelledby="calendar-detail-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="calendar-detail-title" data-detail="title"></h2>
                        <small class="text-body-secondary" data-detail="subtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-grid gap-3">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Status</dt><dd class="col-sm-9" data-detail="status"></dd>
                        <dt class="col-sm-3">When</dt><dd class="col-sm-9" data-detail="when"></dd>
                        <dt class="col-sm-3">Where</dt><dd class="col-sm-9" data-detail="place"></dd>
                        <dt class="col-sm-3" data-interview-only>Interviewer</dt><dd class="col-sm-9" data-interview-only data-detail="interviewer"></dd>
                    </dl>
                    <div class="alert alert-warning mb-0 d-none" data-detail="requested"></div>
                    <div class="alert alert-danger mb-0 d-none" data-detail="conflict">This interviewer has another interview that overlaps this one.</div>
                    <p class="mb-0 d-none" data-event-only data-detail="notes"></p>

                    <details data-interview-only>
                        <summary class="fw-medium">Applicant information</summary>
                        <dl class="row mt-2 mb-0 small">
                            <dt class="col-sm-3">Email</dt><dd class="col-sm-9" data-detail="email"></dd>
                            <dt class="col-sm-3">Mobile</dt><dd class="col-sm-9" data-detail="phone"></dd>
                            <dt class="col-sm-3">Education</dt><dd class="col-sm-9" data-detail="education"></dd>
                        </dl>
                        <a class="btn btn-sm btn-outline-secondary mt-2" href="#" data-detail-link="application">Open the full application and documents</a>
                    </details>

                    <div data-scheduled-only class="d-grid gap-3">
                        <details>
                            <summary class="fw-medium">Reschedule</summary>
                            <form method="post" class="row g-2 mt-1" data-form="reschedule" novalidate>
                                @csrf
                                @method('PATCH')
                                <div class="col-md-4"><label class="form-label" for="rs-date">Date</label><input class="form-control" type="date" id="rs-date" name="date" min="{{ now()->toDateString() }}" required></div>
                                <div class="col-md-4"><label class="form-label" for="rs-start">Start</label><input class="form-control" type="time" id="rs-start" name="start_time" min="08:00" max="17:00" required></div>
                                <div class="col-md-4"><label class="form-label" for="rs-end">End</label><input class="form-control" type="time" id="rs-end" name="end_time" min="08:00" max="17:00"></div>
                                <div class="col-12">
                                    <label class="form-label is-required" for="rs-reason">Reason</label>
                                    <input class="form-control" type="text" id="rs-reason" name="reason" required>
                                    <div class="form-text d-none" data-reschedule-hint>No reason is needed to answer the applicant’s request.</div>
                                </div>
                                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Save New Time</button></div>
                            </form>
                        </details>

                        <details data-interview-only>
                            <summary class="fw-medium">Change interviewer</summary>
                            <form method="post" class="row g-2 mt-1" data-form="interviewer" novalidate>
                                @csrf
                                @method('PATCH')
                                <div class="col-md-8">
                                    <label class="form-label" for="ci-interviewer">New interviewer</label>
                                    <select class="form-select" id="ci-interviewer" name="interviewer_id">
                                        @foreach ($interviewers as $person) <option value="{{ $person->id }}">{{ $person->name }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Change Interviewer</button></div>
                            </form>
                        </details>

                        <div data-interview-only class="d-flex gap-2 flex-wrap">
                            <form method="post" data-form="complete" class="d-none" data-needs-started>@csrf<button type="submit" class="btn btn-primary">Mark Completed (next stage)</button></form>
                            <a class="btn btn-primary d-none" href="#" data-detail-link="application" data-needs-completed>Next stage: issue an offer</a>
                        </div>

                        <details>
                            <summary class="fw-medium text-danger">Cancel</summary>
                            <form method="post" class="d-flex gap-2 mt-1" data-form="cancel">
                                @csrf
                                <input class="form-control" type="text" name="reason" placeholder="Reason" required aria-label="Reason for cancelling">
                                <button type="submit" class="btn btn-outline-danger text-nowrap">Cancel It</button>
                            </form>
                        </details>

                        <details data-interview-only>
                            <summary class="fw-medium text-danger">Reject the applicant</summary>
                            <form method="post" class="d-flex gap-2 mt-1" data-form="reject">
                                @csrf
                                <input class="form-control" type="text" name="reason" placeholder="Reason (the applicant is told)" required aria-label="Reason for rejecting">
                                <button type="submit" class="btn btn-outline-danger text-nowrap">Reject</button>
                            </form>
                        </details>

                        <details data-event-only>
                            <summary class="fw-medium">Edit event</summary>
                            @include('calendar._event-form', ['formId' => 'edit', 'method' => 'PUT'])
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- After a drag: the new day is set, so HR gives the reason and may adjust the time. --}}
    <div class="modal fade" id="calendar-move-modal" tabindex="-1" aria-labelledby="calendar-move-title" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content" data-form="move" novalidate>
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="calendar-move-title">Move to <span data-move="label"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="date" data-move="date">
                    <div class="col-12"><strong data-move="title"></strong></div>
                    <div class="col-6"><label class="form-label" for="mv-start">Start</label><input class="form-control" type="time" id="mv-start" name="start_time" min="08:00" max="17:00" required></div>
                    <div class="col-6"><label class="form-label" for="mv-end">End</label><input class="form-control" type="time" id="mv-end" name="end_time" min="08:00" max="17:00"></div>
                    <div class="col-12">
                        <label class="form-label is-required" for="mv-reason" data-move="reason-label">Reason for moving</label>
                        <input class="form-control" type="text" id="mv-reason" name="reason" required>
                        <div class="form-text d-none" data-move="reason-hint">No reason is needed to answer the applicant’s request.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Do not move</button>
                    <button type="submit" class="btn btn-primary">Move</button>
                </div>
            </form>
        </div>
    </div>

    {{-- A new event. --}}
    <div class="modal fade" id="calendar-event-modal" tabindex="-1" aria-labelledby="calendar-event-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="calendar-event-title">Add Event</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('calendar._event-form', ['formId' => 'new', 'method' => 'POST', 'action' => route('calendar.events.store')])
                </div>
            </div>
        </div>
    </div>
@endsection
