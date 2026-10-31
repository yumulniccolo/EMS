@extends('layouts.admin')

@section('title', 'Colleges · '.config('app.name'))

{{-- The academic colleges, their programs, and their deans. Everyone in HR reads this page; only the HR Admin adds or edits. --}}
@section('content')
    <div class="d-grid gap-4" data-page="colleges">
        <div>
            <h1 class="page-title">Colleges</h1>
            <p class="text-body-secondary mt-1 mb-0">The academic colleges, their programs, and their faculty</p>
        </div>

        @can('manage-reference-data')
            <section class="card employee-form-card" aria-labelledby="add-college-title">
                <h2 class="fs-5" id="add-college-title">Add a college</h2>
                <form method="post" action="{{ route('colleges.store') }}" class="row g-3 align-items-start" novalidate>
                    @csrf
                    <div class="col-md-3"><x-field name="code" label="Code" required /></div>
                    <div class="col-md-6"><x-field name="name" label="Name" required /></div>
                    <div class="col-md-3 align-self-end"><button type="submit" class="btn btn-primary w-100">Add College</button></div>
                </form>
            </section>
        @endcan

        @php($canManage = auth()->user()->can('manage-reference-data'))
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0 employees-table">
                    <thead>
                        <tr>
                            @if ($canManage) <th scope="col" class="sortable-handle-cell"><span class="visually-hidden">Order</span></th> @endif
                            <th scope="col">Code</th>
                            <th scope="col">College</th>
                            <th scope="col">Dean</th>
                            <th scope="col">Programs</th>
                            <th scope="col" class="text-end">People</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody @if ($canManage) data-sortable-list="colleges" @endif>
                        @forelse ($colleges as $college)
                            <tr class="employee-row" data-row-href="{{ $canManage ? route('colleges.edit', $college) : route('employees.index', ['college' => $college->id]) }}" data-sortable-id="{{ $college->id }}">
                                @if ($canManage) <x-sort-handle :label="$college->name" /> @endif
                                <td class="tabular-nums">{{ $college->code }}</td>
                                <td>
                                    <a class="text-reset fw-medium" href="{{ route('employees.index', ['college' => $college->id]) }}">{{ $college->name }}</a>
                                </td>
                                <td>{{ $college->deans->first()?->employee->full_name ?? '—' }}</td>
                                <td>
                                    @forelse ($college->programs as $program)
                                        <span class="ems-chip" title="{{ $program->name }}">{{ $program->code }}</span>
                                    @empty
                                        <span class="text-body-secondary">—</span>
                                    @endforelse
                                </td>
                                <td class="text-end tabular-nums">{{ $college->employees_count }}</td>
                                <td>{{ $college->is_active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-body-secondary py-4">No colleges yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
