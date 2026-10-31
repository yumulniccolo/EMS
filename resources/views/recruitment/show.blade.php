@extends('layouts.admin')

@section('title', $profile->full_name.' · '.config('app.name'))

@php
    use App\Enums\ApplicationStatus;
    use App\Enums\InterviewMode;
    use App\Enums\InterviewStatus;
    use App\Enums\OfferStatus;

    $status = $application->status;
    $canSchedule = $status === ApplicationStatus::Applied && ! $interview;
    $interviewDone = $lastInterview?->status === InterviewStatus::Completed;
    $readyForOffer = $status === ApplicationStatus::DemoInterview && ! $interview && $interviewDone;
    $acceptedOffer = $application->acceptedOffer();
@endphp

{{--
    One application. The resume and portfolio open immediately; the applicant’s information, history, and interview details are
    collapsible so the page stays short. The panel under the documents is the next step for the stage the application is in:
    Applied (review, then reject or schedule the demo interview), Demo Interview (reschedule, change interviewer, complete, then
    reject or issue an offer), Offered (waiting for the answer; complete the hiring once accepted). A rejected application has no
    next step; instead HR can reopen it, which puts it back in Applied so a new demo interview can be scheduled.
--}}
@section('content')
    <div class="d-grid gap-4" data-application-content>
        <div>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('recruitment.index') }}">
                <x-icon name="back" /> Back to Recruitment
            </a>
        </div>

        <section class="card employee-form-card">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="page-title">{{ $profile->full_name }}</h1>
                    <span class="text-body-secondary">
                        {{ $application->jobTitle->title }} · {{ $application->jobTitle->college?->name ?? $application->jobTitle->office?->name }}
                        · Applied {{ $application->applied_on->format('M j, Y') }}
                    </span>
                </div>
                <span class="status-pill {{ $status->cssClass() }}">{{ $status->label() }}</span>
            </div>
            @if ($application->status_reason && $status->isFinal())
                <p class="mb-0 mt-3"><strong>Reason:</strong> {{ $application->status_reason }}</p>
            @endif
            @if ($status === ApplicationStatus::Hired && $application->employee)
                <p class="mb-0 mt-3">Hired as employee <a href="{{ route('employees.show', $application->employee) }}">{{ $application->employee->employee_no }}</a>.</p>
            @endif
        </section>

        {{-- Documents: visible at once, as HR reviews them before deciding. --}}
        <section class="card employee-form-card" aria-labelledby="documents-title">
            <h2 class="fs-5" id="documents-title">Documents</h2>
            {{-- Each [data-accordion] group opens one panel at a time (jQuery UI Accordion; resources/js/admin-widgets.js). --}}
            <div data-accordion>
            @forelse ($application->documents as $document)
                <div class="mb-2">
                    <div class="fw-medium" data-accordion-header>
                        {{ ucfirst($document->kind) }}: {{ $document->original_name }}
                        <small class="text-body-secondary">({{ $document->humanSize() }})</small>
                    </div>
                    <div><div class="mt-2 d-grid gap-2">
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $document->uuid) }}" target="_blank" rel="noopener">Open in new tab</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show', [$document->uuid, 'download' => 1]) }}">Download</a>
                        </div>
                        @if ($document->mime_type === 'application/pdf')
                            {{-- Its bottom edge can be dragged to make the preview taller or shorter (jQuery UI Resizable). --}}
                            <div class="document-viewer border rounded" data-resizable-viewer>
                                <iframe src="{{ route('documents.show', $document->uuid) }}" title="{{ ucfirst($document->kind) }} of {{ $profile->full_name }}"></iframe>
                            </div>
                        @elseif ($document->isViewable())
                            <img class="img-fluid border rounded" src="{{ route('documents.show', $document->uuid) }}" alt="{{ ucfirst($document->kind) }} of {{ $profile->full_name }}">
                        @else
                            <p class="small text-body-secondary mb-0">This file type cannot be previewed here. Download it to read it.</p>
                        @endif
                    </div></div>
                </div>
            @empty
                <p class="text-body-secondary mb-0">No documents were attached.</p>
            @endforelse
            </div>
            @if ($application->portfolio_url)
                <p class="mb-0 mt-2">Portfolio link: <a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener noreferrer">{{ $application->portfolio_url }}</a></p>
            @endif
        </section>

        @unless ($status->isFinal())
        {{-- The next step --}}
        <section class="card employee-form-card" aria-labelledby="next-title">
            <h2 class="fs-5" id="next-title">Next step</h2>
            <div data-accordion>

            @if ($interview?->reschedule_requested_at)
                <div class="alert alert-warning">
                    <strong>The applicant asked to reschedule</strong> ({{ $interview->reschedule_requested_at->diffForHumans() }}).<br>
                    Reason: {{ $interview->reschedule_reason }}<br>
                    Available: {{ $interview->reschedule_available_days }}
                </div>
            @endif

            @if ($canSchedule)
                <h3 class="fs-6">Schedule the demo interview</h3>
                <form method="post" action="{{ route('recruitment.interviews.store', $application) }}" class="row g-3 mb-3" novalidate>
                    @csrf
                    <div class="col-md-3"><x-field name="date" type="date" label="Date" :min="now()->toDateString()" required /></div>
                    <div class="col-md-2"><x-field name="start_time" type="time" label="Start" min="08:00" max="17:00" hint="8:00 AM to 5:00 PM" required /></div>
                    <div class="col-md-2"><x-field name="end_time" type="time" label="End" min="08:00" max="17:00" required /></div>
                    <div class="col-md-5">
                        <x-select-field name="interviewer_id" label="Interviewer" :options="$interviewers->pluck('name', 'id')->all()" :value="auth()->id()" required />
                    </div>
                    <div class="col-md-3" data-mode-select><x-select-field name="mode" label="Face to face or online" :options="['face_to_face' => 'Face to face', 'online' => 'Online']" value="face_to_face" required /></div>
                    <div class="col-md-4" data-mode-field="face_to_face"><x-field name="venue" label="Venue (face to face)" placeholder="e.g. HR Office, Admin Building" /></div>
                    <div class="col-md-5" data-mode-field="online"><x-field name="meeting_link" type="url" label="Meeting link (online)" placeholder="https://meet.google.com/..." /></div>
                    <div class="col-12"><x-field name="notes" label="Notes (optional)" /></div>
                    <div class="col-12">@include('recruitment._conflicts', ['suffix' => 'schedule'])</div>
                    <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Schedule Demo Interview</button></div>
                </form>
            @endif

            @if ($interview)
                <h3 class="fs-6">Demo interview</h3>
                <dl class="row mb-2">
                    <dt class="col-sm-3">When</dt><dd class="col-sm-9">{{ $interview->starts_at->format('l, M j, Y') }}, {{ $interview->starts_at->format('g:i A') }} to {{ $interview->ends_at->format('g:i A') }}</dd>
                    <dt class="col-sm-3">Where</dt>
                    <dd class="col-sm-9">
                        @if ($interview->mode === InterviewMode::Online)
                            Online: <a href="{{ $interview->meeting_link }}" target="_blank" rel="noopener noreferrer">{{ $interview->meeting_link }}</a>
                        @else
                            {{ $interview->venue }}
                        @endif
                    </dd>
                    <dt class="col-sm-3">Interviewer</dt><dd class="col-sm-9">{{ $interview->interviewer->name }} <small class="text-body-secondary">(set by {{ $interview->scheduler->name }})</small></dd>
                </dl>

                <div class="mb-2">
                    <div class="fw-medium" data-accordion-header @if ((session('conflicts')['form'] ?? null) === 'reschedule') data-accordion-open @endif>Reschedule</div>
                    <div><form method="post" action="{{ route('recruitment.interviews.reschedule', $interview) }}" class="row g-3 mt-1" novalidate>
                        @csrf
                        @method('PATCH')
                        <div class="col-md-3"><x-field name="date" type="date" label="New date" :value="$interview->starts_at->toDateString()" :min="now()->toDateString()" required /></div>
                        <div class="col-md-2"><x-field name="start_time" type="time" label="Start" :value="$interview->starts_at->format('H:i')" min="08:00" max="17:00" required /></div>
                        <div class="col-md-2"><x-field name="end_time" type="time" label="End" :value="$interview->ends_at->format('H:i')" min="08:00" max="17:00" required /></div>
                        <div class="col-md-5">
                            @if ($interview->reschedule_requested_at)
                                <x-field name="reason" label="Reason (optional)" hint="This answers the applicant’s request, so no reason is needed. Add one only if you change the time for your own reasons." />
                            @else
                                <x-field name="reason" label="Reason (for example HR is busy or there is an event)" required />
                            @endif
                        </div>
                        <div class="col-12">@include('recruitment._conflicts', ['suffix' => 'reschedule'])</div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Move Interview</button></div>
                    </form></div>
                </div>

                <div class="mb-2">
                    <div class="fw-medium" data-accordion-header @if ((session('conflicts')['form'] ?? null) === 'interviewer') data-accordion-open @endif>Change interviewer</div>
                    <div><form method="post" action="{{ route('recruitment.interviews.interviewer', $interview) }}" class="row g-3 mt-1" novalidate>
                        @csrf
                        @method('PATCH')
                        <div class="col-md-6"><x-select-field name="interviewer_id" label="New interviewer" :options="$interviewers->pluck('name', 'id')->all()" :value="$interview->interviewer_id" required /></div>
                        <div class="col-12">@include('recruitment._conflicts', ['suffix' => 'interviewer'])</div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Change Interviewer</button></div>
                    </form></div>
                </div>

                <div class="mb-2">
                    <div class="fw-medium" data-accordion-header>Cancel the interview</div>
                    <div><form method="post" action="{{ route('recruitment.interviews.cancel', $interview) }}" class="d-flex gap-2 mt-1" data-confirm-title="Cancel interview"
                        data-confirm="Cancel this interview? The applicant is told the reason you gave." data-confirm-button="Cancel Interview" data-confirm-cancel="Keep Interview">
                        @csrf
                        <input type="text" class="form-control" name="reason" placeholder="Reason (the applicant is told)" required aria-label="Reason for cancelling the interview">
                        <button type="submit" class="btn btn-outline-danger text-nowrap">Cancel Interview</button>
                    </form></div>
                </div>

                @if ($interview->starts_at->isPast())
                    <form method="post" action="{{ route('recruitment.interviews.complete', $interview) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        <input type="text" class="form-control" name="notes" placeholder="Interview notes (optional)" aria-label="Interview notes">
                        <button type="submit" class="btn btn-primary text-nowrap">Mark as Completed</button>
                    </form>
                @else
                    <p class="small text-body-secondary mb-0">You can mark the interview as completed once it has started.</p>
                @endif
            @endif

            @if ($readyForOffer)
                <h3 class="fs-6 mt-2">The demo interview is done</h3>
                @if ($canOffer)
                    <p class="text-body-secondary">Send an offer, or reject the application below.</p>
                    @include('recruitment._offer-form')
                @else
                    <p class="text-body-secondary">An HR Admin issues the offer. You can reject the application below.</p>
                @endif
            @endif

            @if ($status === ApplicationStatus::Offered)
                @php($pendingOffer = $application->pendingOffer())
                @if ($acceptedOffer)
                    <div class="alert alert-success">
                        <strong>{{ $profile->full_name }} accepted the offer</strong> and signed as “{{ $acceptedOffer->signed_name }}” on {{ $acceptedOffer->responded_at->format('M j, Y g:i A') }}.
                    </div>
                    @if ($canOffer)
                        <form method="post" action="{{ route('recruitment.hire', $application) }}">
                            @csrf
                            <p class="text-body-secondary">Hiring creates the employee record and the employee login (the employee ID is the first password), gives the offered benefits, and tells the applicant.</p>
                            <button type="submit" class="btn btn-primary">Complete Hiring</button>
                        </form>
                    @else
                        <p class="text-body-secondary mb-0">An HR Admin completes the hiring.</p>
                    @endif
                @elseif ($pendingOffer)
                    <p class="mb-2">Waiting for {{ $profile->first_name }} to accept or decline the offer sent {{ $pendingOffer->created_at->diffForHumans() }}.</p>
                    @if ($canOffer)
                        <div>
                            <div class="fw-medium" data-accordion-header>Edit the offer</div>
                            <div><div class="mt-2">@include('recruitment._offer-form', ['editOffer' => $pendingOffer])</div></div>
                        </div>
                    @endif
                @endif
            @endif

            @if (! $status->isFinal() && ! $canSchedule && ! $interview && ! $readyForOffer && $status !== ApplicationStatus::Offered)
                <p class="text-body-secondary mb-0">Nothing to do right now.</p>
            @endif

            <hr>
            <div>
                <div class="fw-medium text-danger" data-accordion-header>Reject this application</div>
                <div><form method="post" action="{{ route('recruitment.reject', $application) }}" class="d-flex gap-2 mt-2" data-confirm-title="Reject application"
                    data-confirm="Reject this application? The applicant is told the reason you gave." data-confirm-button="Reject">
                    @csrf
                    <input type="text" class="form-control" name="reason" placeholder="Reason (the applicant is told)" required aria-label="Reason for rejecting">
                    <button type="submit" class="btn btn-outline-danger text-nowrap">Reject</button>
                </form></div>
            </div>
            </div>
        </section>
        @endunless

        @if ($status === ApplicationStatus::Rejected)
            <section class="card employee-form-card" aria-labelledby="reopen-title">
                <h2 class="fs-5" id="reopen-title">Reopen the application</h2>
                <p class="text-body-secondary">This application was rejected. Reopening puts it back in the Applied stage so you can review it again and schedule a new demo interview.</p>
                <form method="post" action="{{ route('recruitment.reopen', $application) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">Reopen application</button>
                </form>
            </section>
        @endif

        <div class="d-grid gap-4" data-accordion>
        <section class="card employee-form-card">
            <div>
                <div class="fs-5 fw-semibold" data-accordion-header>Applicant information</div>
                <div><dl class="row mt-3 mb-0">
                    <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $profile->full_name }}</dd>
                    <dt class="col-sm-3">Birthdate</dt><dd class="col-sm-9">{{ $profile->birthdate?->format('M j, Y') ?? '—' }}</dd>
                    <dt class="col-sm-3">Sex</dt><dd class="col-sm-9">{{ $profile->sex?->value ?? '—' }}</dd>
                    <dt class="col-sm-3">Civil status</dt><dd class="col-sm-9">{{ $profile->marital_status?->value ?? '—' }}</dd>
                    <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $application->applicant->email }}</dd>
                    <dt class="col-sm-3">Mobile</dt><dd class="col-sm-9">{{ $profile->phone ?? '—' }}</dd>
                    <dt class="col-sm-3">Address</dt><dd class="col-sm-9">{{ $profile->address ?? '—' }}</dd>
                </dl></div>
            </div>
        </section>

        <section class="card employee-form-card">
            <div>
                <div class="fs-5 fw-semibold" data-accordion-header>Education and work history</div>
                <div>
                <h3 class="fs-6 mt-3">Education</h3>
                <ul>@forelse ($application->education ?? [] as $row)
                    <li>{{ $row['level'] ?? '' }} {{ $row['school'] ?? '' }} {{ ! empty($row['course']) ? '· '.$row['course'] : '' }} {{ ! empty($row['year_graduated']) ? '· '.$row['year_graduated'] : '' }}</li>
                @empty <li class="text-body-secondary">None given.</li> @endforelse</ul>
                <h3 class="fs-6">Work history</h3>
                <ul class="mb-0">@forelse ($application->work_history ?? [] as $row)
                    <li>{{ $row['position'] ?? '' }} at {{ $row['employer'] ?? '' }} ({{ $row['from'] ?? '' }} to {{ $row['to'] ?? '' }})</li>
                @empty <li class="text-body-secondary">None given.</li> @endforelse</ul>
                @if ($application->cover_note)
                    <h3 class="fs-6 mt-3">Note to HR</h3>
                    <p class="mb-0">{{ $application->cover_note }}</p>
                @endif
                </div>
            </div>
        </section>

        @if ($application->offers->isNotEmpty())
            <section class="card employee-form-card">
                <div>
                    <div class="fs-5 fw-semibold" data-accordion-header>Offers</div>
                    <div>
                    @foreach ($application->offers as $offerItem)
                        <div class="mt-3">
                            <p class="mb-1"><strong>{{ $offerItem->status->label() }}</strong> · sent {{ $offerItem->created_at->format('M j, Y') }} by {{ $offerItem->issuer->name }}</p>
                            @include('offers._body', ['offer' => $offerItem])
                        </div>
                    @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="card employee-form-card">
            <div>
                <div class="fs-5 fw-semibold" data-accordion-header>Timeline</div>
                <div><ul class="list-group list-group-flush mt-2">
                    @foreach ($application->events->reverse() as $event)
                        <li class="list-group-item px-0">
                            <span class="fw-medium">{{ $event->label() }}</span>
                            <small class="d-block text-body-secondary">{{ $event->created_at->format('M j, Y g:i A') }} · {{ $event->actor?->name ?? 'System' }}</small>
                            @if (! empty($event->details['reason'])) <small class="d-block">Reason: {{ $event->details['reason'] }}</small> @endif
                        </li>
                    @endforeach
                </ul></div>
            </div>
        </section>
        </div>
    </div>
@endsection
