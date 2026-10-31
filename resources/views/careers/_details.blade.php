{{--
    One position's details: description, responsibilities, qualifications, requirements, and (for a part-time post) the contract
    term, with Apply Now. There is no benefits section: benefits are decided with the applicant at the offer. A closed position can
    be read but not applied for, and an applicant who already applied is pointed to that application instead.
--}}
@php
    $typeLabel = \App\Enums\EmploymentType::tryFrom((string) $position->employment_type)?->label() ?? 'Not specified';
    $isOpen = $position->openings > 0;
    $backRoute = $context === 'public' ? 'careers.index' : 'applicant.positions';
    $applyUrl = auth()->check() ? route('applicant.apply', ['position' => $position->id]) : route('applicant.register', ['position' => $position->id]);
    $canApplyHere = ! auth()->check() || auth()->user()->isApplicant();
@endphp

<div class="d-grid gap-4">
    <div>
        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route($backRoute) }}">
            <x-icon name="back" />
            Back to Open Positions
        </a>
    </div>

    <section class="card employee-form-card">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="min-w-0">
                <h1 class="page-title">{{ $position->title }}</h1>
                <div class="d-flex align-items-center gap-2 flex-wrap mt-1 text-body-secondary">
                    <span>{{ $position->college?->name ?? $position->office?->name }}</span>
                    @if ($isOpen)
                        <span class="status-pill employment-active">Open · {{ $position->openings }} {{ \Illuminate\Support\Str::plural('opening', $position->openings) }}</span>
                    @else
                        <span class="ems-chip">Closed</span>
                    @endif
                </div>
            </div>

            <div class="d-grid gap-2 text-md-end">
                @if ($existingApplication)
                    <a class="btn btn-outline-primary" href="{{ route('applicant.applications.show', $existingApplication) }}">View My Application</a>
                @elseif (! $isOpen)
                    <button type="button" class="btn btn-primary" disabled>Applications closed</button>
                @elseif ($canApplyHere)
                    <a class="btn btn-primary" href="{{ $applyUrl }}" @guest data-terms-gate @endguest>Apply Now</a>
                @endif
            </div>
        </div>

        <dl class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mt-1 mb-0 employee-details">
            <div class="col"><dt>Base Pay</dt><dd class="mb-0 tabular-nums">{{ $pay ? '₱'.number_format((float) $pay, 2).' per month (SG '.$position->salary_grade.')' : 'Not specified' }}</dd></div>
            <div class="col"><dt>Employment Type</dt><dd class="mb-0">{{ $typeLabel }}</dd></div>
            <div class="col"><dt>Location</dt><dd class="mb-0">{{ $position->location ?: 'Not specified' }}</dd></div>
            <div class="col"><dt>Openings</dt><dd class="mb-0 tabular-nums">{{ $position->openings }}</dd></div>
            @if ($termLabel)
                <div class="col"><dt>Contract</dt><dd class="mb-0">{{ $termLabel }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="card employee-form-card">
        <section class="mb-4">
            <h2 class="employee-form-section">About the Position</h2>
            <p class="mb-0">{{ $position->description ?: 'No description is available.' }}</p>
        </section>

        @foreach (['responsibilities' => 'Responsibilities', 'qualifications' => 'Qualifications', 'requirements' => 'Requirements'] as $list => $heading)
            <section class="mb-4">
                <h2 class="employee-form-section">{{ $heading }}</h2>
                @if (! empty($position->{$list}))
                    <ul class="mb-0">
                        @foreach ($position->{$list} as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-body-secondary mb-0">None listed.</p>
                @endif
            </section>
        @endforeach
    </section>
</div>
