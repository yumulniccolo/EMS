@extends('layouts.employee')

@section('title', 'My Profile · '.config('app.name'))

@php
    $sections = [
        'employment' => 'Employment Information',
        'government' => 'Government IDs and Tax IDs',
        'contact' => 'Contact Details',
        'emergency' => 'Emergency Contacts',
        'security' => 'Security',
    ];
@endphp

{{--
    The employee's own record. A summary sits on the page; each part of the record opens in its own window from the buttons
    under it, like the account window on Accounts. The forms work as before; after one is sent, the window it was in opens again
    on the returned page so its message or errors show there, and #security in the address opens Security (resources/js/profile-sections.js).
--}}
@section('content')
    <div class="d-grid gap-4" data-profile-sections>
        <div>
            <h1 class="page-title">My Profile</h1>
            <p class="text-body-secondary mt-1 mb-0">Your record as HR holds it. You can update your contact details and emergency contacts.</p>
        </div>

        <section class="card employee-form-card" aria-labelledby="profile-name">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="profile-avatar" aria-hidden="true">{{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}</span>
                <div class="min-w-0 flex-grow-1">
                    <h2 class="fs-4 mb-1" id="profile-name">{{ $employee->full_name }}</h2>
                    <div class="d-flex align-items-center gap-2 flex-wrap text-body-secondary">
                        <span class="ems-chip tabular-nums">{{ $employee->employee_no }}</span>
                        <span>{{ $employee->jobTitle->title }}</span>
                    </div>
                </div>
            </div>

            <nav class="d-flex flex-wrap gap-2 border-top pt-3 mt-4" aria-label="Profile sections">
                @foreach ($sections as $key => $label)
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#profile-{{ $key }}-modal">{{ $label }}</button>
                @endforeach
            </nav>
        </section>
    </div>

    <div class="modal fade" id="profile-employment-modal" tabindex="-1" aria-labelledby="profile-employment-title" aria-hidden="true" data-profile-section="employment">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-employment-title">Employment Information</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $employee->full_name }}</dd>
                        <dt class="col-sm-4">Employee ID</dt><dd class="col-sm-8">{{ $employee->employee_no }}</dd>
                        <dt class="col-sm-4">Job title</dt><dd class="col-sm-8">{{ $employee->jobTitle->title }}</dd>
                        <dt class="col-sm-4">College or office</dt><dd class="col-sm-8">{{ collect([$employee->college?->name, $employee->office?->name])->filter()->implode(' and ') }}</dd>
                        <dt class="col-sm-4">Employment type</dt><dd class="col-sm-8">{{ $employee->employment_type->label() }}</dd>
                        <dt class="col-sm-4">Hired on</dt><dd class="col-sm-8">{{ $employee->hired_on?->format('F j, Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Contract ends</dt><dd class="col-sm-8">{{ $employee->contract_ends_on?->format('F j, Y') ?? 'No end date' }}</dd>
                        <dt class="col-sm-4">Official email</dt><dd class="col-sm-8 mb-0">{{ $employee->official_email }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profile-government-modal" tabindex="-1" aria-labelledby="profile-government-title" aria-hidden="true" data-profile-section="government">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-government-title">Government IDs and Tax IDs</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary">For your privacy only the last four digits show.</p>
                    <dl class="row mb-0">
                        @forelse ($maskedIds as $key => $last4)
                            <dt class="col-sm-4">{{ strtoupper($key === 'pagibig' ? 'Pag-IBIG' : $key) }}</dt><dd class="col-sm-8">•••• {{ $last4 }}</dd>
                        @empty
                            <dd class="col-12 text-body-secondary mb-0">None on record.</dd>
                        @endforelse
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profile-contact-modal" tabindex="-1" aria-labelledby="profile-contact-title" aria-hidden="true" data-profile-section="contact">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-contact-title">Contact Details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{ route('employee.profile.update') }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-sm-6"><x-field name="phone" label="Mobile number" :value="$employee->phone" placeholder="09XXXXXXXXX" /></div>
                            <div class="col-sm-6"><x-field name="personal_email" type="email" label="Personal email" :value="$employee->personal_email" /></div>
                            <div class="col-12"><x-field name="address" label="Address" :value="$employee->address" /></div>
                            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Save</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profile-emergency-modal" tabindex="-1" aria-labelledby="profile-emergency-title" aria-hidden="true" data-profile-section="emergency">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-emergency-title">Emergency Contacts</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-grid gap-4">
                    <ul class="list-group list-group-flush mb-0">
                        @forelse ($employee->emergencyContacts as $contact)
                            <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center gap-2">
                                <span>{{ $contact->name }} ({{ $contact->relationship }}) · {{ $contact->phone }} @if ($contact->is_primary) <span class="ems-chip">Primary</span> @endif</span>
                                <form method="post" action="{{ route('employee.emergency-contacts.destroy', $contact) }}" data-confirm-title="Remove emergency contact"
                                    data-confirm="Remove {{ $contact->name }} from your emergency contacts? This cannot be undone." data-confirm-button="Remove">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Remove</button></form>
                            </li>
                        @empty
                            <li class="list-group-item bg-transparent px-0 text-body-secondary">No emergency contacts on file.</li>
                        @endforelse
                    </ul>
                    <form method="post" action="{{ route('employee.emergency-contacts.store') }}" class="row g-3" novalidate>
                        @csrf
                        <div class="col-sm-6"><x-field name="name" label="Name" required /></div>
                        <div class="col-sm-6"><x-field name="relationship" label="Relationship" required /></div>
                        <div class="col-sm-6"><x-field name="phone" label="Mobile number" required /></div>
                        <div class="col-sm-6"><x-field name="address" label="Address" /></div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Add Contact</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profile-security-modal" tabindex="-1" aria-labelledby="profile-security-title" aria-hidden="true" data-profile-section="security">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-security-title">Security</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <a href="{{ route('password.change') }}">Change my password</a>
                </div>
            </div>
        </div>
    </div>
@endsection
