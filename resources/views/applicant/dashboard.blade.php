@extends('layouts.applicant')

@section('title', 'Dashboard · '.config('app.name'))

@php
    use App\Enums\ApplicationStatus;

    $latest = $applications->first();
    $openCount = $applications->whereIn('status', [ApplicationStatus::Applied, ApplicationStatus::DemoInterview, ApplicationStatus::Offered])->count();
    $upcomingInterviews = $applications
        ->map(fn ($application) => $application->activeInterview()?->setRelation('application', $application))
        ->filter(fn ($interview) => $interview && $interview->ends_at->isFuture())
        ->sortBy('starts_at')
        ->values();
    $firstOffer = $offers->first();

    $nextStep = match (true) {
        ! $profile->isComplete() => ['value' => 'Complete your profile', 'caption' => 'You fill it in once, before your first application'],
        $firstOffer !== null => ['value' => 'Answer your offer', 'caption' => $firstOffer->jobTitle->title],
        $nextInterview !== null => ['value' => 'Demo interview', 'caption' => $nextInterview->starts_at->format('M j, Y · g:i A')],
        $hired !== null => ['value' => 'Sign in as an employee', 'caption' => 'You are hired as '.$hired->jobTitle->title],
        $openCount > 0 => ['value' => 'Wait for HR', 'caption' => 'HR is reviewing your documents'],
        default => ['value' => 'Apply for a position', 'caption' => 'Browse the open positions to start'],
    };

    $summary = [
        [
            'label' => 'Applications',
            'value' => $applications->count(),
            'caption' => $applications->isEmpty() ? 'No applications submitted yet' : $openCount.' in progress',
            'isNumber' => true,
        ],
        [
            'label' => 'Latest Status',
            'value' => $latest?->status->label() ?? 'None yet',
            'caption' => $latest ? $latest->jobTitle->title.' · applied '.$latest->applied_on->format('M j, Y') : 'Your newest application shows here',
            'isNumber' => false,
        ],
        ['label' => 'Next Step', ...$nextStep, 'isNumber' => false],
    ];

    $quickLinks = [
        ['label' => 'Profile', 'caption' => 'Your details and contact information', 'icon' => 'user', 'route' => 'applicant.profile'],
        ['label' => 'Calendar', 'caption' => 'Your demo interview and interview dates', 'icon' => 'calendar', 'route' => 'applicant.calendar'],
        ['label' => 'Open Positions', 'caption' => 'Find a position to apply for', 'icon' => 'job-titles', 'route' => 'applicant.positions'],
        ['label' => 'Applications', 'caption' => 'Follow your applications', 'icon' => 'recruitment', 'route' => 'applicant.applications'],
    ];
@endphp

