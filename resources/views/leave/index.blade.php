@extends('layouts.admin')

@section('title', 'Leave Requests · '.config('app.name'))

@php
    $statusTabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
@endphp

{{--
    Every leave request. HR Admin and HR Staff approve or reject a pending one (a rejection needs a reason); a decision is
    final and kept in the request's history. Nobody decides their own request.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="leave-requests">
        <div>
            <h1 class="page-title">Leave Requests</h1>
            <p class="text-body-secondary mt-1 mb-0">{{ $pendingCount }} {{ $pendingCount === 1 ? 'request is' : 'requests are' }} waiting for a decision</p>
        </div>

        @if ($overdueCount > 0)
            <div class="alert alert-warning mb-0" role="alert">
                <strong>{{ $overdueCount }} {{ $overdueCount === 1 ? 'request has' : 'requests have' }} waited more than {{ \App\Models\LeaveRequest::FLAG_AFTER_WORKING_DAYS }} working days.</strong>
                Decide {{ $overdueCount === 1 ? 'it' : 'them' }} soon; they are marked below.
            </div>
        @endif

        <ul class="nav nav-tabs">
            @foreach ($statusTabs as $value => $label)
                <li class="nav-item">
                    <a class="nav-link{{ $status === $value ? ' active' : '' }}" href="{{ route('leave.index', array_merge(request()->except(['page', 'status']), ['status' => $value])) }}"
                        @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        <form method="get" class="card employees-filters" data-auto-submit>
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label for="leave-search" class="visually-hidden">Search employees</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="leave-search" name="q" value="{{ $filters['q'] ?? '' }}" data-suggest-url="{{ route('employees.suggest') }}" placeholder="Search employees..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="leave-type-filter" class="visually-hidden">Leave type</label>
                    <select class="form-select" id="leave-type-filter" name="type">
                        <option value="">All leave types</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="leave-college-filter" class="visually-hidden">College</label>
                    <select class="form-select" id="leave-college-filter" name="college">
                        <option value="">All colleges</option>
                        @foreach ($colleges as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['college'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="leave-office-filter" class="visually-hidden">Office</label>
                    <select class="form-select" id="leave-office-filter" name="office">
                        <option value="">All offices</option>
                        @foreach ($offices as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['office'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Leave</th>
                            <th scope="col">Dates</th>
                            <th scope="col" class="text-end">Days</th>
                            <th scope="col">Status</th>
                            <th scope="col">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $leaveRequest)
                            @php($employee = $leaveRequest->employee)
                            <tr @class(['table-warning' => $leaveRequest->isOverdue()])>
                                <td>
                                    <a class="text-reset fw-medium" href="{{ route('employees.show', $employee) }}">{{ $employee->last_name }}, {{ $employee->first_name }}</a>
                                    <small class="d-block text-body-secondary">{{ $employee->jobTitle->title }} · {{ collect([$employee->college?->code, $employee->office?->code])->filter()->implode(' · ') }}</small>
                                </td>
                                <td>
                                    {{ $leaveRequest->leave_type->label() }}
                                    @if ($leaveRequest->reason) <small class="d-block text-body-secondary">{{ $leaveRequest->reason }}</small> @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $leaveRequest->start_date->format('M j, Y') }}
                                    @if (! $leaveRequest->end_date->isSameDay($leaveRequest->start_date)) to {{ $leaveRequest->end_date->format('M j, Y') }} @endif
                                </td>
                                <td class="text-end tabular-nums">{{ rtrim(rtrim(number_format((float) $leaveRequest->days, 1), '0'), '.') }}</td>
                                <td>
                                    <span class="status-pill">{{ $leaveRequest->status->label() }}</span>
                                    @if ($leaveRequest->isOverdue())
                                        <small class="d-block text-warning-emphasis fw-medium">Waiting {{ $leaveRequest->workingDaysWaiting() }} working days</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending)
                                        @if (! $canDecide)
                                            <small class="text-body-secondary">Waiting for an HR Admin.</small>
                                        @elseif ($leaveRequest->employee_id === $ownEmployeeId)
                                            <small class="text-body-secondary">Your own request: another HR Admin decides it.</small>
                                        @else
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <form method="post" action="{{ route('leave.approve', $leaveRequest) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                                </form>
                                                <form method="post" action="{{ route('leave.reject', $leaveRequest) }}" class="d-flex align-items-center gap-2" data-confirm-title="Reject leave request"
                                                    data-confirm="Reject this leave request? The employee is told the reason you gave." data-confirm-button="Reject">
                                                    @csrf
                                                    <label class="visually-hidden" for="reject-{{ $leaveRequest->id }}">Reason for rejecting this request</label>
                                                    <input type="text" class="form-control form-control-sm leave-reject-note" id="reject-{{ $leaveRequest->id }}" name="decision_note" placeholder="Reason to reject" required>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                </form>
                                            </div>
                                        @endif
                                    @elseif ($leaveRequest->decided_at)
                                        <small class="text-body-secondary">
                                            {{ $leaveRequest->status->label() }} by {{ $leaveRequest->decider?->name }} · {{ $leaveRequest->decided_at->format('M j, Y g:i A') }}
                                            @if ($leaveRequest->decision_note) <span class="d-block">{{ $leaveRequest->decision_note }}</span> @endif
                                        </small>
                                    @else
                                        <small class="text-body-secondary">{{ $leaveRequest->status->label() }} by the employee</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="employees-empty text-start text-sm-center"><strong>No leave requests</strong> Nothing matches these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $requests->firstItem() ?? 0 }} to {{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} requests</span>
                {{ $requests->links() }}
            </div>
        </div>
    </div>
@endsection
