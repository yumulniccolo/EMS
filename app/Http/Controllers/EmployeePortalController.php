<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Models\Benefit;
use App\Models\CalendarEvent;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AuditLogger;
use App\Services\DocumentStore;
use App\Services\LeaveBalances;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Employee interface, filled from the database for the signed-in employee: dashboard, profile (they can update their own contact
 * details and emergency contacts), files, benefits, the directory, and the calendar. The directory shows only internal details.
 */
class EmployeePortalController extends Controller
{
    public const DOCUMENT_KINDS = ['diploma' => 'Diploma', 'transcript' => 'Transcript', 'certificate' => 'Certificate', 'id' => 'Government ID copy', 'contract' => 'Contract', 'other' => 'Other'];

    public function dashboard(Request $request, LeaveBalances $balances): View
    {
        $employee = $this->employee($request)->load(['jobTitle', 'college', 'office']);

        return view('employee.dashboard', [
            'employee' => $employee,
            'balances' => collect($balances->forEmployee($employee))->whereIn('type', LeaveBalances::ACCRUING),
            'pendingOffers' => $employee->offers()->pending()->count(),
            'upcomingLeave' => $employee->leaveRequests()->where('status', 'approved')->whereDate('end_date', '>=', now())->orderBy('start_date')->limit(3)->get(),
            'events' => CalendarEvent::query()->where('for_everyone', true)->where('status', 'scheduled')->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(4)->get(),
            'unread' => $request->user()->unreadNotifications()->limit(5)->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        $employee = $this->employee($request)->load(['jobTitle', 'college', 'office', 'emergencyContacts']);

        return view('employee.profile', ['employee' => $employee, 'baseRate' => $employee->baseRate(), 'maskedIds' => $employee->maskedGovernmentIds()]);
    }

    public function updateProfile(Request $request, AuditLogger $audit): RedirectResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'phone' => ['nullable', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
        ], ['phone.regex' => 'Enter a valid mobile number: 09XXXXXXXXX or +639XXXXXXXXX.']);

        DB::transaction(function () use ($employee, $data, $audit, $request): void {
            $employee->fill(array_map(fn ($value) => filled($value) ? $value : null, $data));
            $changes = $audit->changesFor($employee);
            $employee->save();

            if ($changes !== []) {
                $audit->log(AuditAction::Update, $employee, $changes, $request->user());
            }
        });

        return back()->with('success', 'Your contact details were saved.');
    }

    public function storeEmergencyContact(Request $request, AuditLogger $audit): RedirectResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['phone.regex' => 'Enter a valid mobile number: 09XXXXXXXXX or +639XXXXXXXXX.']);

        DB::transaction(function () use ($employee, $data, $audit, $request): void {
            $contact = $employee->emergencyContacts()->create([...$data, 'is_primary' => $employee->emergencyContacts()->doesntExist()]);
            $audit->log(AuditAction::Create, $contact, ['employee_no' => $employee->employee_no], $request->user());
        });

        return back()->with('success', 'Emergency contact added.');
    }

    public function destroyEmergencyContact(Request $request, EmergencyContact $contact, AuditLogger $audit): RedirectResponse
    {
        $employee = $this->employee($request);
        abort_unless($contact->employee_id === $employee->id, 404);

        if ($employee->emergencyContacts()->count() <= 1) {
            return back()->with('error', 'Keep at least one emergency contact. Add another before removing this one.');
        }

        DB::transaction(function () use ($contact, $employee, $audit, $request): void {
            $audit->log(AuditAction::Delete, $contact, ['employee_no' => $employee->employee_no], $request->user());
            $wasPrimary = $contact->is_primary;
            $contact->delete();

            if ($wasPrimary) {
                $employee->emergencyContacts()->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });

        return back()->with('success', 'Emergency contact removed.');
    }

    public function files(Request $request): View
    {
        $employee = $this->employee($request);

        return view('employee.files', ['documents' => $employee->documents()->latest('id')->get(), 'kinds' => self::DOCUMENT_KINDS]);
    }

    public function storeFile(Request $request, DocumentStore $store, AuditLogger $audit): RedirectResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(self::DOCUMENT_KINDS))],
            'file' => ['required', 'file', 'mimes:'.implode(',', DocumentStore::ALLOWED_EXTENSIONS), 'max:'.DocumentStore::MAX_KILOBYTES],
        ], ['file.max' => 'The file must be 5 MB or smaller.', 'file.required' => 'Choose a file to upload.']);

        DB::transaction(function () use ($employee, $data, $request, $store, $audit): void {
            $document = $store->put($employee, $data['kind'], $request->file('file'), $request->user());
            $audit->log(AuditAction::Create, $document, ['employee_no' => $employee->employee_no, 'kind' => $data['kind']], $request->user());
        });

        return back()->with('success', 'The file was added to your 201 file.');
    }

    public function benefits(Request $request): View
    {
        $employee = $this->employee($request);
        $mine = $employee->benefits()->orderBy('name')->get();

        return view('employee.benefits', ['mine' => $mine, 'others' => Benefit::query()->where('is_active', true)->whereNotIn('id', $mine->modelKeys())->orderBy('name')->get()]);
    }

    public function directory(Request $request): View
    {
        $term = $request->string('q')->trim()->toString();

        return view('employee.directory', [
            'employees' => Employee::query()
                ->with(['jobTitle', 'college', 'office'])
                ->where('status', EmployeeStatus::Active)
                ->when($term !== '', fn ($query) => $query->search($term))
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate(20)->withQueryString(),
            'term' => $term,
        ]);
    }

    public function calendar(Request $request): View
    {
        $employee = $this->employee($request);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = CarbonImmutable::parse(($data['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();
        $gridStart = $month->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $events = CalendarEvent::query()->where('for_everyone', true)->where('status', 'scheduled')->whereBetween('starts_at', [$gridStart, $gridEnd])->orderBy('starts_at')->get();
        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('start_date', '<=', $gridEnd)->whereDate('end_date', '>=', $gridStart)
            ->orderBy('start_date')->get();

        $days = [];

        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $day,
                'inMonth' => $day->month === $month->month,
                'isToday' => $day->isToday(),
                'events' => $events->filter(fn (CalendarEvent $event): bool => $event->starts_at->toDateString() === $key)->values(),
                'leave' => $leave->filter(fn (LeaveRequest $request): bool => $request->start_date->toDateString() <= $key && $request->end_date->toDateString() >= $key)->values(),
            ];
        }

        return view('employee.calendar', [
            'month' => $month,
            'weeks' => array_chunk($days, 7),
        ]);
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if($employee === null, 403, 'This account is not linked to an employee record.');

        return $employee;
    }
}
