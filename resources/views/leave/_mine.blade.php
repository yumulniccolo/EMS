{{--
    The signed-in person's own leave, shared by the Employee interface and the HR "My Leave" page: credits (vacation and
    sick are earned each month from the hire date and carry over; the rest are yearly), their requests with how each
    was decided, a form to file a new request (in a modal), and cancelling a pending one. The credits follow the Civil Service
    leave rules and are reference data for HR, not payroll.
--}}
<div class="d-grid gap-4" data-page="employee-leave">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h1 class="page-title">My Leave</h1>
            <p class="text-body-secondary mt-1 mb-0">Your leave requests and HR’s decisions. You can cancel a request while it is Pending.</p>
        </div>
        @if ($employee && $types !== [])
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#leave-request-modal">
                <x-icon name="plus" />
                Request Leave
            </button>
        @endif
    </div>

    @if (! $employee)
        <div class="card">
            <div class="employees-empty">
                <strong>Your account is not linked to an employee record</strong>
                Only accounts linked to an employee record can file leave.
            </div>
        </div>
    @else
        <section class="card employee-form-card" aria-labelledby="credits-title">
            <h2 class="fs-5" id="credits-title">Leave credits</h2>
            @if ($balances === [])
                <p class="text-body-secondary mb-0">You do not earn leave credits.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Leave</th>
                                <th scope="col" class="text-end">Credits</th>
                                <th scope="col" class="text-end">Used</th>
                                <th scope="col" class="text-end">Pending</th>
                                <th scope="col" class="text-end">Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($balances as $row)
                                <tr>
                                    <td>
                                        {{ $row['label'] }}
                                        <small class="d-block text-body-secondary">{{ $row['accrues'] ? 'Earned each month since you were hired' : 'Yearly allowance for '.$year }}</small>
                                    </td>
                                    @foreach (['entitled', 'used', 'pending', 'remaining'] as $column)
                                        <td class="text-end tabular-nums">{{ rtrim(rtrim(number_format($row[$column], 2), '0'), '.') ?: '0' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($employee->employment_type === \App\Enums\EmploymentType::PartTime)
                    <p class="form-text mb-0 mt-2">At {{ $employee->weekly_hours }} hours a week you earn {{ rtrim(rtrim(number_format($employee->leaveCreditShare() * 100, 1), '0'), '.') }}% of the full-time vacation and sick leave.</p>
                @endif
            @endif
        </section>

        <section class="card overflow-hidden" aria-labelledby="requests-title">
            <h2 class="visually-hidden" id="requests-title">My requests</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Leave Type</th>
                            <th scope="col">Start Date</th>
                            <th scope="col">End Date</th>
                            <th scope="col">Date Filed</th>
                            <th scope="col">Status</th>
                            <th scope="col">HR Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $leaveRequest)
                            <tr role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#my-leave-{{ $leaveRequest->id }}">
                                <td>{{ $leaveRequest->leave_type->label() }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->start_date->format('M j, Y') }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->end_date->format('M j, Y') }}</td>
                                <td class="text-nowrap">{{ $leaveRequest->created_at->format('M j, Y') }}</td>
                                <td><span class="status-pill">{{ $leaveRequest->status->label() }}</span></td>
                                <td>@if ($leaveRequest->decision_note) HR: {{ $leaveRequest->decision_note }} @else <span class="text-body-secondary">—</span> @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="employees-empty text-center"><strong>No leave requests</strong> You have not filed any leave yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @foreach ($requests as $leaveRequest)
            <div class="modal fade" id="my-leave-{{ $leaveRequest->id }}" tabindex="-1" aria-labelledby="my-leave-{{ $leaveRequest->id }}-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="my-leave-{{ $leaveRequest->id }}-title">{{ $leaveRequest->leave_type->label() }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row row-cols-1 row-cols-sm-2 g-3 mb-0 employee-details">
                                <div class="col"><dt>Status</dt><dd class="mb-0"><span class="status-pill">{{ $leaveRequest->status->label() }}</span></dd></div>
                                <div class="col"><dt>Date Filed</dt><dd class="mb-0">{{ $leaveRequest->created_at->format('M j, Y') }}</dd></div>
                                <div class="col"><dt>Dates</dt><dd class="mb-0">
                                    {{ $leaveRequest->start_date->format('M j, Y') }}
                                    @if (! $leaveRequest->end_date->isSameDay($leaveRequest->start_date)) to {{ $leaveRequest->end_date->format('M j, Y') }} @endif
                                </dd></div>
                                <div class="col"><dt>Days</dt><dd class="mb-0 tabular-nums">{{ rtrim(rtrim(number_format((float) $leaveRequest->days, 1), '0'), '.') }}</dd></div>
                                <div class="col-12"><dt>Reason</dt><dd class="mb-0">{{ $leaveRequest->reason ?: '—' }}</dd></div>
                                @if ($leaveRequest->decision_note)
                                    <div class="col-12"><dt>HR Remarks</dt><dd class="mb-0">HR: {{ $leaveRequest->decision_note }}</dd></div>
                                @endif
                            </dl>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending)
                                <form method="post" action="{{ route($cancelRoute, $leaveRequest) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Cancel Request</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if ($types !== [])
            <div class="modal fade" id="leave-request-modal" tabindex="-1" aria-labelledby="leave-request-modal-title" aria-hidden="true" @if ($errors->any()) data-open-on-load @endif>
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form method="post" action="{{ route($storeRoute) }}" class="modal-content" novalidate>
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="leave-request-modal-title">File a leave request</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4"><x-select-field name="leave_type" label="Leave type" :options="$types" placeholder="Select" required /></div>
                                <div class="col-md-4"><x-field name="start_date" type="date" label="First day" required /></div>
                                <div class="col-md-4"><x-field name="end_date" type="date" label="Last day" required /></div>
                                <div class="col-12"><x-field name="reason" label="Reason (optional)" /></div>
                            </div>
                            <p class="form-text mb-0 mt-3">Weekends are not counted for most leaves. Sick and emergency leave can start up to 30 days ago. A request cannot use more than the credits you have left.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send to HR Admin</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
