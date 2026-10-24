@extends('layouts.admin')

@section('title', 'Leave Requests · '.config('app.name'))

@php
    $statusTabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
    $days = fn ($leaveRequest) => rtrim(rtrim(number_format((float) $leaveRequest->days, 1), '0'), '.');
@endphp

{{--
    Every leave request. HR Admin and HR Staff approve or reject a pending one (a rejection needs a reason); a decision is
    final and kept in the request's history. Nobody decides their own request. Open a row to see the request and decide it.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="leave-requests">
        <div>
            <h1 class="page-title">Leave Requests</h1>
            <p class="text-body-secondary mt-1 mb-0">
                Every employee’s leave requests. Open a Pending request to approve or reject it.
                {{ $pendingCount }} {{ $pendingCount === 1 ? 'request is' : 'requests are' }} waiting for a decision.
            </p>
        </div>

        @if ($overdueCount > 0)
            <div class="alert alert-warning mb-0" role="alert">
                <strong>{{ $overdueCount }} {{ $overdueCount === 1 ? 'request has' : 'requests have' }} waited more than {{ \App\Models\LeaveRequest::FLAG_AFTER_WORKING_DAYS }} working days.</strong>
                Decide {{ $overdueCount === 1 ? 'it' : 'them' }} soon; they are marked below.
            </div>
        @endif

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <label for="leave-search" class="visually-hidden">Search employees</label>
                    <div class="search-field">
                        <x-icon name="search" />
                        <input type="search" class="form-control" id="leave-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search employees..." autocomplete="off">
                    </div>
                </div>
                <div class="col-sm-6 col-md-3 col-xl-4">
                    <label for="leave-status-filter" class="visually-hidden">Status</label>
                    <select class="form-select" id="leave-status-filter" name="status">
                        @foreach ($statusTabs as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $value === 'all' ? 'All Statuses' : $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-3 col-xl-4">
                    <label for="leave-type-filter" class="visually-hidden">Leave type</label>
                    <select class="form-select" id="leave-type-filter" name="type">
                        <option value="">All Leave Types</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="leave-college-filter" class="visually-hidden">College</label>
                    <select class="form-select" id="leave-college-filter" name="college">
                        <option value="">All Colleges</option>
                        @foreach ($colleges as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['college'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="leave-office-filter" class="visually-hidden">Office</label>
                    <select class="form-select" id="leave-office-filter" name="office">
                        <option value="">All Offices</option>
                        @foreach ($offices as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['office'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('leave.index') }}">Clear Filters</a>
            </div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Leave Type</th>
                            <th scope="col">Start Date</th>
                            <th scope="col">End Date</th>
                            <th scope="col">Date Filed</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $leaveRequest)
                            @php($employee = $leaveRequest->employee)
                            <tr @class(['table-warning' => $leaveRequest->isOverdue()]) role="button" tabindex="0"
                                data-bs-toggle="modal" data-bs-target="#leave-details-{{ $leaveRequest->id }}">
                                <td>
                                    <span class="fw-medium">{{ $employee->last_name }}, {{ $employee->first_name }}</span>
                                    <small class="d-block text-body-secondary">{{ $employee->jobTitle->title }} · {{ collect([$employee->college?->code, $employee->office?->code])->filter()->implode(' · ') }}</small>
                                </td>
                                <td>{{ $leaveRequest->leave_type->label() }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->start_date->format('M j, Y') }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->end_date->format('M j, Y') }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->created_at->format('M j, Y') }}</td>
                                <td>
                                    <span class="status-pill">{{ $leaveRequest->status->label() }}</span>
                                    @if ($leaveRequest->isOverdue())
                                        <small class="d-block text-warning-emphasis fw-medium">Waiting {{ $leaveRequest->workingDaysWaiting() }} working days</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="employees-empty text-center"><strong>No leave requests</strong> Nothing matches these filters.</td></tr>
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

    {{-- A request's details, with the decision when it is still pending. --}}
    @foreach ($requests as $leaveRequest)
        @php($employee = $leaveRequest->employee)
        <div class="modal fade" id="leave-details-{{ $leaveRequest->id }}" tabindex="-1" aria-labelledby="leave-details-{{ $leaveRequest->id }}-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="leave-details-{{ $leaveRequest->id }}-title">{{ $leaveRequest->leave_type->label() }}</h2>
                            <p class="small text-body-secondary mb-0">
                                <a class="text-reset" href="{{ route('employees.show', $employee) }}">{{ $employee->last_name }}, {{ $employee->first_name }}</a>
                                · {{ $employee->jobTitle->title }}
                            </p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body d-grid gap-3">
                        <dl class="row row-cols-1 row-cols-sm-2 g-3 mb-0 employee-details">
                            <div class="col"><dt>Status</dt><dd class="mb-0"><span class="status-pill">{{ $leaveRequest->status->label() }}</span></dd></div>
                            <div class="col"><dt>Date Filed</dt><dd class="mb-0">{{ $leaveRequest->created_at->format('M j, Y') }}</dd></div>
                            <div class="col"><dt>Dates</dt><dd class="mb-0">
                                {{ $leaveRequest->start_date->format('M j, Y') }}
                                @if (! $leaveRequest->end_date->isSameDay($leaveRequest->start_date)) to {{ $leaveRequest->end_date->format('M j, Y') }} @endif
                            </dd></div>
                            <div class="col"><dt>Days</dt><dd class="mb-0 tabular-nums">{{ $days($leaveRequest) }}</dd></div>
                            <div class="col-12"><dt>Reason</dt><dd class="mb-0">{{ $leaveRequest->reason ?: '—' }}</dd></div>
                            @if ($leaveRequest->decided_at)
                                <div class="col-12"><dt>Decision</dt><dd class="mb-0">
                                    {{ $leaveRequest->status->label() }} by {{ $leaveRequest->decider?->name }} · {{ $leaveRequest->decided_at->format('M j, Y g:i A') }}
                                    @if ($leaveRequest->decision_note) <span class="d-block text-body-secondary">{{ $leaveRequest->decision_note }}</span> @endif
                                </dd></div>
                            @elseif ($leaveRequest->status !== \App\Enums\LeaveStatus::Pending)
                                <div class="col-12"><dt>Decision</dt><dd class="mb-0">{{ $leaveRequest->status->label() }} by the employee</dd></div>
                            @endif
                        </dl>

                        @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending)
                            @if (! $canDecide)
                                <p class="text-body-secondary mb-0">Waiting for an HR Admin.</p>
                            @elseif ($leaveRequest->employee_id === $ownEmployeeId)
                                <p class="text-body-secondary mb-0">Your own request: another HR Admin decides it.</p>
                            @else
                                <form method="post" action="{{ route('leave.reject', $leaveRequest) }}" class="d-flex gap-2 flex-wrap">
                                    @csrf
                                    <label class="visually-hidden" for="reject-{{ $leaveRequest->id }}">Reason for rejecting this request</label>
                                    <input type="text" class="form-control flex-grow-1 w-auto" id="reject-{{ $leaveRequest->id }}" name="decision_note" placeholder="Reason to reject" required>
                                    <button type="submit" class="btn btn-outline-danger">Reject</button>
                                </form>
                            @endif
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending && $canDecide && $leaveRequest->employee_id !== $ownEmployeeId)
                            <form method="post" action="{{ route('leave.approve', $leaveRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Approve</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
