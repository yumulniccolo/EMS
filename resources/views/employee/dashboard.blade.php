@extends('layouts.employee')

@section('title', 'Dashboard · '.config('app.name'))

@php
    use App\Enums\EmploymentType;
    use App\Enums\LeaveStatus;
    use App\Models\Benefit;
    use Illuminate\Support\Str;

    // Read here from the employee's own records; the controller passes the employee and the lists used further down.
    $today = now()->startOfDay();
    $approvedLeave = $employee->leaveRequests()->where('status', LeaveStatus::Approved);
    $leaveTakenDays = (float) (clone $approvedLeave)->whereYear('start_date', $today->year)->whereDate('start_date', '<=', $today)->sum('days');
    $upcomingLeaveCount = (clone $approvedLeave)->whereDate('start_date', '>', $today)->count();
    $awaitingCount = $employee->leaveRequests()->where('status', LeaveStatus::Pending)->count();
    $applicableBenefits = Benefit::query()->where('is_active', true)
        ->when($employee->employment_type !== EmploymentType::FullTime, fn ($query) => $query->where('full_time_only', false))
        ->orderBy('name')->pluck('name');
    $formatDays = fn (float $days): string => rtrim(rtrim(number_format($days, 1), '0'), '.') ?: '0';
    $summary = [
        ['label' => 'Status', 'value' => ucfirst($employee->status->value), 'caption' => $employee->employment_type->label()],
        ['label' => 'Leave Taken', 'value' => $formatDays($leaveTakenDays), 'caption' => 'days in '.$today->year],
        ['label' => 'Upcoming Leave', 'value' => $upcomingLeaveCount, 'caption' => 'approved '.Str::plural('request', $upcomingLeaveCount)],
        ['label' => 'Awaiting Approval', 'value' => $awaitingCount, 'caption' => Str::plural('request', $awaitingCount).' with HR'],
    ];
    $quickLinks = [
        ['label' => 'Profile', 'caption' => 'Your record', 'icon' => 'user', 'url' => route('employee.profile')],
        ['label' => 'My Files', 'caption' => 'Your documents', 'icon' => 'folder', 'url' => route('employee.files')],
        ['label' => 'Benefits', 'caption' => 'What you get', 'icon' => 'gift', 'url' => route('employee.benefits')],
    ];
@endphp

