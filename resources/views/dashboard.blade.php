@extends('layouts.admin')

@section('title', 'Dashboard · '.config('app.name'))

{{-- Everything on this page is read from the database when it opens. --}}
@section('content')
    <div class="d-grid gap-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Welcome, {{ strtok(auth()->user()->name, ' ') }}</h1>
                <p class="text-body-secondary mt-1 mb-0">Here is where the workforce stands today.</p>
            </div>

            <a class="btn btn-primary d-inline-flex align-items-center gap-2" href="{{ route('employees.create') }}">
                <x-icon name="plus" />
                Add Employee
            </a>
        </div>

        @if ($endingContracts > 0 && $isAdmin)
            <div class="alert alert-warning mb-0" role="status">
                {{ $endingContracts }} {{ \Illuminate\Support\Str::plural('contract', $endingContracts) }} ending within 30 days or already ended.
                <a href="{{ route('contracts.index') }}">Decide on each</a>.
            </div>
        @endif

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
            @foreach ($kpis as $kpi)
                <div class="col">
                    <a class="card dashboard-kpi text-decoration-none text-reset" href="{{ $kpi['url'] }}">
                        <span class="dashboard-kpi-label">{{ $kpi['label'] }}</span>
                        <strong class="dashboard-kpi-value tabular-nums">{{ $kpi['value'] }}</strong>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card dashboard-panel">
                    <h2>Head Count by Colleges</h2>
                    <p class="dashboard-panel-subtitle">Active employees per college</p>

                    <div class="d-grid gap-2">
                        @foreach ($headcount as $college)
                            <div>
                                <div class="d-flex justify-content-between small"><span>{{ $college->name }}</span><strong class="tabular-nums">{{ $college->headcount }}</strong></div>
                                <div class="progress" data-progressbar data-value="{{ $college->headcount }}" data-max="{{ $headcountMax }}" aria-label="{{ $college->name }}: {{ $college->headcount }}"></div>
                            </div>
                        @endforeach
                    </div>
                    @if ($nonCollegeCount > 0)
                        <p class="small text-body-secondary mt-3 mb-0">{{ $nonCollegeCount }} non-teaching {{ \Illuminate\Support\Str::plural('employee', $nonCollegeCount) }} work in offices only.</p>
                    @endif
                </section>
            </div>

            <div class="col-lg-5">
                <section class="card dashboard-panel">
                    <h2>Recruitment Pipeline</h2>
                    <p class="dashboard-panel-subtitle">{{ $pipelineTotal }} {{ \Illuminate\Support\Str::plural('application', $pipelineTotal) }}</p>

                    @if ($pipelineTotal > 0)
                        <div class="progress-stacked dashboard-pipeline">
                            @foreach ($pipeline as $stage)
                                @if ($stage['count'] > 0)
                                    <div class="progress" role="progressbar" style="width: {{ $stage['count'] / $pipelineTotal * 100 }}%" aria-label="{{ $stage['status']->label() }}: {{ $stage['count'] }}"><div class="progress-bar {{ $stage['status']->cssClass() }}"></div></div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <ul class="list-unstyled d-grid gap-2 mb-0">
                        @foreach ($pipeline as $stage)
                            <li class="d-flex justify-content-between align-items-center">
                                <a class="text-reset text-decoration-none" href="{{ route('recruitment.index', ['status' => $stage['status']->value]) }}">
                                    <span class="dashboard-swatch {{ $stage['status']->cssClass() }}"></span> {{ $stage['status']->label() }}
                                </a>
                                <strong class="tabular-nums">{{ $stage['count'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card dashboard-panel">
                    <h2>Hires per year</h2>
                    <p class="dashboard-panel-subtitle">Based on date hired</p>

                    @if ($hiresPerYear->isEmpty())
                        <p class="text-body-secondary mb-0">No hires on record.</p>
                    @else
                        <div class="d-flex align-items-end gap-2" style="height: 11rem" role="img" aria-label="Hires per year">
                            @foreach ($hiresPerYear as $year => $total)
                                <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100">
                                    <small class="tabular-nums fw-medium">{{ $total }}</small>
                                    <div class="w-100 rounded-top {{ $total === $hiresMax ? 'bg-primary' : 'bg-primary-subtle' }}" style="height: {{ max(2, $total / $hiresMax * 85) }}%"></div>
                                    <small class="text-body-secondary">{{ $year }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-lg-5">
                <section class="card dashboard-panel">
                    <h2>Recent hires</h2>
                    <p class="dashboard-panel-subtitle">Latest additions to the roster</p>

                    <ul class="list-group list-group-flush dashboard-list">
                        @forelse ($recentHires as $hire)
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <div class="min-w-0">
                                    <a class="text-truncate d-block" href="{{ route('employees.show', $hire) }}">{{ $hire->full_name }}</a>
                                    <small class="d-block text-body-secondary">{{ $hire->jobTitle->title }} · {{ $hire->college?->code ?? $hire->office?->code }}</small>
                                </div>
                                <span class="ems-chip tabular-nums">{{ $hire->hired_on->format('M j, Y') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">No employees yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>

        <section class="card dashboard-panel">
                <h2>Recent activity</h2>
                <p class="dashboard-panel-subtitle">Latest changes made in the system</p>

                <ul class="list-group list-group-flush dashboard-list">
                    @forelse ($activity as $entry)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                            <div class="min-w-0">
                                <div class="text-truncate">{{ $entry->summary() }}</div>
                                <small class="text-body-secondary">{{ $entry->user?->name ?? 'System' }}</small>
                            </div>
                            <small class="text-body-secondary text-end text-nowrap">{{ $entry->created_at->format('M j, Y g:i A') }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">Nothing has happened yet.</li>
                    @endforelse
                </ul>

                @if ($isAdmin)
                    <div class="mt-2"><a class="btn btn-link p-0" href="{{ route('audit.index') }}">View all audit logs</a></div>
                @endif
            </section>
    </div>
@endsection
