@extends('layouts.admin')

@section('title', $employee->full_name.' · '.config('app.name'))

@php
    $isHead = auth()->user()->isHrAdmin();
    $isArchived = $employee->status !== \App\Enums\EmployeeStatus::Active;
    $idLabels = ['tin' => 'TIN', 'gsis' => 'GSIS', 'sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG', 'bir' => 'BIR'];
    $masked = $employee->maskedGovernmentIds();
    $pending = $employee->transfers->firstWhere('applied_at', null);
@endphp

{{--
    An employee's profile (201 file) from the database. Everyone in HR sees it. Government IDs are shown masked only. The HR
    Admin can transfer the employee, change the salary step, and archive or restore the record.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="employee-profile">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h1 class="page-title">Employee Profile</h1>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('employees.index') }}">
                <x-icon name="back" />
                Back to Employees
            </a>
        </div>

        @if (session('revealed'))
            <div class="alert alert-warning mb-0" role="alert">
                <strong>{{ $idLabels[session('revealed')['type']] }} number:</strong>
                <code class="user-select-all">{{ session('revealed')['number'] }}</code>
                <div class="small">Shown once. This view was recorded in the audit log.</div>
            </div>
        @endif

        <section class="card employee-form-card">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="profile-avatar" aria-hidden="true">{{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}</span>
                <div class="min-w-0 flex-grow-1">
                    <h2 class="fs-4 mb-1">{{ $employee->full_name }}</h2>
                    <div class="d-flex align-items-center gap-2 flex-wrap text-body-secondary">
                        <span class="ems-chip tabular-nums">{{ $employee->employee_no }}</span>
                        <span>{{ $employee->jobTitle->title }}</span>
                        <span class="status-pill">{{ ucfirst($employee->status->value) }}</span>
                        @if ($onLeaveUntil) <span class="ems-chip">On leave until {{ \Illuminate\Support\Carbon::parse($onLeaveUntil)->format('M j, Y') }}</span> @endif
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="{{ route('employees.edit', $employee) }}">Edit</a>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-6 d-grid gap-4 align-content-start">
                <section class="card employee-form-card" aria-labelledby="personal-title">
                    <h2 class="fs-5" id="personal-title">Personal Information</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt><dd class="col-sm-7">{{ $employee->full_name }}</dd>
                        <dt class="col-sm-5">Sex</dt><dd class="col-sm-7">{{ $employee->sex?->value ?? '—' }}</dd>
                        <dt class="col-sm-5">Birthdate</dt><dd class="col-sm-7">{{ $employee->birthdate?->format('M j, Y') ?? '—' }}</dd>
                        <dt class="col-sm-5">Marital status</dt><dd class="col-sm-7">{{ $employee->marital_status?->value ?? '—' }}</dd>
                    </dl>
                </section>

                <section class="card employee-form-card" aria-labelledby="employment-title">
                    <h2 class="fs-5" id="employment-title">Employment Information</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Job title</dt><dd class="col-sm-7">{{ $employee->jobTitle->title }}</dd>
                        <dt class="col-sm-5">College</dt><dd class="col-sm-7">{{ $employee->college?->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Office</dt><dd class="col-sm-7">{{ $employee->office?->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Employment type</dt><dd class="col-sm-7">{{ $employee->employment_type->label() }}</dd>
                        <dt class="col-sm-5">Date hired</dt><dd class="col-sm-7">{{ $employee->hired_on?->format('M j, Y') ?? '—' }}</dd>
                        <dt class="col-sm-5">Official email</dt><dd class="col-sm-7">{{ $employee->official_email }}</dd>
                        <dt class="col-sm-5">Monthly pay (SG {{ $employee->jobTitle->salary_grade }})</dt>
                        <dd class="col-sm-7 tabular-nums">{{ $baseRate ? '₱'.number_format((float) $baseRate->monthly_amount, 2) : '—' }}</dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">
                            {{ ucfirst($employee->status->value) }}
                            @if ($employee->status_effective_on) since {{ $employee->status_effective_on->format('M j, Y') }} @endif
                            @if ($employee->status_reason) ({{ $employee->status_reason }}) @endif
                        </dd>
                        <dt class="col-sm-5">Login account</dt>
                        <dd class="col-sm-7">{{ $employee->user ? ($employee->user->is_active ? 'Active' : 'Deactivated') : 'None' }}</dd>
                    </dl>
                </section>

                <section class="card employee-form-card" aria-labelledby="contact-title">
                    <h2 class="fs-5" id="contact-title">Contact Information</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Mobile number</dt><dd class="col-sm-7">{{ $employee->phone ?? '—' }}</dd>
                        <dt class="col-sm-5">Personal email</dt><dd class="col-sm-7">{{ $employee->personal_email ?? '—' }}</dd>
                        <dt class="col-sm-5">Home address</dt><dd class="col-sm-7">{{ $employee->address ?? '—' }}</dd>
                    </dl>
                </section>

                <section class="card employee-form-card" aria-labelledby="ids-title">
                    <h2 class="fs-5" id="ids-title">Government IDs</h2>
                    <p class="text-body-secondary">Numbers are masked.</p>
                    <ul class="list-group list-group-flush">
                        @foreach ($idLabels as $key => $label)
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3 flex-wrap px-0">
                                <span><strong>{{ $label }}</strong> <span class="tabular-nums">{{ isset($masked[$key]) ? '•••• '.$masked[$key] : 'Not on file' }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="card employee-form-card" aria-labelledby="documents-title">
                    <h2 class="fs-5" id="documents-title">Documents</h2>
                    <ul @class(['list-group list-group-flush', 'mb-3' => ! $isArchived])>
                        @forelse ($employee->documents()->latest('id')->get() as $document)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <span><span class="fw-medium">{{ ucfirst($document->kind) }}</span> · {{ $document->original_name }} <small class="text-body-secondary">({{ $document->humanSize() }}, {{ $document->created_at->format('M j, Y') }})</small></span>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $document->uuid) }}" target="_blank" rel="noopener">Open</a>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No documents yet. The employee can add their own, or you can add one.</li>
                        @endforelse
                    </ul>
                    @unless ($isArchived)
                        <form method="post" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data" class="row g-3" novalidate>
                            @csrf
                            <div class="col-sm-5"><x-select-field name="kind" label="Type" :options="\App\Http\Controllers\EmployeePortalController::DOCUMENT_KINDS" required /></div>
                            <div class="col-sm-7">
                                <label for="document-file" class="form-label is-required">File</label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror" id="document-file" name="file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                                @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-sm btn-outline-primary">Add Document</button></div>
                        </form>
                    @endunless
                </section>
            </div>

            <div class="col-xl-6 d-grid gap-4 align-content-start">
                <section class="card employee-form-card" aria-labelledby="emergency-title">
                    <h2 class="fs-5" id="emergency-title">Emergency Contacts</h2>
                    <ul class="list-group list-group-flush mb-3">
                        @forelse ($employee->emergencyContacts as $contact)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-3 px-0">
                                <div>
                                    <div class="fw-medium">{{ $contact->name }} @if ($contact->is_primary) <span class="ems-chip">Primary</span> @endif</div>
                                    <small class="text-body-secondary">{{ $contact->relationship }} · {{ $contact->phone }}@if ($contact->address) · {{ $contact->address }} @endif</small>
                                </div>
                                <form method="post" action="{{ route('emergency-contacts.destroy', [$employee, $contact]) }}" data-confirm-title="Remove emergency contact"
                                    data-confirm="Remove {{ $contact->name }} from the emergency contacts? This cannot be undone." data-confirm-button="Remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Remove {{ $contact->name }}">Remove</button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No emergency contacts on file.</li>
                        @endforelse
                    </ul>
                    <form method="post" action="{{ route('emergency-contacts.store', $employee) }}" class="row g-3" novalidate>
                        @csrf
                        <div class="col-md-6"><x-field name="name" label="Name" required /></div>
                        <div class="col-md-6"><x-field name="relationship" label="Relationship" required /></div>
                        <div class="col-md-6"><x-field name="phone" type="tel" label="Mobile number" placeholder="09XXXXXXXXX" required /></div>
                        <div class="col-md-6"><x-field name="address" label="Address" /></div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="contact-primary" name="is_primary" value="1">
                                <label class="form-check-label" for="contact-primary">Primary contact</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add Contact</button>
                        </div>
                    </form>
                </section>

                <section class="card employee-form-card" aria-labelledby="leave-title">
                    <h2 class="fs-5" id="leave-title">Leave for {{ now()->year }}</h2>
                    @if ($leaveBalances === [])
                        <p class="text-body-secondary mb-3">This employee earns no leave credits (part-time staff do not).</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-3">
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
                                    @foreach ($leaveBalances as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            @foreach (['entitled', 'used', 'pending', 'remaining'] as $column)
                                                <td class="text-end tabular-nums">{{ rtrim(rtrim(number_format($row[$column], 1), '0'), '.') }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <h3 class="fs-6">Recent requests</h3>
                    <ul class="list-group list-group-flush">
                        @forelse ($recentLeave as $leaveRequest)
                            <li class="list-group-item px-0">
                                <span class="fw-medium">{{ $leaveRequest->leave_type->label() }}</span>
                                <span class="status-pill">{{ $leaveRequest->status->label() }}</span>
                                <small class="text-body-secondary d-block">
                                    {{ $leaveRequest->start_date->format('M j, Y') }}
                                    @if (! $leaveRequest->end_date->isSameDay($leaveRequest->start_date)) to {{ $leaveRequest->end_date->format('M j, Y') }} @endif
                                    · {{ rtrim(rtrim(number_format((float) $leaveRequest->days, 1), '0'), '.') }} day(s)
                                </small>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No leave requests yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="card employee-form-card" aria-labelledby="contract-title">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <h2 class="fs-5 mb-0" id="contract-title">Contract and Agreements</h2>
                        @if ($isHead && ! $isArchived)
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-contract" aria-expanded="false" aria-controls="edit-contract">Edit Contract</button>
                        @endif
                    </div>

                    <p class="mb-3">
                        <strong>{{ $employee->jobTitle->title }}</strong> · {{ $employee->employment_type->label() }}
                        @if ($employee->employment_type === \App\Enums\EmploymentType::PartTime) · {{ $employee->weekly_hours }} hours a week @endif
                        @if ($employee->contract_ends_on)
                            · contract ends <strong>{{ $employee->contract_ends_on->format('M j, Y') }}</strong>
                            @if ($employee->contract_ends_on->isPast()) <span class="badge text-bg-danger">Ended</span> @elseif ($employee->contractEndsWithin(30)) <span class="badge text-bg-warning">Ending soon</span> @endif
                        @elseif ($employee->employment_type === \App\Enums\EmploymentType::FullTime)
                            · no end of contract
                        @endif
                    </p>

                    @if ($pending)
                        <div class="alert alert-info" role="status">A transfer to {{ $pending->toJobTitle->title }} takes effect on {{ $pending->effective_date->format('M j, Y') }}.</div>
                    @endif

                    @if ($isHead && ! $isArchived)
                        <div class="collapse" id="edit-contract">
                            <div class="border rounded p-3 mb-3">
                                <h3 class="fs-6">Edit the contract</h3>
                                <p class="small text-body-secondary">Nothing changes when you save. The employee gets an agreement to accept by signing; if they decline, they stay as they are.</p>
                                <form method="post" action="{{ route('employees.transfer', $employee) }}" class="row g-3" novalidate>
                                    @csrf
                                    <div class="col-12"><x-select-field name="job_title_id" label="Job title" :options="$jobTitleOptions" :value="$employee->job_title_id" grouped required /></div>
                                    <div class="col-md-6"><x-select-field name="extra_college_id" label="Also teaches in" :options="$colleges" placeholder="None" /></div>
                                    <div class="col-md-6"><x-select-field name="extra_office_id" label="Also assigned to office" :options="$offices" placeholder="None" /></div>
                                    <div class="col-md-6" data-employment-type><x-select-field name="employment_type" label="Employment type" :options="['full_time' => 'Full-time', 'part_time' => 'Part-time']" :value="$employee->employment_type->value" required /></div>
                                    <div class="col-md-6"><x-field name="effective_date" type="date" label="Effective date" :value="now()->toDateString()" required /></div>
                                    <div class="col-12" data-part-time-fields>
                                        <div class="row g-3">
                                            <div class="col-sm-4"><x-field name="weekly_hours" type="number" min="1" max="39" label="Weekly hours" :value="$employee->weekly_hours < 40 ? $employee->weekly_hours : 20" /></div>
                                            <div class="col-sm-4"><x-select-field name="contract_term" label="Contract ends with" :options="$contractTerms" placeholder="Choose" /></div>
                                            <div class="col-sm-4"><x-select-field name="academic_year" label="Academic year" :options="$academicYears" placeholder="Choose" /></div>
                                        </div>
                                        <div class="form-text">The contract ends on the last day of the term you choose.</div>
                                    </div>
                                    <div class="col-12"><x-field name="reason" label="Reason (optional)" /></div>
                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        @if ($employee->contract_ends_on)
                                            <button type="submit" class="btn btn-sm btn-outline-primary" formaction="{{ route('employees.renew', $employee) }}">Send as New Contract</button>
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-primary">Send Agreement</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <h3 class="fs-6">Agreements</h3>
                    <ul class="list-group list-group-flush mb-3">
                        @forelse ($offers as $offer)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <span>
                                    <span class="fw-medium">{{ $offer->kind->label() }}: {{ $offer->summary['position'] }}</span>
                                    <small class="d-block text-body-secondary">{{ $offer->employment_type->label() }} · from {{ $offer->start_date->format('M j, Y') }}{{ $offer->end_date ? ' to '.$offer->end_date->format('M j, Y') : '' }}@if ($offer->signed_name) · signed by {{ $offer->signed_name }} @endif</small>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="status-pill">{{ $offer->status->label() }}</span>
                                    @if ($isHead && $offer->isPending())
                                        <form method="post" action="{{ route('employees.offers.withdraw', [$employee, $offer]) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary">Withdraw</button></form>
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No agreements yet.</li>
                        @endforelse
                    </ul>

                    <h3 class="fs-6">Transfer History</h3>
                    <ul class="list-group list-group-flush mb-0">
                        @forelse ($transfers as $transfer)
                            <li class="list-group-item px-0">
                                <div class="fw-medium">{{ $transfer->fromJobTitle->title }} → {{ $transfer->toJobTitle->title }}</div>
                                <small class="text-body-secondary">
                                    {{ $transfer->effective_date->format('M j, Y') }}
                                    · {{ $transfer->applied_at ? 'Applied' : 'Waiting' }}
                                    @if ($transfer->reason) · {{ $transfer->reason }} @endif
                                </small>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No transfers yet.</li>
                        @endforelse
                    </ul>
                </section>

                @if ($isHead)
                    <section class="card employee-form-card" aria-labelledby="archive-title">
                        <h2 class="fs-5" id="archive-title">{{ $isArchived ? 'Restore' : 'Archive' }}</h2>
                        @if ($isArchived)
                            <p class="text-body-secondary">Restoring puts this employee back on the Employees list. Their login stays off until you turn it on in Accounts.</p>
                            <form method="post" action="{{ route('employees.status', $employee) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="btn btn-outline-primary">Restore Employee</button>
                            </form>
                        @else
                            <p class="text-body-secondary">Archiving removes the employee from the list and turns off their login. Nothing is deleted.</p>
                            <form method="post" action="{{ route('employees.status', $employee) }}" class="row g-3" novalidate data-confirm-title="Archive employee"
                                data-confirm="Archive {{ $employee->full_name }}? They leave the Employees list and can no longer sign in. You can restore the record later." data-confirm-button="Archive Employee">
                                @csrf
                                @method('PUT')
                                <div class="col-md-4"><x-select-field name="status" label="Status" :options="$archiveStatuses" required /></div>
                                <div class="col-md-4"><x-field name="effective_on" type="date" label="Effective" :value="now()->toDateString()" /></div>
                                <div class="col-md-4"><x-field name="reason" label="Reason (optional)" /></div>
                                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-sm btn-outline-danger">Archive Employee</button></div>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="card employee-form-card" aria-labelledby="activity-title">
                    <h2 class="fs-5" id="activity-title">Recent Activity</h2>
                    <ul class="list-group list-group-flush">
                        @forelse ($activity as $entry)
                            <li class="list-group-item px-0">
                                <span class="fw-medium">{{ ucfirst(str_replace('_', ' ', $entry->action->value)) }}</span>
                                <small class="text-body-secondary d-block">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('M j, Y g:i A') }}</small>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No activity recorded yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>
@endsection
