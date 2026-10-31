@extends('layouts.admin')

@section('title', 'Job Posting · '.config('app.name'))

@section('content')
    <div class="d-grid gap-4">
        <div>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('job-positions.index') }}"><x-icon name="back" /> Back to Open Job Positions</a>
        </div>

        <div>
            <h1 class="page-title">{{ $jobTitle->title }}</h1>
            <p class="text-body-secondary mt-1 mb-0">
                {{ $jobTitle->college?->name ?? $jobTitle->office?->name }} · SG {{ $jobTitle->salary_grade }}
                {{ $pay ? '· ₱'.number_format((float) $pay, 2).' a month' : '' }}
            </p>
        </div>

        <form method="post" action="{{ route('job-positions.update', $jobTitle) }}" class="d-grid gap-4" novalidate>
            @csrf
            @method('PUT')

            <section class="card employee-form-card" aria-labelledby="posting-title">
                <h2 class="employee-form-section" id="posting-title">Posting</h2>
                <div class="row g-3">
                    <div class="col-md-4"><x-select-field name="employment_type" label="Employment type" :options="['full_time' => 'Full-time', 'part_time' => 'Part-time']" :value="$jobTitle->employment_type ?? 'full_time'" required /></div>
                    <div class="col-md-4"><x-field name="location" label="Location" :value="$jobTitle->location ?? '12-B Alcalde Jose, Pasig City'" required /></div>
                    <div class="col-md-2"><x-field name="openings" type="number" min="0" max="99" label="Openings" :value="$jobTitle->openings ?: 1" required /></div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_published" value="0">
                            <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" @checked(old('is_published', $jobTitle->is_published ?: true))>
                            <label class="form-check-label" for="is_published">Open to applicants</label>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card employee-form-card" aria-labelledby="contract-title">
                <h2 class="employee-form-section" id="contract-title">End of contract <small>Part-time only</small></h2>
                <p class="text-body-secondary small">A full-time posting has no end of contract. A part-time posting ends with a semester or the academic year (PLP has two semesters a year).</p>
                <div class="row g-3">
                    <div class="col-md-4"><x-select-field name="contract_term" label="Term" :options="$terms" :value="$jobTitle->contract_term?->value" placeholder="Not part-time" /></div>
                    <div class="col-md-4"><x-select-field name="academic_year" label="Academic year" :options="$academicYears" :value="$jobTitle->academic_year" placeholder="Choose a year" /></div>
                </div>
            </section>

            <section class="card employee-form-card" aria-labelledby="desc-title">
                <h2 class="employee-form-section" id="desc-title">Description</h2>
                <div class="row g-3">
                    <div class="col-12"><x-field name="summary" label="Short description" :value="$jobTitle->summary" maxlength="200" hint="One or two sentences, shown on the job card. Up to 200 characters." required /></div>
                    <div class="col-12">
                        <label for="description" class="form-label is-required">Full description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description', $jobTitle->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @foreach (['responsibilities' => 'Responsibilities', 'qualifications' => 'Qualifications', 'requirements' => 'Requirements'] as $field => $label)
                        <div class="col-md-4">
                            <label for="{{ $field }}" class="form-label{{ $field !== 'requirements' ? ' is-required' : '' }}">{{ $label }}</label>
                            <textarea class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" rows="5">{{ old($field, implode("\n", $jobTitle->{$field} ?? [])) }}</textarea>
                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                    <div class="col-12"><div class="form-text mt-0">Write one item per line. Benefits are not part of a posting: HR adds them to the offer after the interview.</div></div>
                </div>
            </section>

            <div class="d-flex justify-content-end gap-2">
                @if ($jobTitle->isOpen())
                    <button type="submit" form="close-posting-form" class="btn btn-outline-secondary me-auto">Close Posting</button>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('job-positions.index') }}">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Posting</button>
            </div>
        </form>

        @if ($jobTitle->isOpen())
            <form method="post" action="{{ route('job-positions.close', $jobTitle) }}" id="close-posting-form" class="d-none" data-confirm-title="Close posting"
                data-confirm="Close this posting? It is taken off the Careers website." data-confirm-button="Close Posting">
                @csrf
            </form>
        @endif
    </div>
@endsection
