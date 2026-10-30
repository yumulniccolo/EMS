@extends('layouts.applicant')

@section('title', 'My Applications · '.config('app.name'))

{{--
    The applicant's applications. A row opens its application page (read only once submitted). Drafts saved on the Apply form
    (kept in this browser, resources/js/application-drafts.js) are added at the top from the template below; a draft row opens the
    Apply form again to continue and submit.
--}}
@section('content')
    <div class="d-grid gap-4">
        <div>
            <h1 class="page-title">My Applications</h1>
            <p class="text-body-secondary mt-1 mb-0">Follow each application from Applied to Hired.</p>
        </div>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Position</th>
                            <th scope="col">Applied On</th>
                            <th scope="col">Status</th>
                            <th scope="col">Next</th>
                        </tr>
                    </thead>
                    <tbody data-draft-list data-draft-owner="{{ auth()->id() }}" data-apply-url="{{ route('applicant.apply') }}">
                        @forelse ($applications as $application)
                            @php($interview = $application->activeInterview())
                            <tr class="employee-row" data-row-href="{{ route('applicant.applications.show', $application) }}"
                                @if (in_array($application->status, [\App\Enums\ApplicationStatus::Applied, \App\Enums\ApplicationStatus::DemoInterview, \App\Enums\ApplicationStatus::Offered], true)) data-application-open-position="{{ $application->job_title_id }}" @endif>
                                <td>
                                    <a class="text-reset fw-medium" href="{{ route('applicant.applications.show', $application) }}">{{ $application->jobTitle->title }}</a>
                                    <small class="d-block text-body-secondary">{{ $application->jobTitle->college?->name ?? $application->jobTitle->office?->name }}</small>
                                </td>
                                <td class="text-nowrap">{{ $application->applied_on->format('M j, Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge text-bg-success">Submitted</span>
                                        <span class="status-pill {{ $application->status->cssClass() }}">{{ $application->status->label() }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($interview)
                                        Demo interview {{ $interview->starts_at->format('M j, Y g:i A') }}
                                    @elseif ($application->status === \App\Enums\ApplicationStatus::Offered)
                                        <strong>Answer your offer</strong>
                                    @elseif ($application->status === \App\Enums\ApplicationStatus::Applied)
                                        HR is reviewing your documents
                                    @else
                                        <span class="text-body-secondary">None</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr data-draft-empty><td colspan="4" class="employees-empty text-center"><strong>No applications yet</strong> <a href="{{ route('applicant.positions') }}">Browse the open positions</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <template data-draft-row-template>
        <tr class="employee-row" data-row-href="">
            <td>
                <a class="text-reset fw-medium" href="" data-draft-link></a>
                <small class="d-block text-body-secondary" data-draft-unit></small>
            </td>
            <td class="text-nowrap">
                <span class="text-body-secondary">Not submitted</span>
                <small class="d-block text-body-secondary" data-draft-saved></small>
            </td>
            <td><span class="badge text-bg-warning">Draft</span></td>
            <td>Continue and submit</td>
        </tr>
    </template>
@endsection
