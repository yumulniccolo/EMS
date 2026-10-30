@extends('layouts.applicant')

@section('title', 'Apply · '.config('app.name'))

{{--
    The application form. The position is automatic (the one chosen). The applicant's profile is not asked again: only the resume
    (education, work history, and a file) and an optional portfolio go with this application. Emergency contact and government
    and tax details are asked for after hiring. Save Draft keeps what was typed (not the files) in this browser for this applicant and
    position, without the Submit checks; the form fills itself from the draft when it is opened again (resources/js/application-drafts.js).
--}}
@section('content')
    <div class="d-grid gap-4">
        <div>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('applicant.positions.show', $position->id) }}">
                <x-icon name="back" /> Back to the position
            </a>
        </div>

        <div class="alert alert-secondary mb-0 d-none" role="status" data-draft-restored>
            Your saved draft for this position is filled in below. Attach your resume again before you submit; files are not kept in a draft.
        </div>

        <div>
            <h1 class="page-title">Apply for a Position</h1>
            <p class="text-body-secondary mt-1 mb-0">Your profile is already on file ({{ $profile->full_name }}). Add your resume and portfolio for this position.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mb-0" role="alert">
                <ul class="mb-0 ps-3">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('applicant.applications.store') }}" enctype="multipart/form-data" class="d-grid gap-4" novalidate
            data-application-draft data-draft-owner="{{ auth()->id() }}" data-draft-position="{{ $position->id }}" data-draft-title="{{ $position->title }}"
            data-draft-unit="{{ $position->college?->name ?? $position->office?->name }}" data-draft-has-old="{{ session()->hasOldInput() ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="job_title_id" value="{{ $position->id }}">

            <section class="card employee-form-card" aria-labelledby="position-title">
                <h2 class="employee-form-section" id="position-title">Position Applied For</h2>
                <p class="mb-0 fw-medium">{{ $position->title }}</p>
                <small class="text-body-secondary">{{ $position->college?->name ?? $position->office?->name }}</small>
            </section>

            <section class="card employee-form-card" aria-labelledby="resume-title">
                <h2 class="employee-form-section" id="resume-title">Resume</h2>

                <h3 class="fs-6">Education</h3>
                <div data-sortable-rows data-sortable-required="education[0][school]">
                    @foreach (range(0, 2) as $i)
                        <div class="row g-2 mb-2 sortable-row">
                            <span class="sort-handle" title="Drag to reorder" aria-hidden="true">⋮⋮</span>
                            <div class="col-md-3"><x-field :name="'education['.$i.'][level]'" label="Level" placeholder="Bachelor, Master, Doctorate" /></div>
                            <div class="col-md-4"><x-field :name="'education['.$i.'][school]'" label="School" :required="$i === 0" /></div>
                            <div class="col-md-3"><x-field :name="'education['.$i.'][course]'" label="Course or Degree" /></div>
                            <div class="col-md-2"><x-field :name="'education['.$i.'][year_graduated]'" label="Year Graduated" /></div>
                        </div>
                    @endforeach
                </div>

                <h3 class="fs-6 mt-3">Work History</h3>
                <div data-sortable-rows>
                    @foreach (range(0, 2) as $i)
                        <div class="row g-2 mb-2 sortable-row">
                            <span class="sort-handle" title="Drag to reorder" aria-hidden="true">⋮⋮</span>
                            <div class="col-md-4"><x-field :name="'work_history['.$i.'][employer]'" label="Employer" /></div>
                            <div class="col-md-4"><x-field :name="'work_history['.$i.'][position]'" label="Position" /></div>
                            <div class="col-md-2"><x-field :name="'work_history['.$i.'][from]'" label="From" placeholder="2019-06" /></div>
                            <div class="col-md-2"><x-field :name="'work_history['.$i.'][to]'" label="To" placeholder="2024-03 or Present" /></div>
                        </div>
                    @endforeach
                </div>
                <p class="form-text mb-0">Drag the dots beside a row to put your most recent first.</p>

                <div class="mt-3">
                    <label for="resume" class="form-label is-required">Resume file</label>
                    <input type="file" class="form-control @error('resume') is-invalid @enderror" id="resume" name="resume" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
                    <div class="form-text">PDF, Word, or image, up to 5 MB.</div>
                </div>
            </section>

            <section class="card employee-form-card" aria-labelledby="portfolio-title">
                <h2 class="employee-form-section" id="portfolio-title">Portfolio <small>Optional</small></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="portfolio" class="form-label">Portfolio file</label>
                        <input type="file" class="form-control @error('portfolio') is-invalid @enderror" id="portfolio" name="portfolio" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                    </div>
                    <div class="col-md-6"><x-field name="portfolio_url" type="url" label="Portfolio link" placeholder="https://" /></div>
                    <div class="col-12">
                        <label for="field-cover_note" class="form-label">Note to HR</label>
                        <textarea class="form-control" id="field-cover_note" name="cover_note" rows="3" maxlength="1500">{{ old('cover_note') }}</textarea>
                    </div>
                </div>
            </section>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary me-auto" href="{{ route('applicant.positions') }}">Cancel</a>
                <button type="button" class="btn btn-outline-primary" data-save-draft>Save Draft</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
@endsection
