@extends('layouts.admin')

@section('title', 'Accounts · '.config('app.name'))

{{--
    HR Admin account administration: issue a login to an employee (with a one-time temporary password), reset a password,
    deactivate or reactivate an account, and assign roles. Clicking an account opens a window with those actions. Accounts are
    deactivated, never deleted, so history stays.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="accounts">
        <div>
            <h1 class="page-title">Accounts</h1>
            <p class="text-body-secondary mt-1 mb-0">Logins for HR and staff. Applicants register themselves on the Careers website.</p>
        </div>

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-6"><label for="account-search" class="visually-hidden">Search accounts</label><div class="search-field"><x-icon name="search" /><input type="search" class="form-control" id="account-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name or email..." autocomplete="off"></div></div>
                <div class="col-md-3"><label for="account-role" class="visually-hidden">Role</label><select class="form-select" id="account-role" name="role"><option value="">All roles</option>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ ucfirst(str_replace('_', ' ', $role->value)) }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="account-status" class="visually-hidden">Status</label><select class="form-select" id="account-status" name="status"><option value="">Active and inactive</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></div>
            </div>
        </form>

        <section class="card employee-form-card" aria-labelledby="create-account-title">
            <h2 class="fs-5" id="create-account-title">Create an account</h2>

            @if ($employeesWithoutAccount->isEmpty())
                <p class="text-body-secondary mb-0">Every employee already has an account.</p>
            @else
                <form method="post" action="{{ route('accounts.store') }}" class="row g-3 align-items-end">
                    @csrf

                    <div class="col-md-6">
                        <label for="account-employee" class="form-label">Employee</label>
                        <select class="form-select" id="account-employee" name="employee_id" required data-native-select data-combobox>
                            <option value="">Select employee</option>
                            @foreach ($employeesWithoutAccount as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->last_name }}, {{ $employee->first_name }} ({{ $employee->employee_no }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="account-role" class="form-label">Role</label>
                        <select class="form-select" id="account-role" name="role" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected($role === \App\Enums\Role::Employee)>{{ str($role->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Create Account</button>
                    </div>
                </form>
            @endif
        </section>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Two-factor</th>
                            <th scope="col">Last sign-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr class="employee-row" data-bs-toggle="modal" data-bs-target="#account-{{ $account->id }}">
                                <td>
                                    <button type="button" class="btn btn-link p-0 text-reset text-decoration-none fw-medium" data-bs-toggle="modal" data-bs-target="#account-{{ $account->id }}">{{ $account->name }}</button>
                                </td>
                                <td>{{ $account->email }}</td>
                                <td>
                                    {{ str($account->role->value)->replace('_', ' ')->title() }}
                                    @if (auth()->user()->is($account)) (you) @endif
                                </td>
                                <td>
                                    <span class="ems-chip">{{ $account->is_active ? 'Active' : 'Deactivated' }}</span>
                                    @if ($account->must_change_password)
                                        <span class="ems-chip">Temporary password</span>
                                    @endif
                                </td>
                                <td>{{ $account->two_factor_confirmed_at ? 'On' : 'Off' }}</td>
                                <td class="text-nowrap">{{ $account->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="employees-empty text-center"><strong>No accounts match</strong> Try a different search or clear the filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }} accounts</span>
                {{ $accounts->links() }}
            </div>
        </div>
    </div>

    {{-- One window per account on this page: its details, and the role, password, and access actions (none on your own account). --}}
    @foreach ($accounts as $account)
        <div class="modal fade" id="account-{{ $account->id }}" tabindex="-1" aria-labelledby="account-{{ $account->id }}-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="account-{{ $account->id }}-title">{{ $account->name }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-grid gap-4">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $account->email }}</dd>
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <span class="ems-chip">{{ $account->is_active ? 'Active' : 'Deactivated' }}</span>
                                @if ($account->must_change_password)
                                    <span class="ems-chip">Temporary password</span>
                                @endif
                            </dd>
                            <dt class="col-sm-4">Two-factor</dt><dd class="col-sm-8">{{ $account->two_factor_confirmed_at ? 'On' : 'Off' }}</dd>
                            <dt class="col-sm-4">Last sign-in</dt><dd class="col-sm-8 mb-0">{{ $account->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</dd>
                        </dl>

                        @if (auth()->user()->isNot($account))
                            <form method="post" action="{{ route('accounts.role', $account) }}">
                                @csrf
                                @method('PUT')
                                <label class="form-label" for="role-{{ $account->id }}">Role</label>
                                <div class="d-flex gap-2">
                                    <select class="form-select" id="role-{{ $account->id }}" name="role">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($account->role === $role)>{{ str($role->value)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-secondary">Save</button>
                                </div>
                            </form>

                            <div class="d-flex gap-2 flex-wrap">
                                <form method="post" action="{{ route('accounts.reset-password', $account) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">Reset Password</button>
                                </form>

                                @if ($account->is_active)
                                    <form method="post" action="{{ route('accounts.deactivate', $account) }}" data-confirm-title="Deactivate account"
                                        data-confirm="Deactivate {{ $account->name }}'s account? They can no longer sign in until it is reactivated." data-confirm-button="Deactivate">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger">Deactivate</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('accounts.reactivate', $account) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary">Reactivate</button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <p class="text-body-secondary mb-0">
                                Role: {{ str($account->role->value)->replace('_', ' ')->title() }}. This is your account, so its role and access cannot be changed here.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{--
        The one-time temporary password, from the redirect after an account is created or a password is reset (a flash, so it is
        on this one response only). The window opens by itself, the password is masked until the eye is pressed, and closing the
        window wipes it from the page (resources/js/issued-password.js).
    --}}
    @if (session('issued_password'))
        @php($issued = session('issued_password'))
        <div class="modal fade" id="issued-password-modal" tabindex="-1" aria-labelledby="issued-password-title" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false" data-issued-password>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="issued-password-title">One-time temporary password</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-grid gap-3">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $issued['name'] }}</dd>
                            <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><span class="user-select-all">{{ $issued['email'] }}</span></dd>
                            <dt class="col-sm-4">Expires</dt><dd class="col-sm-8 mb-0">{{ $issued['expires'] ?? '—' }}</dd>
                        </dl>

                        <div>
                            <label for="issued-password" class="form-label">Temporary password</label>
                            <div class="d-flex gap-2">
                                <div class="login-password position-relative flex-grow-1">
                                    <input type="password" class="form-control pe-5 font-monospace" id="issued-password" value="{{ $issued['password'] }}"
                                        readonly autocomplete="off" spellcheck="false" data-issued-password-input>
                                    <button type="button" class="login-password-toggle" aria-controls="issued-password" aria-pressed="false" data-password-toggle>
                                        <x-icon name="eye" />
                                        <span data-password-toggle-label class="visually-hidden">Show</span><span class="visually-hidden"> password</span>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 text-nowrap" data-issued-password-copy>
                                    <x-icon name="copy" />
                                    <span data-issued-password-copy-label>Copy Password</span>
                                </button>
                            </div>
                            <div class="form-text" role="status" aria-live="polite" data-issued-password-status></div>
                        </div>

                        <div class="alert alert-warning small mb-0">
                            Shown only once: after you close this window it cannot be viewed again. Hand it over in person or through an
                            approved channel. The person must choose their own password at first sign-in.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
