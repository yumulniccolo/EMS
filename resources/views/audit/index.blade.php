@extends('layouts.admin')

@section('title', 'Audit Logs · '.config('app.name'))

@section('content')
    <div class="d-grid gap-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Audit Logs</h1>
                <p class="text-body-secondary mt-1 mb-0">Who did what and when. Entries cannot be edited or deleted. Passwords and government ID numbers are never recorded.</p>
            </div>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('audit.report', request()->query()) }}">
                <x-icon name="report" />
                Generate Report
            </a>
        </div>

        <form method="get" class="card employees-filters" data-auto-submit>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label for="audit-action" class="visually-hidden">Action</label>
                    <select class="form-select" id="audit-action" name="action">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action->value }}" @selected(($filters['action'] ?? '') === $action->value)>{{ ucfirst(str_replace('_', ' ', $action->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="audit-user" class="visually-hidden">User</label>
                    <select class="form-select" id="audit-user" name="user">
                        <option value="">All users</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label for="audit-entity" class="visually-hidden">Record</label>
                    <select class="form-select" id="audit-entity" name="entity">
                        <option value="">All records</option>
                        @foreach ($entities as $type => $label)
                            <option value="{{ $type }}" @selected(($filters['entity'] ?? '') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="visually-hidden" for="audit-dates">Date range</label>
                    <x-date-range id="audit-dates" :from="$filters['from'] ?? null" :to="$filters['to'] ?? null" placeholder="Date range" />
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><a class="btn btn-sm btn-outline-secondary" href="{{ route('audit.index') }}">Clear Filters</a></div>
        </form>

        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">What happened</th><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="text-nowrap">{{ $entry->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ $entry->user?->name ?? 'System' }}</td>
                                <td><span class="status-pill">{{ ucfirst(str_replace('_', ' ', $entry->action->value)) }}</span></td>
                                <td>{{ $entry->summary() }}</td>
                                <td class="text-end">
                                    @if ($entry->changes)
                                        <button type="button" class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#audit-{{ $entry->id }}" aria-expanded="false" aria-controls="audit-{{ $entry->id }}">Details</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($entry->changes)
                                <tr class="collapse" id="audit-{{ $entry->id }}">
                                    <td colspan="5" class="bg-body-tertiary"><pre class="small mb-0">{{ json_encode($entry->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre><small class="text-body-secondary">IP address {{ $entry->ip_address ?? 'unknown' }}</small></td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="employees-empty text-center"><strong>No entries</strong> Nothing matches these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="employees-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <span>Showing {{ $entries->firstItem() ?? 0 }} to {{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries</span>
                {{ $entries->links() }}
            </div>
        </div>
    </div>
@endsection