{{--
    The employee's home: greeting, a row of summary cards, upcoming approved leave beside their information, quick links beside
    the benefits that apply to their employment, then leave credits, upcoming items, and notifications.
--}}
@section('content')
    <div class="d-grid gap-4">
        <div>
            <h1 class="page-title">Welcome, {{ $employee->first_name }}</h1>
            <p class="text-body-secondary mt-1 mb-0">{{ $employee->jobTitle->title }} · {{ $employee->college?->name ?? $employee->office?->name }} · {{ $employee->employee_no }}</p>
        </div>

        @if ($pendingOffers > 0)
            <div class="alert alert-primary mb-0" role="status">
                You have {{ $pendingOffers }} {{ \Illuminate\Support\Str::plural('agreement', $pendingOffers) }} waiting for your answer.
                <a href="{{ route('employee.offers') }}">Read and answer</a>.
            </div>
        @endif

        @if ($employee->contract_ends_on)
            <div class="alert {{ $employee->contractEndsWithin(30) ? 'alert-warning' : 'alert-secondary' }} mb-0" role="status">
                Your contract ends on <strong>{{ $employee->contract_ends_on->format('F j, Y') }}</strong>.
                @if ($employee->contractEndsWithin(30)) HR will tell you if a new contract is offered. @endif
            </div>
        @endif

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
            @foreach ($summary as $card)
                <div class="col">
                    <section class="card dashboard-kpi">
                        <h2 class="dashboard-kpi-label fs-6 mb-0">{{ $card['label'] }}</h2>
                        <strong class="dashboard-kpi-value tabular-nums">{{ $card['value'] }}</strong>
                        <span class="dashboard-kpi-caption">{{ $card['caption'] }}</span>
                    </section>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>Upcoming approved leave</h2>
                    <p class="dashboard-panel-subtitle">Leave HR has approved that has not ended yet</p>
                    <ul class="list-group list-group-flush dashboard-list">
                        @forelse ($upcomingLeave as $leave)
                            @php($daysUntil = (int) $today->diffInDays($leave->start_date->copy()->startOfDay(), false))
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <span class="fw-medium">{{ $leave->leave_type->label() }}</span>
                                    <small class="d-block text-body-secondary">
                                        {{ $leave->start_date->format('M j, Y') }}@if (! $leave->end_date->isSameDay($leave->start_date)) to {{ $leave->end_date->format('M j, Y') }}@endif
                                        · {{ $formatDays((float) $leave->days) }} {{ Str::plural('day', (float) $leave->days) }}
                                    </small>
                                </div>
                                <span class="ems-chip">
                                    @if ($daysUntil > 1)
                                        In {{ $daysUntil }} days
                                    @elseif ($daysUntil === 1)
                                        Tomorrow
                                    @elseif ($daysUntil === 0)
                                        Starts today
                                    @else
                                        On leave now
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">No approved leave coming up.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>My information</h2>
                    <p class="dashboard-panel-subtitle">Your record as HR holds it</p>
                    <dl class="row row-cols-1 row-cols-sm-2 g-3 mb-0 employee-details">
                        <div class="col"><dt>Employee ID</dt><dd class="mb-0 tabular-nums">{{ $employee->employee_no }}</dd></div>
                        <div class="col"><dt>Status</dt><dd class="mb-0">{{ ucfirst($employee->status->value) }}</dd></div>
                        <div class="col"><dt>{{ $employee->college ? 'College' : 'Office' }}</dt><dd class="mb-0">{{ $employee->college?->name ?? $employee->office?->name ?? '—' }}</dd></div>
                        <div class="col"><dt>Job Title</dt><dd class="mb-0">{{ $employee->jobTitle->title }}</dd></div>
                        <div class="col"><dt>Date Hired</dt><dd class="mb-0">{{ $employee->hired_on?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="col"><dt>Phone</dt><dd class="mb-0">{{ $employee->phone ?? '—' }}</dd></div>
                        <div class="col-12"><dt>Email</dt><dd class="mb-0 text-break">{{ $employee->official_email }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>Quick links</h2>
                    <p class="dashboard-panel-subtitle">Go straight to your records</p>
                    <div class="row row-cols-1 row-cols-sm-3 g-2">
                        @foreach ($quickLinks as $link)
                            <div class="col">
                                <a class="quick-link" href="{{ $link['url'] }}">
                                    <x-icon :name="$link['icon']" />
                                    <span class="quick-link-label">{{ $link['label'] }}</span>
                                    <small>{{ $link['caption'] }}</small>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>My benefits</h2>
                    <p class="dashboard-panel-subtitle">Benefits for {{ $employee->employment_type === EmploymentType::FullTime ? 'full-time' : 'part-time' }} staff like you ({{ $employee->jobTitle->title }})</p>
                    @if ($applicableBenefits->isEmpty())
                        <p class="text-body-secondary mb-0">No benefits are listed yet.</p>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($applicableBenefits as $benefitName)
                                <span class="ems-chip">{{ $benefitName }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-3"><a href="{{ route('employee.benefits') }}">See your benefits</a></div>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>Leave credits</h2>
                    <p class="dashboard-panel-subtitle">Earned each month since you were hired</p>
                    <ul class="list-group list-group-flush dashboard-list">
                        @foreach ($balances as $row)
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>{{ $row['label'] }}</span><strong class="tabular-nums">{{ rtrim(rtrim(number_format($row['remaining'], 2), '0'), '.') ?: '0' }} left</strong></li>
                        @endforeach
                    </ul>
                    <div class="mt-2"><a href="{{ route('employee.leave') }}">File or follow a leave request</a></div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card dashboard-panel">
                    <h2>Upcoming</h2>
                    <ul class="list-group list-group-flush dashboard-list">
                        @foreach ($upcomingLeave as $leave)
                            <li class="list-group-item px-0">{{ $leave->leave_type->label() }}: {{ $leave->start_date->format('M j') }} to {{ $leave->end_date->format('M j, Y') }}</li>
                        @endforeach
                        @foreach ($events as $event)
                            <li class="list-group-item px-0">{{ $event->title }}: {{ $event->starts_at->format('M j, Y g:i A') }}</li>
                        @endforeach
                        @if ($upcomingLeave->isEmpty() && $events->isEmpty())
                            <li class="list-group-item px-0 text-body-secondary">Nothing coming up.</li>
                        @endif
                    </ul>
                </section>
            </div>
        </div>

        <section class="card dashboard-panel">
            <h2>Notifications</h2>
            <ul class="list-group list-group-flush dashboard-list">
                @forelse ($unread as $notification)
                    <li class="list-group-item px-0"><span class="fw-medium">{{ $notification->data['title'] }}</span><small class="d-block text-body-secondary">{{ $notification->data['body'] }}</small></li>
                @empty
                    <li class="list-group-item px-0 text-body-secondary">You are all caught up.</li>
                @endforelse
            </ul>
            <div class="mt-2"><a href="{{ route('notifications.index') }}">View all notifications</a></div>
        </section>
    </div>
@endsection
