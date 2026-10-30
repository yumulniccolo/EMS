<!DOCTYPE html>
@php($isLightByDefault = request()->routeIs('careers.*'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if ($isLightByDefault) data-bs-theme="light" data-theme-scope="public" @else data-bs-theme="dark" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        @vite(['resources/scss/app.scss', 'resources/js/app.js'])

        @stack('styles')
    </head>
    <body class="public-page">
        @php($onCareers = request()->routeIs('careers.index'))

        {{--
            The public PLP Careers website, open to anyone without an account. The navigation bar offers Home, Open
            Positions, and About, the shared Login page, and Create Account (which asks for the Terms and Conditions first),
            or the dashboard when an applicant is already signed in this session (resources/js/applicant-positions.js).
            On narrow screens the links fold into a menu.
        --}}
        <header class="public-header" data-bs-theme="dark">
            <nav class="navbar navbar-expand-lg" aria-label="PLP Careers">
                <div class="container-xl">
                    <a class="navbar-brand app-brand" href="{{ route('careers.index') }}">
                        <span class="app-brand-logo"><x-brand-logo /></span>
                        <span class="d-flex flex-column lh-sm">
                            <span class="public-brand-name">PLP Careers</span>
                            <small class="public-brand-tagline">Pamantasan ng Lungsod ng Pasig</small>
                        </span>
                    </a>

                    <div class="d-flex align-items-center gap-2 order-lg-last">
                        <button type="button" class="app-icon-btn" data-theme-toggle>
                            <x-icon name="sun" class="theme-when-dark" />
                            <x-icon name="moon" class="theme-when-light" />
                            <span class="visually-hidden theme-when-dark">Switch to light mode</span>
                            <span class="visually-hidden theme-when-light">Switch to dark mode</span>
                        </button>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#public-nav"
                            aria-controls="public-nav" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                    </div>

                    <div class="collapse navbar-collapse" id="public-nav">
                        <ul class="navbar-nav me-auto ms-lg-4 mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link{{ $onCareers ? ' active' : '' }}" aria-current="{{ $onCareers ? 'page' : 'false' }}"
                                    href="{{ route('careers.index') }}" data-public-nav="home">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('careers.index') }}#open-positions" data-public-nav="positions">Open Positions</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('careers.index') }}#about" data-public-nav="about">About</a>
                            </li>
                        </ul>

                        @auth
                            <div class="pb-3 pb-lg-0 me-lg-2">
                                <a class="btn btn-sm btn-light" href="{{ auth()->user()->homeUrl() }}">My Dashboard</a>
                            </div>
                        @else
                            <div class="d-flex flex-wrap align-items-center gap-2 pb-3 pb-lg-0 me-lg-2">
                                <a class="btn btn-sm btn-outline-light" href="{{ route('login') }}">Login</a>
                                <a class="btn btn-sm btn-light" href="{{ route('applicant.register') }}" data-terms-gate>Create Account</a>
                            </div>
                        @endauth
                    </div>
                </div>
            </nav>
        </header>

        <main class="app-main">
            <div class="app-content">
                @yield('content')
            </div>
        </main>

        {{-- PLP branding, the contact details PLP publishes on its website, and the site's links. --}}
        <footer class="public-footer" data-bs-theme="dark" aria-labelledby="public-footer-title">
            <div class="container-xl py-5">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="app-brand mb-3">
                            <span class="app-brand-logo"><x-brand-logo /></span>
                            <span class="d-flex flex-column lh-sm">
                                <span class="public-brand-name" id="public-footer-title">PLP Careers</span>
                                <small class="public-brand-tagline">Pamantasan ng Lungsod ng Pasig</small>
                            </span>
                        </div>
                        <p class="public-footer-muted mb-0">
                            Teaching and non-teaching opportunities at Pamantasan ng Lungsod ng Pasig, open for online application.
                        </p>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <h2 class="public-footer-heading">Contact</h2>
                        <ul class="list-unstyled d-grid gap-2 mb-2">
                            <li>12-B Alcalde Jose, Pasig, 1600 Metro Manila</li>
                            <li><a href="tel:+63286431014">2-8643-1014</a></li>
                            <li><a href="mailto:inquiry@plpasig.edu.ph">inquiry@plpasig.edu.ph</a></li>
                            <li><a href="https://plpasig.edu.ph" target="_blank" rel="noopener">plpasig.edu.ph</a></li>
                        </ul>
                        <p class="small public-footer-muted mb-0">Contact details as published on the official PLP website.</p>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <h2 class="public-footer-heading">Explore</h2>
                        <ul class="list-unstyled d-grid gap-2 mb-0">
                            <li><a href="{{ route('careers.index') }}">Home</a></li>
                            <li><a href="{{ route('careers.index') }}#open-positions">Open Positions</a></li>
                            <li><a href="{{ route('careers.index') }}#about">About PLP Careers</a></li>
                            <li><a href="{{ route('login') }}">Login</a></li>
                            <li><a href="{{ route('applicant.register') }}" data-terms-gate>Create Account</a></li>
                        </ul>
                    </div>
                </div>

                <p class="small public-footer-muted border-top border-secondary-subtle pt-3 mt-4 mb-0">
                    PLP Careers is part of the PLP Employee Management System.
                </p>
            </div>
        </footer>

        {{-- Create Account and Apply Now (for a visitor) open the Terms and Conditions first. --}}
        <x-applicant-terms-modal />

        @stack('scripts')
    </body>
</html>
