@extends('layouts.admin')

@section('title', 'Job Titles · '.config('app.name'))

{{--
    The job titles of each college and office, with the pay band that sets each one's base rate (step 1 to step 8, in
    force today). Everyone in HR reads this page; only the HR Admin adds or edits titles and rates.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="job-titles">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Job Titles</h1>
                <p class="text-body-secondary mt-1 mb-0">One shared list of positions. Editing a job title changes it in every college and office that uses it.</p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary" href="{{ route('rates.index') }}">Pay Rates</a>
                @can('manage-reference-data')
                    <a class="btn btn-primary d-inline-flex align-items-center gap-2" href="{{ route('job-titles.create') }}">
                        <x-icon name="plus" />
                        Add Job Title
                    </a>
                @endcan
            </div>
        </div>

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="job-title-search" class="visually-hidden">Search job titles</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="job-title-search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Search job titles..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="job-title-unit" class="visually-hidden">College or office</label>
                    <select class="form-select" id="job-title-unit" name="unit">
                        <option value="">All colleges and offices</option>
                        @foreach ($unitOptions as $group => $options)
                            <optgroup label="{{ $group }}">
                                @foreach ($options as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['unit'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="job-title-category" class="visually-hidden">Category</label>
                    <select class="form-select" id="job-title-category" name="category">
                        <option value="">Faculty and non-teaching</option>
                        <option value="faculty" @selected(($filters['category'] ?? '') === 'faculty')>Faculty</option>
                        <option value="non_teaching" @selected(($filters['category'] ?? '') === 'non_teaching')>Non-teaching</option>
                    </select>
                </div>
            </div>
        </form>

        @php($canManage = auth()->user()->can('manage-reference-data'))
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            @if ($canManage) <th scope="col" class="sortable-handle-cell"><span class="visually-hidden">Order</span></th> @endif
                            <th scope="col">Job title</th>
                            <th scope="col">Used in</th>
                            <th scope="col">Category</th>
                            <th scope="col">Monthly pay</th>
                            <th scope="col" class="text-end">People</th>
                            <th scope="col">Open positions</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody @if ($canManage) data-sortable-list="job-titles" @endif>
                        @forelse ($jobTitles as $jobTitle)
                            @php($pay = $schedule[$jobTitle->salary_grade] ?? null)
                            @php($units = $copies->get($jobTitle->title.'|'.$jobTitle->category->value, collect()))
                            <tr @if ($canManage) class="employee-row" data-row-href="{{ route('job-titles.edit', $jobTitle->id) }}" data-sortable-id="{{ $jobTitle->id }}" @endif>
                                @if ($canManage) <x-sort-handle :label="$jobTitle->title" /> @endif
                                <td class="fw-medium">{{ $jobTitle->title }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($units as $copy)
                                            <span class="ems-chip">{{ $copy->college?->code ?? $copy->office?->code }}</span>
                                        @endforeach
                                    </div>
                                    <small class="text-body-secondary">{{ $units->count() }} {{ \Illuminate\Support\Str::plural('unit', $units->count()) }}</small>
                                </td>
                                <td>{{ $jobTitle->category === \App\Enums\JobTitleCategory::Faculty ? 'Faculty' : 'Non-teaching' }}</td>
                                <td class="tabular-nums text-nowrap">
                                    @if ($pay)
                                        SG {{ $jobTitle->salary_grade }} · ₱{{ number_format((float) $pay, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end tabular-nums">{{ $units->sum('employees_count') }}</td>
                                <td>
                                    @if ($jobTitle->openings > 0)
                                        {{ $jobTitle->openings }} open
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $jobTitle->active_count > 0 ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-body-secondary py-4">No job titles match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $jobTitles->firstItem() ?? 0 }} to {{ $jobTitles->lastItem() ?? 0 }} of {{ $jobTitles->total() }} job titles</span>
                {{ $jobTitles->links() }}
            </div>
        </div>
    </div>
@endsection
