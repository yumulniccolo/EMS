@extends('layouts.admin')

@section('title', 'Recruitment · '.config('app.name'))

@php
    use App\Enums\ApplicationStatus;

    $columns = ['name' => 'Applicant', 'position' => 'Position Applied For', 'date' => 'Application Date', 'status' => 'Status'];
    $sort = $filters['sort'] ?? 'date';
    $dir = $filters['dir'] ?? 'desc';
@endphp

{{--
    The pipeline from the database: Applied, Demo Interview, Offered, Hired, Rejected, and Cancelled (by the applicant). A row opens
    the application page. The applicant's information is collapsible so the list stays short; the resume and portfolio open
    immediately from the Documents buttons.
--}}
@section('content')
    <div class="d-grid gap-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Recruitment</h1>
                <p class="text-body-secondary mt-1 mb-0">Review applications, schedule demo interviews, send offers, and hire. Applicants apply online.</p>
            </div>

            @if (auth()->user()->isHrAdmin())
                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('recruitment.report') }}">
                    <x-icon name="report" />
                    Generate Report
                </a>
            @endif
        </div>

        <section class="card dashboard-panel" aria-labelledby="recruitment-pipeline-title">
            <h2 id="recruitment-pipeline-title">Recruitment pipeline</h2>
            <p class="dashboard-panel-subtitle">{{ $total }} {{ \Illuminate\Support\Str::plural('application', $total) }} in all</p>

            @if ($total > 0)
                <div class="progress-stacked dashboard-pipeline">
                    @foreach ($pipeline as $stage)
                        @if ($stage['count'] > 0)
                            <div class="progress" role="progressbar" style="width: {{ $stage['count'] / $total * 100 }}%" aria-label="{{ $stage['status']->label() }}: {{ $stage['count'] }}">
                                <div class="progress-bar {{ $stage['status']->cssClass() }}"></div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-2 mt-1" role="group" aria-label="Filter by status">
                @foreach ($pipeline as $stage)
                    @php($active = ($filters['status'] ?? null) === $stage['status']->value)
                    <div class="col">
                        <a class="quick-link text-decoration-none {{ $active ? 'border-primary' : '' }}" href="{{ route('recruitment.index', $active ? [] : ['status' => $stage['status']->value]) }}" aria-pressed="{{ $active ? 'true' : 'false' }}">
                            <span class="quick-link-label tabular-nums">{{ $stage['count'] }}</span>
                            <small>{{ $stage['status']->label() }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label for="application-search" class="visually-hidden">Search applications</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="application-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search applicants..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="application-status-filter" class="visually-hidden">Status</label>
                    <select class="form-select" id="application-status-filter" name="status">
                        <option value="">All Statuses</option>
                        @foreach (ApplicationStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="application-interview-filter" class="visually-hidden">Demo interview</label>
                    <select class="form-select" id="application-interview-filter" name="interview">
                        <option value="">Any demo interview</option>
                        <option value="upcoming" @selected(($filters['interview'] ?? '') === 'upcoming')>Not yet (scheduled)</option>
                        <option value="finished" @selected(($filters['interview'] ?? '') === 'finished')>Finished</option>
                        <option value="none" @selected(($filters['interview'] ?? '') === 'none')>No interview yet</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="application-college-filter" class="visually-hidden">College</label>
                    <select class="form-select" id="application-college-filter" name="college">
                        <option value="">All Colleges</option>
                        @foreach ($colleges as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['college'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="input-group">
                        <label class="input-group-text" for="application-applied-on-filter">Application Date</label>
                        <x-date-range id="application-applied-on-filter" from-name="applied_from" to-name="applied_to"
                            :from="$filters['applied_from'] ?? null" :to="$filters['applied_to'] ?? null" placeholder="Any date" />
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('recruitment.index') }}">Clear Filters</a>
            </div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $key => $label)
                                <th scope="col" aria-sort="{{ $sort === $key ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a class="sort-button text-decoration-none" href="{{ route('recruitment.index', array_merge(request()->except('page'), ['sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }}</a>
                                </th>
                            @endforeach
                            <th scope="col">Documents</th>
                            <th scope="col"><span class="visually-hidden">Details</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            @php($profile = $application->applicant->applicantProfile)
                            @php($interview = $application->activeInterview())
                            <tr>
                                <td>
                                    <a class="text-reset fw-medium" href="{{ route('recruitment.show', $application) }}">{{ $profile->last_name }}, {{ $profile->first_name }}</a>
                                    <small class="d-block text-body-secondary">{{ $application->applicant->email }}</small>
                                    @if ($interview?->reschedule_requested_at)
                                        <span class="badge text-bg-warning">Asked to reschedule</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $application->jobTitle->title }}
                                    <small class="d-block text-body-secondary">{{ $application->jobTitle->college?->code ?? $application->jobTitle->office?->code }}</small>
                                </td>
                                <td class="text-nowrap">{{ $application->applied_on->format('M j, Y') }}</td>
                                <td>
                                    <span class="status-pill {{ $application->status->cssClass() }}">{{ $application->status->label() }}</span>
                                    @if ($interview)
                                        <small class="d-block text-body-secondary">{{ $interview->starts_at->format('M j, g:i A') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @foreach ($application->documents as $document)
                                        <a class="btn btn-sm btn-outline-secondary mb-1" href="{{ route('documents.show', $document->uuid) }}" target="_blank" rel="noopener">{{ ucfirst($document->kind) }}</a>
                                    @endforeach
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#info-{{ $application->id }}" aria-expanded="false" aria-controls="info-{{ $application->id }}">Info</button>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('recruitment.show', $application) }}" data-application-modal>Open</a>
                                </td>
                            </tr>
                            <tr class="collapse" id="info-{{ $application->id }}">
                                <td colspan="6" class="bg-body-tertiary">
                                    <div class="row g-3 small">
                                        <div class="col-md-4">
                                            <strong>Contact</strong><br>
                                            {{ $application->applicant->email }}<br>{{ $profile->phone }}<br>{{ $profile->address }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Personal</strong><br>
                                            {{ $profile->birthdate?->format('M j, Y') }} · {{ $profile->sex?->value }} · {{ $profile->marital_status?->value }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Education</strong>
                                            @foreach ($application->education ?? [] as $row)
                                                <div>{{ $row['school'] ?? '' }} {{ ! empty($row['course']) ? '· '.$row['course'] : '' }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="employees-empty text-center"><strong>No applications found</strong> Applications appear when applicants apply online.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $applications->firstItem() ?? 0 }} to {{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications</span>
                {{ $applications->links() }}
            </div>
        </div>
    </div>
    <div class="modal fade" id="application-modal" tabindex="-1" aria-labelledby="application-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="application-modal-title">Application</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-application-modal-body></div>
            </div>
        </div>
    </div>
@endsection
