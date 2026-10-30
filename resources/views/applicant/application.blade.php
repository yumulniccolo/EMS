@extends('layouts.applicant')

@section('title', $application->jobTitle->title.' · '.config('app.name'))

@php
    use App\Enums\ApplicationStatus;
@endphp

{{--
    One application: its status and timeline, the demo interview HR scheduled (with Ask to Reschedule and Cancel Application), the
    offer to accept or decline (summary of the position and terms), once hired, how to sign in as an employee, and a read-only summary
    of what was submitted (it cannot be changed after submitting).
--}}
@section('content')
    <div class="d-grid gap-4" data-submitted-position="{{ $application->job_title_id }}">
        <div>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('applicant.applications') }}">
                <x-icon name="back" /> Back to Applications
            </a>
        </div>

        <section class="card employee-form-card">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="page-title">{{ $application->jobTitle->title }}</h1>
                    <span class="text-body-secondary">{{ $application->jobTitle->college?->name ?? $application->jobTitle->office?->name }} · Applied {{ $application->applied_on->format('M j, Y') }}</span>
                </div>
                <span class="status-pill {{ $application->status->cssClass() }}">{{ $application->status->label() }}</span>
            </div>
            @if ($application->status_reason && in_array($application->status, [ApplicationStatus::Rejected, ApplicationStatus::Cancelled], true))
                <p class="mb-0 mt-3"><strong>Reason:</strong> {{ $application->status_reason }}</p>
            @endif
        </section>

        @if ($application->status === ApplicationStatus::Hired && $application->employee)
            <section class="card employee-form-card border-success" aria-labelledby="hired-title">
                <h2 class="fs-5" id="hired-title">You are hired</h2>
                <p class="mb-2">Welcome to PLP. Start date: <strong>{{ $application->employee->hired_on->format('F j, Y') }}</strong>. Sign in to the Employee Management System as an employee:</p>
                <dl class="row mb-2">
                    <dt class="col-sm-3">Login email</dt><dd class="col-sm-9">{{ $application->employee->official_email }}</dd>
                    @if ($application->employee->user?->must_change_password)
                        <dt class="col-sm-3">First password</dt><dd class="col-sm-9">Your employee ID, <strong>{{ $application->employee->employee_no }}</strong></dd>
                    @endif
                </dl>
                <p class="small text-body-secondary mb-0">You will be asked to choose your own password and fill in your emergency contact and government and tax details. This applicant account stays available.</p>
            </section>
        @endif

        @if ($offer)
            <section class="card employee-form-card border-primary" aria-labelledby="offer-title">
                <h2 class="fs-5" id="offer-title">Your job offer</h2>
                @include('offers._body', ['offer' => $offer])

                <form method="post" action="{{ route('applicant.offers.accept', [$application, $offer]) }}" class="d-grid gap-3 mt-3" novalidate>
                    @csrf
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agree" name="agree" value="1">
                        <label class="form-check-label" for="agree">I have read and understood this offer and its terms and conditions.</label>
                    </div>
                    <div>
                        <x-field name="signed_name" label="Type your full name to sign" required autocomplete="off" hint="Your typed name is your signature and confirmation." />
                    </div>
                    <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Accept Offer</button></div>
                </form>

                <form method="post" action="{{ route('applicant.offers.decline', [$application, $offer]) }}" class="d-flex gap-2 mt-2" data-confirm-title="Decline offer"
                    data-confirm="Decline this offer? This cannot be undone." data-confirm-button="Decline Offer" data-confirm-cancel="Keep Offer">
                    @csrf
                    <label class="visually-hidden" for="decline-reason">Reason for declining (optional)</label>
                    <input type="text" class="form-control" id="decline-reason" name="reason" placeholder="Reason for declining (optional)">
                    <button type="submit" class="btn btn-outline-danger text-nowrap">Decline Offer</button>
                </form>
            </section>
        @endif

        @if ($interview)
            <section class="card employee-form-card" aria-labelledby="interview-title">
                <h2 class="fs-5" id="interview-title">Your demo interview</h2>
                <dl class="row mb-2">
                    <dt class="col-sm-3">When</dt><dd class="col-sm-9">{{ $interview->starts_at->format('l, M j, Y') }}, {{ $interview->starts_at->format('g:i A') }} to {{ $interview->ends_at->format('g:i A') }}</dd>
                    <dt class="col-sm-3">Where</dt>
                    <dd class="col-sm-9">
                        @if ($interview->mode === \App\Enums\InterviewMode::Online)
                            Online: <a href="{{ $interview->meeting_link }}" target="_blank" rel="noopener">{{ $interview->meeting_link }}</a>
                        @else
                            {{ $interview->venue }}
                        @endif
                    </dd>
                    <dt class="col-sm-3">Interviewer</dt><dd class="col-sm-9">{{ $interview->interviewer->name }}</dd>
                </dl>

                @if ($interview->reschedule_requested_at)
                    <div class="alert alert-info">You asked HR to reschedule ({{ $interview->reschedule_requested_at->diffForHumans() }}). They will set a new time and notify you.</div>
                @else
                    <form method="post" action="{{ route('applicant.interviews.reschedule', [$application, $interview]) }}" class="row g-2 mb-3" novalidate>
                        @csrf
                        <div class="col-md-6"><x-field name="reason" label="Why can you not attend?" required /></div>
                        <div class="col-md-6"><x-field name="available_days" label="Days you are available" placeholder="e.g. Mon and Wed afternoons" required /></div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Ask to Reschedule</button></div>
                    </form>
                @endif

                <p class="small text-body-secondary mb-0">
                    You can ask to reschedule any time before the interview. You cannot cancel your application within {{ $cutoffHours }} hours of the interview.
                </p>
            </section>
        @endif

        @if (in_array($application->status, [ApplicationStatus::Applied, ApplicationStatus::DemoInterview], true))
            <section class="card employee-form-card" aria-labelledby="cancel-title">
                <h2 class="fs-5" id="cancel-title">Cancel this application</h2>
                @if ($interview && ! $interview->canBeCancelledByApplicant())
                    <p class="text-body-secondary mb-0">Your demo interview is less than {{ $cutoffHours }} hours away, so the application can no longer be cancelled. Please attend, or contact HR.</p>
                @else
                    <form method="post" action="{{ route('applicant.applications.cancel', $application) }}" class="d-flex gap-2 flex-wrap" data-confirm-title="Cancel application"
                        data-confirm="Cancel this application? This cannot be undone." data-confirm-button="Cancel Application" data-confirm-cancel="Keep Application">
                        @csrf
                        <label class="visually-hidden" for="cancel-reason">Reason (optional)</label>
                        <input type="text" class="form-control" style="max-width: 28rem" id="cancel-reason" name="reason" placeholder="Reason (optional)">
                        <button type="submit" class="btn btn-outline-danger">Cancel Application</button>
                    </form>
                @endif
            </section>
        @endif

        @php($profile = $application->applicant->applicantProfile)
        <section class="card employee-form-card" aria-labelledby="submitted-title">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <h2 class="fs-5 mb-0" id="submitted-title">Your application</h2>
                <span class="badge text-bg-success">Submitted</span>
            </div>
            <p class="small text-body-secondary">What you sent on {{ $application->applied_on->format('M j, Y') }}. A submitted application cannot be changed.</p>

            <h3 class="fs-6">Personal details</h3>
            <dl class="row mb-3">
                <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $profile?->full_name ?? $application->applicant->name }}</dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $application->applicant->email }}</dd>
                <dt class="col-sm-4">Mobile number</dt><dd class="col-sm-8">{{ $profile?->phone ?? '—' }}</dd>
                <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $profile?->address ?? '—' }}</dd>
                <dt class="col-sm-4">Birthdate</dt><dd class="col-sm-8">{{ $profile?->birthdate?->format('M j, Y') ?? '—' }}</dd>
                <dt class="col-sm-4">Sex · Civil status</dt><dd class="col-sm-8">{{ collect([$profile?->sex?->value, $profile?->marital_status?->value])->filter()->implode(' · ') ?: '—' }}</dd>
            </dl>

            <h3 class="fs-6">Education</h3>
            <ul class="list-group list-group-flush mb-3">
                @forelse ($application->education ?? [] as $row)
                    <li class="list-group-item bg-transparent px-0">
                        <span class="fw-medium">{{ $row['school'] ?? '' }}</span>
                        <small class="d-block text-body-secondary">{{ collect([$row['level'] ?? null, $row['course'] ?? null, isset($row['year_graduated']) ? 'Graduated '.$row['year_graduated'] : null])->filter()->implode(' · ') }}</small>
                    </li>
                @empty
                    <li class="list-group-item bg-transparent px-0 text-body-secondary">None given.</li>
                @endforelse
            </ul>

            <h3 class="fs-6">Work history</h3>
            <ul class="list-group list-group-flush mb-3">
                @forelse ($application->work_history ?? [] as $row)
                    <li class="list-group-item bg-transparent px-0">
                        <span class="fw-medium">{{ $row['position'] ?? '' }}{{ ! empty($row['employer']) ? ' · '.$row['employer'] : '' }}</span>
                        <small class="d-block text-body-secondary">{{ collect([$row['from'] ?? null, $row['to'] ?? null])->filter()->implode(' to ') }}</small>
                    </li>
                @empty
                    <li class="list-group-item bg-transparent px-0 text-body-secondary">None given.</li>
                @endforelse
            </ul>

            <h3 class="fs-6">Resume and portfolio</h3>
            <ul class="list-group list-group-flush mb-3">
                @forelse ($application->documents as $document)
                    <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span>{{ ucfirst($document->kind) }}: {{ $document->original_name }}</span>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show', $document->uuid) }}" target="_blank" rel="noopener">Open</a>
                    </li>
                @empty
                    <li class="list-group-item bg-transparent px-0 text-body-secondary">No files attached.</li>
                @endforelse
                @if ($application->portfolio_url)
                    <li class="list-group-item bg-transparent px-0">Portfolio link: <a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener">{{ $application->portfolio_url }}</a></li>
                @endif
            </ul>

            <h3 class="fs-6">Note to HR</h3>
            <p class="mb-0 {{ $application->cover_note ? '' : 'text-body-secondary' }}">{{ $application->cover_note ?: 'None.' }}</p>
        </section>

        <section class="card employee-form-card" aria-labelledby="history-title">
            <h2 class="fs-5" id="history-title">History</h2>
            <ul class="list-group list-group-flush">
                @foreach ($application->events->reverse() as $event)
                    <li class="list-group-item px-0">
                        <span class="fw-medium">{{ $event->label() }}</span>
                        <small class="d-block text-body-secondary">{{ $event->created_at->format('M j, Y g:i A') }}</small>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
