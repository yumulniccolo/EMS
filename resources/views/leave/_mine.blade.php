{{--
    The signed-in person's own leave, shared by the Employee interface and the HR "My Leave" page: credits (vacation and
    sick are earned each month from the hire date and carry over; the rest are yearly), their requests with how each
    was decided, a form to file a new request, and cancelling a pending one. The credits follow the Civil Service leave
    rules and are reference data for HR, not payroll.
--}}
<div class="d-grid gap-4" data-page="employee-leave">
    <div>
        <h1 class="page-title">My Leave</h1>
        <p class="text-body-secondary mt-1 mb-0">Your leave credits and your requests</p>
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
                    <p class="form-text mb-0 mt-3">At {{ $employee->weekly_hours }} hours a week you earn {{ rtrim(rtrim(number_format($employee->leaveCreditShare() * 100, 1), '0'), '.') }}% of the full-time vacation and sick leave.</p>
                @endif
            @endif
        </section>

        @if ($types !== [])
            <section class="card employee-form-card" aria-labelledby="file-title">
                <h2 class="fs-5" id="file-title">File a leave request</h2>
                <form method="post" action="{{ route($storeRoute) }}" class="row g-3" novalidate>
                    @csrf
                    <div class="col-md-4"><x-select-field name="leave_type" label="Leave type" :options="$types" placeholder="Select" required /></div>
                    <div class="col-md-4"><x-field name="start_date" type="date" label="First day" required /></div>
                    <div class="col-md-4"><x-field name="end_date" type="date" label="Last day" required /></div>
                    <div class="col-12"><x-field name="reason" label="Reason (optional)" /></div>
                    <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Send to HR Admin</button></div>
                </form>
                <p class="form-text mb-0 mt-3">Weekends are not counted for most leaves. Sick and emergency leave can start up to 30 days ago. A request cannot use more than the credits you have left.</p>
            </section>
        @endif

        <section class="card employee-form-card" aria-labelledby="requests-title">
            <h2 class="fs-5" id="requests-title">My requests</h2>
            <ul class="list-group list-group-flush">
                @forelse ($requests as $leaveRequest)
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <div class="fw-medium">
                                {{ $leaveRequest->leave_type->label() }}
                                <span class="status-pill">{{ $leaveRequest->status->label() }}</span>
                            </div>
                            <small class="text-body-secondary d-block">
                                {{ $leaveRequest->start_date->format('M j, Y') }}
                                @if (! $leaveRequest->end_date->isSameDay($leaveRequest->start_date)) to {{ $leaveRequest->end_date->format('M j, Y') }} @endif
                                · {{ rtrim(rtrim(number_format((float) $leaveRequest->days, 1), '0'), '.') }} day(s)
                            </small>
                            @if ($leaveRequest->decision_note)
                                <small class="d-block">HR: {{ $leaveRequest->decision_note }}</small>
                            @endif
                        </div>
                        @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending)
                            <form method="post" action="{{ route($cancelRoute, $leaveRequest) }}" data-confirm-title="Cancel leave request"
                                data-confirm="Cancel this leave request? This cannot be undone." data-confirm-button="Cancel Request" data-confirm-cancel="Keep Request">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Request</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item px-0 text-body-secondary">You have not filed any leave yet.</li>
                @endforelse
            </ul>
        </section>
    @endif
</div>
