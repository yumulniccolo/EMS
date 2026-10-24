@extends('layouts.admin')

@section('title', 'Open Positions · '.config('app.name'))

{{--
    The job postings, from the database: the same job titles the Job Titles and Recruitment pages and the public Careers website
    use. A posting is open while it is published and has openings. A part-time posting says which semester or academic year it
    runs. Benefits are not part of a posting. Only the HR Admin opens, edits, or closes a posting.
--}}
@section('content')
    <div class="d-grid gap-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Open Positions</h1>
                <p class="text-body-secondary mt-1 mb-0">Manage the job postings. Open postings are listed on the public Careers website.</p>
            </div>

            <a class="btn btn-outline-secondary" href="{{ route('careers.index') }}" target="_blank" rel="noopener">View Careers Website</a>
        </div>

        @if ($canManage)
            <section class="card employee-form-card" aria-labelledby="open-new-title">
                <h2 class="fs-5" id="open-new-title">Open a new posting</h2>
                <form method="get" action="{{ route('job-positions.open') }}" class="d-flex gap-2 flex-wrap align-items-end">
                    <div class="flex-grow-1" style="max-width: 32rem">
                        <label for="new-posting" class="form-label">Job title</label>
                        <select class="form-select" id="new-posting" name="job_title" required>
                            <option value="">Choose a job title</option>
                            @foreach ($unopened as $title)
                                <option value="{{ $title->id }}">{{ $title->title }} · {{ $title->college?->code ?? $title->office?->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </form>
                <p class="form-text mb-0 mt-2">A posting is a job title. To post a position that is not listed, add the job title first on the Job Titles page.</p>
            </section>
        @endif

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label for="posting-search" class="visually-hidden">Search job postings</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="posting-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search job postings..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="posting-college" class="visually-hidden">College</label>
                    <select class="form-select" id="posting-college" name="college">
                        <option value="">All Colleges</option>
                        @foreach ($colleges as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['college'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="posting-type" class="visually-hidden">Employment type</label>
                    <select class="form-select" id="posting-type" name="type">
                        <option value="">All Employment Types</option>
                        <option value="full_time" @selected(($filters['type'] ?? '') === 'full_time')>Full-time</option>
                        <option value="part_time" @selected(($filters['type'] ?? '') === 'part_time')>Part-time</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="posting-status" class="visually-hidden">Status</label>
                    <select class="form-select" id="posting-status" name="status">
                        <option value="">All Statuses</option>
                        <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><a class="btn btn-sm btn-outline-secondary" href="{{ route('job-positions.index') }}">Clear Filters</a></div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Position</th>
                            <th scope="col">College or Office</th>
                            <th scope="col">Employment Type</th>
                            <th scope="col">Base Pay</th>
                            <th scope="col" class="text-end">Openings</th>
                            <th scope="col" class="text-end">Applied</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($postings as $posting)
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $posting->title }}</span>                                </td>
                                <td>{{ $posting->college?->name ?? $posting->office?->name }}</td>
                                <td>
                                    {{ \App\Enums\EmploymentType::tryFrom((string) $posting->employment_type)?->label() ?? '—' }}
                                    @if ($posting->contract_term && $posting->academic_year)
                                        <small class="d-block text-body-secondary">{{ $posting->contract_term->label() }}, AY {{ $posting->academic_year }}-{{ $posting->academic_year + 1 }}</small>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ isset($pay[$posting->salary_grade]) ? '₱'.number_format((float) $pay[$posting->salary_grade], 2) : '—' }} <small class="text-body-secondary">SG {{ $posting->salary_grade }}</small></td>
                                <td class="text-end tabular-nums">{{ $posting->openings }}</td>
                                <td class="text-end tabular-nums">{{ $posting->open_applications_count }}</td>
                                <td>
                                    @if ($posting->isOpen()) <span class="status-pill employment-active">Open</span> @else <span class="ems-chip">Closed</span> @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($canManage)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('job-positions.edit', $posting) }}">Edit</a>
                                        @if ($posting->isOpen())
                                            <form method="post" action="{{ route('job-positions.close', $posting) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Close</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"class="employees-empty text-center"><strong>No job postings found</strong> Open a posting from a job title to get started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $postings->firstItem() ?? 0 }} to {{ $postings->lastItem() ?? 0 }} of {{ $postings->total() }} postings</span>
                {{ $postings->links() }}
            </div>
        </div>
    </div>
@endsection
