@extends('layouts.guest')

@section('title', 'Create an Applicant Account · '.config('app.name'))

{{--
    Create Account for new applicants, reached from the public Careers website. The Terms and Conditions with the Privacy
    Notice are read on this page and accepted with a checkbox. Name, surname, and middle name are separate boxes. The account is
    created on the server; the applicant is then asked for their one-time profile.
--}}
@section('content')
    <div class="row g-0 min-vh-100">
        <x-login-brand tagline="Create an account to apply for open positions." />

        <section class="col-md-6 d-flex align-items-center justify-content-center px-3 py-5">
            <form class="login-form w-100 d-grid gap-3" method="post" action="{{ route('applicant.register.store') }}" novalidate>
                @csrf
                @if ($position) <input type="hidden" name="position" value="{{ $position->id }}"> @endif

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

                <section class="border rounded p-3 overflow-auto" style="max-height: 14rem" aria-labelledby="terms-title" tabindex="0">
                    <h3 class="fs-6" id="terms-title">Terms and Conditions and Privacy Notice</h3>
                    <ol class="small">
                        <li>The Applicant Portal lets you create an account, apply for open positions, and follow your applications.</li>
                        <li>Give true, complete, and current information. False or misleading information may disqualify your application.</li>
                        <li>Keep your login details to yourself. You are responsible for what is done with your account.</li>
                        <li>Applying does not guarantee a demo interview or a job offer. HR decides how each application moves forward.</li>
                        <li>Interview schedules are set by HR and may change. Check your Calendar and Applications pages for the latest.</li>
                    </ol>
                    <p class="small mb-1">
                        We collect the personal information you give, such as your name, contact details, birthdate, and the documents you attach, to assess
                        your application, contact you, and schedule your interview. Only the HR staff who handle recruitment can view it. If you are hired,
                        the details are used to start your employee record. This follows the Data Privacy Act of 2012 (Republic Act No. 10173).
                    </p>
                </section>

                <div class="form-check">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" @checked(old('terms'))>
                    <label class="form-check-label" for="terms">I have read and agree to the Terms and Conditions and the Privacy Notice.</label>
                    @error('terms') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary login-submit">Create Account</button>

                <div class="d-grid gap-2 border-top pt-3 text-center">
                    <span class="text-body-secondary">Already have an account? <a href="{{ route('login') }}">Login here</a></span>
                    <a class="small" href="{{ route('careers.index') }}">Back to Careers</a>
                </div>
            </form>
        </section>
    </div>
@endsection
