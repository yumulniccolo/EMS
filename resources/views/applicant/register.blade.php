@extends('layouts.guest')

@section('title', 'Create an Applicant Account · '.config('app.name'))

{{--
    Create Account for new applicants, reached from the public Careers website. The Terms and Conditions with the Privacy
    Notice are accepted in a window first (components/applicant-terms-modal.blade.php): on the Careers page before coming here,
    or here when this page is opened directly. Name, surname, and middle name are separate boxes. The account is
    created on the server; the applicant is then asked for their one-time profile.
--}}
@php($termsAccepted = request('terms') === 'accepted' || old('terms'))

@section('content')
    <div class="row g-0 min-vh-100">
        <x-login-brand tagline="Create an account to apply for open positions." />

        <section class="col-md-6 d-flex align-items-center justify-content-center px-3 py-5">
            <form class="login-form w-100 d-grid gap-3" method="post" action="{{ route('applicant.register.store') }}" novalidate>
                @csrf
                @if ($position) <input type="hidden" name="position" value="{{ $position->id }}"> @endif
                <input type="hidden" name="terms" value="{{ $termsAccepted ? '1' : '' }}" data-terms-input>

                <div>
                    <h2 class="login-form-title mb-1">Create an Applicant Account</h2>
                    <p class="text-body-secondary mb-0">You will use it to apply and to follow your applications.</p>
                </div>

                @if ($position)
                    <div class="alert alert-secondary mb-0" role="status">
                        You are applying for <strong>{{ $position->title }}</strong>. It stays selected while you continue.
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger mb-0" role="alert">
                        <ul class="mb-0 ps-3">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-sm-6"><x-field name="first_name" label="First Name" required autocomplete="given-name" /></div>
                    <div class="col-sm-6"><x-field name="middle_name" label="Middle Name" autocomplete="additional-name" /></div>
                    <div class="col-sm-8"><x-field name="last_name" label="Surname" required autocomplete="family-name" /></div>
                    <div class="col-sm-4"><x-field name="suffix" label="Suffix" placeholder="Jr., III" /></div>
                </div>

                <div><x-field name="email" type="email" label="Personal Email" required autocomplete="email" placeholder="name@gmail.com" /></div>

                <div>
                    <x-password-field name="password" label="Password" required icon-only autocomplete="new-password"
                        hint="At least 8 characters, with an uppercase letter, a lowercase letter, and a number." />
                </div>
                <div><x-password-field name="password_confirmation" label="Confirm Password" required icon-only autocomplete="new-password" /></div>

                <button type="submit" class="btn btn-primary login-submit">Create Account</button>

                <div class="d-grid gap-2 border-top pt-3 text-center">
                    <span class="text-body-secondary">Already have an account? <a href="{{ route('login') }}">Login here</a></span>
                    <a class="small" href="{{ route('careers.index') }}">Back to Careers</a>
                </div>
            </form>
        </section>
    </div>

    <x-applicant-terms-modal :required="! $termsAccepted" :cancel-url="route('careers.index')" />
@endsection
