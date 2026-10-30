@extends('layouts.public')

@section('title', 'PLP Careers · '.config('app.name'))

@php
    $steps = [
        ['title' => 'Create an account', 'about' => 'Accept the Terms and Conditions, then set up your applicant account.'],
        ['title' => 'Apply for a position', 'about' => 'Fill in your personal and contact details once, then attach your resume to each application.'],
        ['title' => 'Follow your application', 'about' => 'See your status and the demo interview HR sets for you, and answer your offer online.'],
    ];
@endphp

{{--
    PLP Careers, the public careers website: anyone can browse without an account. The positions come from the database (published
    job titles with openings). The Employee Management System itself opens on the sign-in page; this site is linked from it.
--}}
@section('content')
    <div class="d-grid gap-5">
        <section class="careers-hero" aria-labelledby="careers-hero-title">
            <p class="careers-hero-eyebrow">Pamantasan ng Lungsod ng Pasig</p>
            <h1 class="careers-hero-title" id="careers-hero-title">Build Your Career with PLP</h1>
            <p class="careers-hero-lead">
                Explore teaching and non-teaching opportunities at Pamantasan ng Lungsod ng Pasig. See what each role involves and apply online.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light btn-lg" href="#open-positions">Explore Open Positions</a>
                @guest
                    <a class="btn btn-outline-light btn-lg" href="{{ route('applicant.register') }}" data-terms-gate>Create Account</a>
                @endguest
            </div>
            <dl class="careers-hero-stats" aria-label="Hiring right now">
                <div><dt>Open positions</dt><dd class="tabular-nums">{{ $stats['positions'] }}</dd></div>
                <div><dt>Colleges hiring</dt><dd class="tabular-nums">{{ $stats['colleges'] }}</dd></div>
                <div><dt>Openings in all</dt><dd class="tabular-nums">{{ $stats['openings'] }}</dd></div>
            </dl>
        </section>

        <section class="careers-section" id="featured" aria-labelledby="careers-featured-title">
            <div class="careers-section-header">
                <h2 class="careers-section-title" id="careers-featured-title">Featured Job Openings</h2>
                <p class="text-body-secondary mb-0">The open positions with the most openings right now.</p>
            </div>
            @if ($featured->isEmpty())
                <p class="text-body-secondary mb-0">No positions are open right now. Please check back later.</p>
            @else
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                    @foreach ($featured as $position)
                        @include('careers._card', ['position' => $position])
                    @endforeach
                </div>
            @endif
        </section>

        <section class="careers-section" id="open-positions" aria-label="Open Positions">
            @include('careers._list', ['title' => 'Open Positions', 'subtitle' => 'Every position currently accepting applications.'])
        </section>

        <section class="careers-section" id="about" aria-labelledby="careers-about-title">
            <div class="careers-section-header">
                <h2 class="careers-section-title" id="careers-about-title">About PLP Careers</h2>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <p>
                        Pamantasan ng Lungsod ng Pasig (PLP) is the local university of the City of Pasig. PLP Careers is where the
                        university lists its open teaching and non-teaching positions.
                    </p>
                    <p class="mb-0">
                        Every listing shows the college or office, employment type, location, and base pay, with the responsibilities,
                        qualifications, and requirements. Benefits are discussed with you at the interview and written in your offer.
                    </p>
                </div>

                <div class="col-lg-7">
                    <h3 class="fs-5 mb-3" id="careers-steps-title">How applying works</h3>
                    <ol class="row row-cols-1 row-cols-md-3 g-3 list-unstyled mb-0" aria-labelledby="careers-steps-title">
                        @foreach ($steps as $step)
                            <li class="col">
                                <div class="quick-link">
                                    <span class="quick-link-label">{{ $loop->iteration }}. {{ $step['title'] }}</span>
                                    <small>{{ $step['about'] }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>
    </div>
@endsection
