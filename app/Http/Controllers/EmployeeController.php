<?php

namespace App\Http\Controllers;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\RevealGovernmentId;
use App\Enums\AuditAction;
use App\Enums\ContractTerm;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\JobTitleCategory;
use App\Enums\MaritalStatus;
use App\Enums\Sex;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Office;
use App\Services\AcademicCalendar;
use App\Services\AuditLogger;
use App\Services\LeaveBalances;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The employee records: a searchable list of current employees (faculty and non-teaching staff), the profile, and
 * creating and editing the personal details. Assignments, archiving, and ID reveals are separate HR Admin actions.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'type' => ['nullable', 'in:faculty,non-teaching'],
            'q' => ['nullable', 'string', 'max:100'],
            'college' => ['nullable', 'integer'],
            'office' => ['nullable', 'integer'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'employment_type' => ['nullable', 'in:full_time,part_time'],
            'sort' => ['nullable', 'in:name,number,hired'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);
        $type = $filters['type'] ?? 'faculty';

        $active = Employee::query()->where('status', EmployeeStatus::Active);

        $employees = (clone $active)
            ->with(['jobTitle', 'college', 'office'])
            ->withExists(['leaveRequests as is_on_leave' => fn ($leave) => $leave->covering()])
            ->listed([...$filters, 'type' => $type])
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'filters' => $filters,
            'type' => $type,
            'counts' => [
                'faculty' => (clone $active)->faculty()->count(),
                'non-teaching' => (clone $active)->nonTeaching()->count(),
            ],
            'colleges' => College::query()->orderBy('name')->pluck('name', 'id'),
            'offices' => Office::query()->orderBy('name')->pluck('name', 'id'),
            'jobTitles' => JobTitle::query()
                ->where('category', $type === 'faculty' ? JobTitleCategory::Faculty : JobTitleCategory::NonTeaching)
                ->orderBy('title')->distinct()->pluck('title', 'title'),
        ]);
    }

    /**
     * Name suggestions for the search boxes (jQuery UI Autocomplete): up to eight current employees matching the term.
     */
    public function suggest(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $term = trim((string) $request->validate(['term' => ['nullable', 'string', 'max:100']])['term'] ?? '');

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->search($term)
                ->orderBy('last_name')->orderBy('first_name')
                ->limit(8)
                ->get(['first_name', 'last_name', 'employee_no'])
                ->map(fn (Employee $employee): array => [
                    'label' => "{$employee->last_name}, {$employee->first_name} ({$employee->employee_no})",
                    'value' => "{$employee->last_name}, {$employee->first_name}",
                ])
                ->all()
        );
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', $this->formData(new Employee, route('employees.store'), 'New Employee'));
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $createEmployee): RedirectResponse
    {
        $employee = $createEmployee->handle(
            $request->user(),
            [...$request->personalAttributes(), ...$request->safe()->only(['employment_type', 'job_title_id', 'extra_college_id', 'extra_office_id'])],
            $request->governmentIds(),
        );

        return to_route('employees.show', $employee)->with('success', "{$employee->full_name} was added as {$employee->employee_no}.");
    }

    public function show(Employee $employee, LeaveBalances $leaveBalances): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['jobTitle', 'college', 'office', 'emergencyContacts', 'user', 'transfers.fromCollege', 'transfers.toCollege', 'transfers.fromJobTitle', 'transfers.toJobTitle']);

        return view('employees.show', [
            'employee' => $employee,
            'baseRate' => $employee->baseRate(),
            'transfers' => $employee->transfers->sortByDesc(fn ($transfer) => [$transfer->effective_date, $transfer->id])->values(),
            'leaveBalances' => $leaveBalances->forEmployee($employee),
            'recentLeave' => $employee->leaveRequests()->latest('start_date')->latest('id')->limit(5)->get(),
            'onLeaveUntil' => $employee->leaveRequests()->covering()->max('end_date'),
            'activity' => AuditLog::query()
                ->where('entity_type', $employee->getMorphClass())
                ->where('entity_id', $employee->id)
                ->with('user')
                ->latest('id')->limit(10)->get(),
            'revealReasons' => RevealGovernmentId::REASONS,
            'offers' => $employee->offers()->latest('id')->get(),
            'contractTerms' => collect(ContractTerm::cases())->mapWithKeys(fn ($term): array => [$term->value => $term->label()])->all(),
            'academicYears' => app(AcademicCalendar::class)->selectableYears(),
            'jobTitleOptions' => $this->jobTitleOptions(),
            'colleges' => College::query()->orderBy('name')->pluck('name', 'id'),
            'offices' => Office::query()->orderBy('name')->pluck('name', 'id'),
            'archiveStatuses' => collect(EmployeeStatus::cases())->reject(fn (EmployeeStatus $status) => $status === EmployeeStatus::Active)
                ->mapWithKeys(fn (EmployeeStatus $status) => [$status->value => ucfirst($status->value)])->all(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.form', $this->formData($employee, route('employees.update', $employee), 'Edit Employee'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $employee, $audit): void {
            $employee->fill($request->personalAttributes());
            $employee->setGovernmentIds($request->governmentIds());
            $changes = $audit->changesFor($employee);
            $employee->save();

            if ($changes !== []) {
                $audit->log(AuditAction::Update, $employee, $changes);
            }
        });

        return to_route('employees.show', $employee)->with('success', 'Employee details saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Employee $employee, string $action, string $heading): array
    {
        return [
            'employee' => $employee,
            'action' => $action,
            'heading' => $heading,
            'sexes' => collect(Sex::cases())->mapWithKeys(fn (Sex $sex) => [$sex->value => $sex->value])->all(),
            'maritalStatuses' => collect(MaritalStatus::cases())->mapWithKeys(fn (MaritalStatus $status) => [$status->value => $status->value])->all(),
            'employmentTypes' => collect(EmploymentType::cases())->mapWithKeys(fn (EmploymentType $type) => [$type->value => $type->label()])->all(),
            'jobTitleOptions' => $this->jobTitleOptions(),
            'colleges' => College::query()->orderBy('name')->pluck('name', 'id')->all(),
            'offices' => Office::query()->orderBy('name')->pluck('name', 'id')->all(),
            'governmentIdLabels' => ['tin' => 'TIN', 'gsis' => 'GSIS', 'sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG', 'bir' => 'BIR'],
        ];
    }

    /**
     * Active job titles grouped by college and office, for the job title dropdown.
     *
     * @return array<string, array<int, string>>
     */
    private function jobTitleOptions(): array
    {
        return JobTitle::query()
            ->where('is_active', true)
            ->with(['college', 'office'])
            ->orderBy('salary_grade')
            ->orderBy('title')
            ->get()
            ->groupBy(fn (JobTitle $title) => $title->college?->name ?? $title->office?->name)
            ->sortKeys()
            ->map(fn ($titles) => $titles->pluck('title', 'id')->all())
            ->all();
    }
}