{{--
    The applicant's home: three summary cards (applications, latest status, next step), their applications beside the scheduled
    demo interviews, and quick links. Notices for an incomplete profile, an offer, or a hire stay under the greeting.
--}}
@section('content')
    <div class="d-grid gap-4">
        <div>
            <h1 class="page-title">Welcome, {{ $profile->first_name }}</h1>
            <p class="text-body-secondary mt-1 mb-0">Follow your applications and your interview schedule here.</p>
        </div>

        @unless ($profile->isComplete())
            <div class="alert alert-warning mb-0" role="status">
                Your profile is not complete yet. <a href="{{ route('applicant.profile') }}">Complete it once</a> to be able to apply.
            </div>
        @endunless

        @if ($hired)
            <div class="alert alert-success mb-0" role="status">
                You are hired as <strong>{{ $hired->jobTitle->title }}</strong>.
                <a href="{{ route('applicant.applications.show', $hired) }}">See how to sign in as an employee.</a>
            </div>
        @endif

        @foreach ($offers as $application)
            <div class="alert alert-primary mb-0" role="status">
                You have a job offer for <strong>{{ $application->jobTitle->title }}</strong>.
                <a href="{{ route('applicant.applications.show', $application) }}">Read it and answer.</a>
            </div>
        @endforeach

        <div class="row row-cols-1 row-cols-md-3 g-3">
            @foreach ($summary as $card)
                <div class="col">
                    <section class="card dashboard-kpi">
                        <h2 class="dashboard-kpi-label fs-6 mb-0">{{ $card['label'] }}</h2>
                        <strong @class(['dashboard-kpi-value', 'tabular-nums' => $card['isNumber'], 'fs-5' => ! $card['isNumber']])>{{ $card['value'] }}</strong>
                        <span class="dashboard-kpi-caption">{{ $card['caption'] }}</span>
                    </section>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card dashboard-panel" aria-labelledby="applications-title">
                    <h2 id="applications-title">Your applications</h2>
                    <p class="dashboard-panel-subtitle mb-3">Current status of each application</p>
                    <hr class="mt-0 mb-3 border-secondary-subtle opacity-100">

                    @if ($applications->isEmpty())
                        <div class="text-center py-3">
                            <p class="text-body-secondary mb-3">You have not applied for a position yet.</p>
                            <a class="btn btn-primary" href="{{ route('applicant.positions') }}">Browse Open Positions</a>
                        </div>
                    @else
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach (ApplicationStatus::pipeline() as $status)
                                @if ($counts[$status->value] > 0)
                                    <span class="ems-chip"><span class="tabular-nums">{{ $counts[$status->value] }}</span> {{ $status->label() }}</span>
                                @endif
                            @endforeach
                        </div>
                        <ul class="list-group list-group-flush dashboard-list border-0">
                            @foreach ($applications->take(5) as $application)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3">
                                    <div class="min-w-0">
                                        <a class="text-reset fw-medium" href="{{ route('applicant.applications.show', $application) }}">{{ $application->jobTitle->title }}</a>
                                        <small class="d-block text-body-secondary">
                                            {{ $application->jobTitle->college?->name ?? $application->jobTitle->office?->name }} · Applied {{ $application->applied_on->format('M j, Y') }}
                                        </small>
                                    </div>
                                    <span class="status-pill {{ $application->status->cssClass() }}">{{ $application->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($applications->count() > 5)
                            <div class="mt-3"><a href="{{ route('applicant.applications') }}">See all {{ $applications->count() }} applications</a></div>
                        @endif
                    @endif
                </section>
            </div>

            <div class="col-lg-5">
                <section class="card dashboard-panel" aria-labelledby="interviews-title">
                    <h2 id="interviews-title">Upcoming demo interviews and interviews</h2>
                    <p class="dashboard-panel-subtitle mb-3">Set by HR</p>
                    <hr class="mt-0 mb-3 border-secondary-subtle opacity-100">

                    @if ($upcomingInterviews->isEmpty())
                        <p class="text-body-secondary mb-0">No demo interview or interview is scheduled yet.</p>
                    @else
                        <ul class="list-group list-group-flush dashboard-list border-0">
                            @foreach ($upcomingInterviews as $interview)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                    <div class="min-w-0">
                                        <span class="fw-medium">{{ $interview->application->jobTitle->title }}</span>
                                        <small class="d-block">{{ $interview->starts_at->format('l, M j, Y') }} · {{ $interview->starts_at->format('g:i A') }} to {{ $interview->ends_at->format('g:i A') }}</small>
                                        <small class="d-block text-body-secondary">{{ $interview->placeLabel() }}</small>
                                    </div>
                                    <a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ route('applicant.applications.show', $interview->application) }}">Details</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>

        <section class="card dashboard-panel" aria-labelledby="quick-links-title">
            <h2 id="quick-links-title">Quick links</h2>
            <p class="dashboard-panel-subtitle">Jump to a section</p>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
                @foreach ($quickLinks as $link)
                    <div class="col">
                        <a class="quick-link align-content-start" href="{{ route($link['route']) }}">
                            <x-icon :name="$link['icon']" />
                            <span class="quick-link-label">{{ $link['label'] }}</span>
                            <small>{{ $link['caption'] }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
