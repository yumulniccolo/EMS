@extends('layouts.employee')

@section('title', 'My Profile · '.config('app.name'))

@section('content')
    <div class="d-grid gap-4" id="security">
        <div>
            <h1 class="page-title">My Profile</h1>
            <p class="text-body-secondary mt-1 mb-0">Your record as HR holds it. You can update your contact details and emergency contacts.</p>
        </div>

        @php($tabs = ['personal' => 'Personal Information', 'ids' => 'Government IDs', 'contact' => 'Contact Details', 'emergency' => 'Emergency Contacts', 'security' => 'Security'])
        <ul class="nav nav-underline profile-tabs flex-nowrap overflow-x-auto" role="tablist" data-remember-tab="my-profile">
            @foreach ($tabs as $key => $label)
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link text-nowrap{{ $loop->first ? ' active' : '' }}" id="profile-{{ $key }}-tab"
                        data-bs-toggle="tab" data-bs-target="#profile-{{ $key }}" role="tab"
                        aria-controls="profile-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active d-grid gap-4" id="profile-personal" role="tabpanel" aria-labelledby="profile-personal-tab" tabindex="0">
        <section class="card employee-form-card" aria-labelledby="work-title">
            <h2 class="employee-form-section" id="work-title">Employment</h2>
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $employee->full_name }}</dd>
                <dt class="col-sm-3">Employee ID</dt><dd class="col-sm-9">{{ $employee->employee_no }}</dd>
                <dt class="col-sm-3">Job title</dt><dd class="col-sm-9">{{ $employee->jobTitle->title }}</dd>
                <dt class="col-sm-3">College or office</dt><dd class="col-sm-9">{{ collect([$employee->college?->name, $employee->office?->name])->filter()->implode(' and ') }}</dd>
                <dt class="col-sm-3">Employment type</dt><dd class="col-sm-9">{{ $employee->employment_type->label() }}</dd>
                <dt class="col-sm-3">Hired on</dt><dd class="col-sm-9">{{ $employee->hired_on?->format('F j, Y') ?? '—' }}</dd>
                <dt class="col-sm-3">Contract ends</dt><dd class="col-sm-9">{{ $employee->contract_ends_on?->format('F j, Y') ?? 'No end date' }}</dd>
                <dt class="col-sm-3">Official email</dt><dd class="col-sm-9">{{ $employee->official_email }}</dd>
            </dl>
        </section>
            </div>

            <div class="tab-pane fade d-grid gap-4" id="profile-ids" role="tabpanel" aria-labelledby="profile-ids-tab" tabindex="0">
        <section class="card employee-form-card" aria-labelledby="gov-title">
            <h2 class="employee-form-section" id="gov-title">Government and tax IDs</h2>
            <p class="small text-body-secondary">For your privacy only the last four digits show.</p>
            <dl class="row mb-0">
                @forelse ($maskedIds as $key => $last4)
                    <dt class="col-sm-3">{{ strtoupper($key === 'pagibig' ? 'Pag-IBIG' : $key) }}</dt><dd class="col-sm-9">•••• {{ $last4 }}</dd>
                @empty
                    <dd class="col-12 text-body-secondary">None on record.</dd>
                @endforelse
            </dl>
        </section>
            </div>

            <div class="tab-pane fade d-grid gap-4" id="profile-contact" role="tabpanel" aria-labelledby="profile-contact-tab" tabindex="0">
        <form method="post" action="{{ route('employee.profile.update') }}" class="card employee-form-card" novalidate>
            @csrf
            @method('PUT')
            <h2 class="employee-form-section">Contact details</h2>
            <div class="row g-3">
                <div class="col-md-4"><x-field name="phone" label="Mobile number" :value="$employee->phone" placeholder="09XXXXXXXXX" /></div>
                <div class="col-md-4"><x-field name="personal_email" type="email" label="Personal email" :value="$employee->personal_email" /></div>
                <div class="col-md-12"><x-field name="address" label="Address" :value="$employee->address" /></div>
                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary">Save</button></div>
            </div>
        </form>
            </div>

            <div class="tab-pane fade d-grid gap-4" id="profile-emergency" role="tabpanel" aria-labelledby="profile-emergency-tab" tabindex="0">
        <section class="card employee-form-card" aria-labelledby="ec-title">
            <h2 class="employee-form-section" id="ec-title">Emergency contacts</h2>
            <ul class="list-group list-group-flush mb-3">
                @foreach ($employee->emergencyContacts as $contact)
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                        <span>{{ $contact->name }} ({{ $contact->relationship }}) · {{ $contact->phone }} @if ($contact->is_primary) <span class="ems-chip">Primary</span> @endif</span>
                        <form method="post" action="{{ route('employee.emergency-contacts.destroy', $contact) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Remove</button></form>
                    </li>
                @endforeach
            </ul>
            <form method="post" action="{{ route('employee.emergency-contacts.store') }}" class="row g-2" novalidate>
                @csrf
                <div class="col-md-3"><x-field name="name" label="Name" required /></div>
                <div class="col-md-3"><x-field name="relationship" label="Relationship" required /></div>
                <div class="col-md-3"><x-field name="phone" label="Mobile number" required /></div>
                <div class="col-md-3"><x-field name="address" label="Address" /></div>
                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-outline-primary">Add Contact</button></div>
            </form>
        </section>
            </div>

            <div class="tab-pane fade d-grid gap-4" id="profile-security" role="tabpanel" aria-labelledby="profile-security-tab" tabindex="0">
        <section class="card employee-form-card" aria-labelledby="sec-title">
            <h2 class="employee-form-section" id="sec-title">Security</h2>
            <a href="{{ route('password.change') }}">Change my password</a>
        </section>
            </div>
        </div>
    </div>
@endsection
