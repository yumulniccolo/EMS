@php
    $detailsRoute = $context === 'public' ? 'careers.show' : 'applicant.positions.show';
    $applied = $appliedIds->contains($position->id);
    $typeLabel = \App\Enums\EmploymentType::tryFrom((string) $position->employment_type)?->label() ?? 'Not specified';
    $monthly = $pay[$position->salary_grade] ?? null;
    $canApplyHere = ! auth()->check() || auth()->user()->isApplicant();
    $applyUrl = auth()->check() ? route('applicant.apply', ['position' => $position->id]) : route('applicant.register', ['position' => $position->id]);
@endphp

<div class="col">
    <article id="position-{{ $position->id }}" class="card dashboard-panel h-100 d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-1">
            <h3 class="fs-6 fw-semibold mb-0">
                <a class="text-reset text-decoration-none" href="{{ route($detailsRoute, $position->id) }}">{{ $position->title }}</a>
            </h3>
            @if ($applied)
                <span class="ems-chip">Applied</span>
            @else
                <span class="status-pill employment-active">Open · {{ $position->openings }} {{ \Illuminate\Support\Str::plural('opening', $position->openings) }}</span>
            @endif
        </div>
        <div class="text-body-secondary">{{ $position->college?->name ?? $position->office?->name }}</div>
        <div class="small text-body-secondary mb-2">{{ $typeLabel }} · {{ $position->location ?: 'Not specified' }}</div>
        <div class="fw-semibold mb-2 tabular-nums">
            {{ $monthly ? '₱'.number_format((float) $monthly, 2).' per month (SG '.$position->salary_grade.')' : 'Not specified' }}
        </div>
        @if ($position->summary)
            <p class="mb-2">{{ $position->summary }}</p>
        @endif
        <div class="mt-auto pt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route($detailsRoute, $position->id) }}" aria-label="View details for {{ $position->title }}">View Details</a>
            @if ($applied)
                <span class="btn btn-sm disabled">Already applied</span>
            @elseif ($canApplyHere)
                <a class="btn btn-sm btn-primary" href="{{ $applyUrl }}" aria-label="Apply for {{ $position->title }}" @guest data-terms-gate @endguest>Apply Now</a>
            @endif
        </div>
    </article>
</div>
