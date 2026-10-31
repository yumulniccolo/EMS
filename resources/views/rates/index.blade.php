@extends('layouts.admin')

@section('title', 'Pay Rates · '.config('app.name'))

{{--
    Reference monthly pay of each salary grade (Step 1 of the government schedule), as in force today. Adding a rate never
    changes an old one: it adds a new rate from an effective date, and the history stays below. Nothing here computes pay.
--}}
@section('content')
    <div class="d-grid gap-4" data-page="rates">
        <div class="d-flex align-items-center gap-3">
            <a class="page-back" href="{{ route('job-titles.index') }}" aria-label="Back to job titles"><x-icon name="back" /></a>
            <div>
                <h1 class="page-title">Pay Rates</h1>
                <p class="text-body-secondary mt-1 mb-0">Monthly pay of each salary grade, as in force today</p>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            <th scope="col">Salary grade</th>
                            <th scope="col">Job titles</th>
                            <th scope="col" class="text-end">Monthly pay</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedule as $salaryGrade => $amount)
                            <tr @class(['table-active' => $salaryGrade === $grade])>
                                <td class="tabular-nums fw-medium">SG {{ $salaryGrade }}</td>
                                <td>{{ ($titlesByGrade[$salaryGrade] ?? collect())->pluck('title')->unique()->take(3)->implode(', ') ?: '—' }}</td>
                                <td class="text-end tabular-nums">₱{{ number_format((float) $amount, 2) }}</td>
                                <td class="text-end">
                                    @can('manage-reference-data')
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('rates.index', ['grade' => $salaryGrade]) }}#new-rates">Change</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($grade)
            <section class="card employee-form-card" id="new-rates" aria-labelledby="new-rates-title">
                <h2 class="fs-5" id="new-rates-title">SG {{ $grade }}: new rate</h2>
                <p class="text-body-secondary">
                    Enter the monthly pay from the date it takes effect. The old rates are kept, so earlier dates still use them.
                </p>

                @can('manage-reference-data')
                    <form method="post" action="{{ route('rates.store') }}" class="row g-3" novalidate>
                        @csrf
                        <input type="hidden" name="salary_grade" value="{{ $grade }}">

                        <div class="col-md-4"><x-field name="effective_date" type="date" label="Effective date" required /></div>
                        <div class="col-md-4">
                            <x-field name="monthly_amount" type="number" step="0.01" min="0" label="Monthly pay (₱)" :value="$schedule[$grade]" required />
                        </div>
                        <div class="col-md-4 align-self-end"><button type="submit" class="btn btn-primary w-100">Save New Rate</button></div>
                    </form>
                @endcan

                <h3 class="fs-6 mt-4">History</h3>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Effective from</th>
                                <th scope="col" class="text-end">Monthly pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $tier)
                                <tr>
                                    <td class="text-nowrap">{{ $tier->effective_date->format('M j, Y') }}</td>
                                    <td class="text-end tabular-nums">₱{{ number_format((float) $tier->monthly_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
