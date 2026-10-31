@extends('layouts.admin')

@section('title', 'Archived Employees · '.config('app.name'))

{{-- Employees who are no longer active (Inactive, Resigned, Retired, or Terminated). Nothing is deleted; the HR Admin can restore a record. --}}
@section('content')
    <div class="d-grid gap-4" data-page="archived-employees">
        <div class="d-flex align-items-center gap-3">
            <a class="page-back" href="{{ route('employees.index') }}" aria-label="Back to employees"><x-icon name="back" /></a>
            <div>
                <h1 class="page-title">Archived Employees</h1>
                <p class="text-body-secondary mt-1 mb-0">Records are kept for good. Restore one to make the employee active again.</p>
            </div>
        </div>

        <form method="get" class="card employees-filters" data-auto-submit>
            <label for="archived-search" class="visually-hidden">Search archived employees</label>
            <div class="search-field">
                <x-icon name="search" />
                <input type="search" class="form-control" id="archived-search" name="q" value="{{ $term }}" placeholder="Search archived employees..." autocomplete="off">
            </div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">College / Office</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">Status</th>
                            <th scope="col">Since</th>
                            <th scope="col">Reason</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="tabular-nums">{{ $employee->employee_no }}</td>
                                <td><a class="text-reset fw-medium" href="{{ route('employees.show', $employee) }}">{{ $employee->last_name }}, {{ $employee->first_name }} {{ $employee->middle_name }}</a></td>
                                <td>{{ collect([$employee->college?->code, $employee->office?->code])->filter()->implode(' · ') }}</td>
                                <td>{{ $employee->jobTitle->title }}</td>
                                <td>{{ ucfirst($employee->status->value) }}</td>
                                <td class="text-nowrap">{{ $employee->status_effective_on?->format('M j, Y') ?? '—' }}</td>
                                <td>{{ $employee->status_reason ?? '—' }}</td>
                                <td class="text-end">
                                    <form method="post" action="{{ route('employees.status', $employee) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="employees-empty text-center"><strong>No archived employees</strong></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} archived employees</span>
                {{ $employees->links() }}
            </div>
        </div>
    </div>
@endsection
