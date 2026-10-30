@extends('layouts.admin')

@section('title', 'Employees · '.config('app.name'))

@php
    $sort = $filters['sort'] ?? 'name';
    $dir = $filters['dir'] ?? 'asc';
    $sortLink = fn (string $key) => route('employees.index', array_merge(
        request()->except(['page', 'sort', 'dir']),
        ['type' => $type, 'sort' => $key, 'dir' => $sort === $key && $dir === 'asc' ? 'desc' : 'asc'],
    ));
    $ariaSort = fn (string $key) => $sort === $key ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $tabQuery = fn (string $tab) => route('employees.index', ['type' => $tab]);
@endphp

{{--
    Current employees from the database, in two tabs: Faculty and Non-Teaching Staff. Search, filters, sorting, and
    pages are done by the server and live in the address, so a filtered list can be bookmarked and the report opens
    with the same filters. Archived employees are on their own page (HR Admin).
--}}
@section('content')
    <div class="d-grid gap-4" data-page="employees">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <h1 class="page-title">Employees</h1>

            <div class="d-flex gap-2 flex-wrap">
                @if (auth()->user()->isHrAdmin())
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"
                        href="{{ route('employees.report', request()->query()) }}" data-employee-report-link>
                        <x-icon name="report" />
                        Generate Report
                    </a>
                @endif
                <a class="btn btn-primary d-inline-flex align-items-center gap-2" href="{{ route('employees.create') }}">
                    <x-icon name="plus" />
                    Add Employee
                </a>
            </div>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link{{ $type === 'faculty' ? ' active' : '' }}" href="{{ $tabQuery('faculty') }}"
                    @if ($type === 'faculty') aria-current="page" @endif>Faculty <span class="ems-chip tabular-nums">{{ $counts['faculty'] }}</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link{{ $type === 'non-teaching' ? ' active' : '' }}" href="{{ $tabQuery('non-teaching') }}"
                    @if ($type === 'non-teaching') aria-current="page" @endif>Non-Teaching Staff <span class="ems-chip tabular-nums">{{ $counts['non-teaching'] }}</span></a>
            </li>
        </ul>

        <form method="get" class="card employees-filters" data-auto-submit>
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">

            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label for="employee-search" class="visually-hidden">Search employees</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="employee-search" name="q" value="{{ $filters['q'] ?? '' }}" data-suggest-url="{{ route('employees.suggest') }}"
                            placeholder="Search employees..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="employee-college-filter" class="visually-hidden">College</label>
                    <select class="form-select" id="employee-college-filter" name="college">
                        <option value="">All colleges</option>
                        @foreach ($colleges as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['college'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label for="employee-office-filter" class="visually-hidden">Office</label>
                    <select class="form-select" id="employee-office-filter" name="office">
                        <option value="">All offices</option>
                        @foreach ($offices as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['office'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label for="employee-title-filter" class="visually-hidden">Job title</label>
                    <select class="form-select" id="employee-title-filter" name="job_title">
                        <option value="">All job titles</option>
                        @foreach ($jobTitles as $id => $title)
                            <option value="{{ $id }}" @selected((string) ($filters['job_title'] ?? '') === (string) $id)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label for="employee-type-filter" class="visually-hidden">Full-time or part-time</label>
                    <select class="form-select" id="employee-type-filter" name="employment_type">
                        <option value="">Full-time and part-time</option>
                        <option value="full_time" @selected(($filters['employment_type'] ?? '') === 'full_time')>Full-time</option>
                        <option value="part_time" @selected(($filters['employment_type'] ?? '') === 'part_time')>Part-time</option>
                    </select>
                </div>
            </div>

            @if (request()->hasAny(['q', 'college', 'office', 'job_title', 'employment_type']))
                <div class="d-flex justify-content-end mt-3">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $tabQuery($type) }}">Clear Filters</a>
                </div>
            @endif
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="{{ $ariaSort('number') }}"><a class="sort-button" href="{{ $sortLink('number') }}">Employee ID</a></th>
                            <th scope="col" aria-sort="{{ $ariaSort('name') }}"><a class="sort-button" href="{{ $sortLink('name') }}">Name</a></th>
                            <th scope="col">College / Office</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">Type</th>
                            <th scope="col" aria-sort="{{ $ariaSort('hired') }}"><a class="sort-button" href="{{ $sortLink('hired') }}">Date Hired</a></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="employee-row" data-row-href="{{ route('employees.show', $employee) }}" style="cursor: pointer">
                                <td class="tabular-nums">{{ $employee->employee_no }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 fw-medium">
                                        <span class="ems-avatar-sm" aria-hidden="true">{{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}</span>
                                        <a class="text-reset text-decoration-none" href="{{ route('employees.show', $employee) }}">{{ $employee->last_name }}, {{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->suffix }}</a>
                                        @if ($employee->is_on_leave) <span class="ems-chip">On leave</span> @endif
                                    </div>
                                </td>
                                <td>{{ collect([$employee->college?->code, $employee->office?->code])->filter()->implode(' · ') }}</td>
                                <td>{{ $employee->jobTitle->title }}</td>
                                <td>{{ $employee->employment_type->label() }}</td>
                                <td class="tabular-nums text-nowrap">{{ $employee->hired_on?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="employees-empty text-center">
                                    <strong>No employees match</strong>
                                    Try a different search or clear the filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} employees</span>
                {{ $employees->links() }}
            </div>
        </div>

        {{-- The archive holds records that are no longer active; nothing is ever deleted. --}}
        @if (auth()->user()->isHrAdmin())
            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('employees.archived') }}">
                    <x-icon name="folder" />
                    Archived Employees
                </a>
            </div>
        @endif
    </div>
@endsection
