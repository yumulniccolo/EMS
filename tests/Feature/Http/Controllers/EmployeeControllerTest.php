<?php

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Office;
use App\Models\RateTier;
use App\Models\User;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function newEmployeeInput(JobTitle $title, array $overrides = []): array
{
    return [
        'last_name' => 'Dela Cruz',
        'first_name' => 'Maria Clara',
        'middle_name' => 'S.',
        'employment_type' => 'full_time',
        'job_title_id' => $title->id,
        'hired_on' => '2026-03-02',
        ...$overrides,
    ];
}

describe('the list', function () {
    it('shows current faculty on the Faculty tab and non-teaching staff on the other, with counts', function () {
        Employee::factory()->create(['last_name' => 'Facultyperson']);
        Employee::factory()->nonTeaching()->create(['last_name' => 'Staffperson']);

        $this->actingAs(hrStaff())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Facultyperson')
            ->assertDontSee('Staffperson')
            ->assertSeeInOrder(['Faculty', '1', 'Non-Teaching Staff', '1']);

        $this->get(route('employees.index', ['type' => 'non-teaching']))
            ->assertSee('Staffperson')
            ->assertDontSee('Facultyperson');
    });

    it('leaves out employees who are archived', function () {
        Employee::factory()->create(['last_name' => 'Currentperson']);
        Employee::factory()->inactive()->create(['last_name' => 'Archivedperson']);

        $this->actingAs(hrAdmin())->get(route('employees.index'))->assertSee('Currentperson')->assertDontSee('Archivedperson');
    });

    it('searches by name, employee number, and official email', function (string $term) {
        Employee::factory()->create(['last_name' => 'Marimon', 'first_name' => 'Realiza', 'employee_no' => '20-01234', 'official_email' => 'marimon_realiza@plpasig.edu.ph']);
        Employee::factory()->create(['last_name' => 'Othername']);

        $this->actingAs(hrAdmin())->get(route('employees.index', ['q' => $term]))
            ->assertSee('Marimon')
            ->assertDontSee('Othername');
    })->with(['last name' => 'Marim', 'first and last name' => 'Realiza Marimon', 'employee number' => '1234', 'email' => 'marimon_realiza@']);

    it('filters by college, job title, and employment type', function () {
        $college = College::factory()->create();
        $title = JobTitle::factory()->create(['college_id' => $college->id]);
        Employee::factory()->for($title)->create(['last_name' => 'Inthecollege']);
        Employee::factory()->partTime()->create(['last_name' => 'Parttimeperson']);

        $this->actingAs(hrAdmin());

        $this->get(route('employees.index', ['college' => $college->id]))->assertSee('Inthecollege')->assertDontSee('Parttimeperson');
        $this->get(route('employees.index', ['job_title' => $title->title]))->assertSee('Inthecollege')->assertDontSee('Parttimeperson');
        $this->get(route('employees.index', ['employment_type' => 'part_time']))->assertSee('Parttimeperson')->assertDontSee('Inthecollege');
    });

    it('sorts by name in either direction and paginates 15 to a page', function () {
        Employee::factory()->count(16)->sequence(fn ($sequence) => ['last_name' => 'Surname'.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();

        $this->actingAs(hrAdmin());

        $this->get(route('employees.index'))->assertSee('Surname00')->assertDontSee('Surname15')->assertSee('Showing 1 to 15 of 16');
        $this->get(route('employees.index', ['sort' => 'name', 'dir' => 'desc']))->assertSee('Surname15')->assertDontSee('Surname00');
        $this->get(route('employees.index', ['page' => 2]))->assertSee('Surname15');
    });

    it('shows Generate Report and Archived Employees to the HR Admin only', function () {
        $this->actingAs(hrAdmin())->get(route('employees.index'))->assertSee('Generate Report')->assertSee('Archived Employees');
        $this->actingAs(hrStaff())->get(route('employees.index'))->assertDontSee('Generate Report')->assertDontSee('Archived Employees');
    });

    it('rejects an unknown tab or sort', function () {
        $this->actingAs(hrAdmin())->get(route('employees.index', ['type' => 'visitors']))->assertSessionHasErrors('type');
    });
});

describe('the name suggestions for the search boxes', function () {
    it('suggests current employees that match the term, as "Last, First (number)"', function () {
        $match = Employee::factory()->create(['last_name' => 'Zamoranos', 'first_name' => 'Lia']);
        Employee::factory()->create(['last_name' => 'Unrelated']);
        Employee::factory()->create(['last_name' => 'Zamoraville', 'status' => EmployeeStatus::Resigned]);

        $this->actingAs(hrStaff())->getJson(route('employees.suggest', ['term' => 'Zamora']))
            ->assertOk()
            ->assertExactJson([['label' => "Zamoranos, Lia ({$match->employee_no})", 'value' => 'Zamoranos, Lia']]);
    });

    it('answers nothing for a term shorter than two letters', function () {
        Employee::factory()->create(['last_name' => 'Anderson']);

        $this->actingAs(hrStaff())->getJson(route('employees.suggest', ['term' => 'A']))->assertOk()->assertExactJson([]);
    });

    it('is closed to employees and guests', function () {
        $this->getJson(route('employees.suggest', ['term' => 'Ander']))->assertUnauthorized();
        $this->actingAs(employeeUser())->getJson(route('employees.suggest', ['term' => 'Ander']))->assertForbidden();
    });
});

describe('adding an employee', function () {
    it('creates the next employee number, the official email, and the college from the job title', function () {
        Employee::factory()->create(['employee_no' => '26-00041']);
        $title = JobTitle::factory()->create();

        $response = $this->actingAs(hrStaff())->post(route('employees.store'), newEmployeeInput($title));

        $employee = Employee::query()->where('last_name', 'Dela Cruz')->sole();

        $response->assertRedirect(route('employees.show', $employee));
        expect($employee->employee_no)->toBe('26-00042')
            ->and($employee->official_email)->toBe('delacruz_mariaclara@plpasig.edu.ph')
            ->and($employee->college_id)->toBe($title->college_id)
            ->and($employee->office_id)->toBeNull()
            ->and($employee->employment_type)->toBe(EmploymentType::FullTime)
            ->and($employee->status)->toBe(EmployeeStatus::Active);
    });

    it('lets office staff also teach in a college, and ignores an extra unit on the title side', function () {
        $office = Office::factory()->create();
        $college = College::factory()->create();
        $otherCollege = College::factory()->create();
        $officeTitle = JobTitle::factory()->nonTeaching()->create(['office_id' => $office->id]);
        $collegeTitle = JobTitle::factory()->create(['college_id' => $college->id]);

        $this->actingAs(hrAdmin())->post(route('employees.store'), newEmployeeInput($officeTitle, ['last_name' => 'Both', 'extra_college_id' => $college->id]));
        $this->post(route('employees.store'), newEmployeeInput($collegeTitle, ['last_name' => 'Single', 'extra_college_id' => $otherCollege->id]));

        $both = Employee::query()->where('last_name', 'Both')->sole();
        $single = Employee::query()->where('last_name', 'Single')->sole();

        expect([$both->office_id, $both->college_id])->toBe([$office->id, $college->id])
            ->and($single->college_id)->toBe($college->id);
    });

    it('stores government IDs encrypted, shows only the last four, and keeps them out of the audit log', function () {
        $title = JobTitle::factory()->create();

        $this->actingAs(hrAdmin())->post(route('employees.store'), newEmployeeInput($title, ['tin' => '123-456-789-000', 'sss' => '34-1234567-8']));

        $employee = Employee::query()->where('last_name', 'Dela Cruz')->sole();
        $raw = DB::table('employees')->where('id', $employee->id)->value('government_ids');
        $entry = AuditLog::query()->where('action', AuditAction::Create)->where('entity_id', $employee->id)->sole();

        expect($raw)->not->toContain('123456789')->not->toContain('34-1234567-8')
            ->and($employee->maskedGovernmentIds())->toEqual(['tin' => '9000', 'sss' => '5678'])
            ->and(json_encode($entry->changes))->not->toContain('123-456-789')
            ->and($entry->changes['government_ids_entered'])->toBe(['tin', 'sss']);
    });

    it('rejects :scenario and saves nothing', function (array $override, string $field) {
        $title = JobTitle::factory()->create();

        $this->actingAs(hrAdmin())->post(route('employees.store'), newEmployeeInput($title, $override))->assertSessionHasErrors($field);

        expect(Employee::query()->count())->toBe(0);
    })->with([
        'a missing last name' => [['last_name' => ''], 'last_name'],
        'a missing first name' => [['first_name' => ''], 'first_name'],
        'a bad mobile number' => [['phone' => '12345'], 'phone'],
        'a TIN with the wrong digit count' => [['tin' => '123-45'], 'tin'],
        'an SSS number with letters' => [['sss' => 'ab-1234567-8'], 'sss'],
        'a PhilHealth number that is too short' => [['philhealth' => '12-123456789'], 'philhealth'],
        'a missing hire date' => [['hired_on' => ''], 'hired_on'],
        'an unknown employment type' => [['employment_type' => 'contractual'], 'employment_type'],
        'a birthdate in the future' => [['birthdate' => '2999-01-01'], 'birthdate'],
        'a missing job title' => [['job_title_id' => ''], 'job_title_id'],
    ]);

    it('rejects an inactive job title', function () {
        $title = JobTitle::factory()->create(['is_active' => false]);

        $this->actingAs(hrAdmin())->post(route('employees.store'), newEmployeeInput($title))->assertSessionHasErrors('job_title_id');
    });

    it('gives a second person with the same name a numbered email', function () {
        $title = JobTitle::factory()->create();

        $this->actingAs(hrAdmin());
        $this->post(route('employees.store'), newEmployeeInput($title));
        $this->post(route('employees.store'), newEmployeeInput($title));

        expect(Employee::query()->pluck('official_email')->sort()->values()->all())
            ->toBe(['delacruz_mariaclara2@plpasig.edu.ph', 'delacruz_mariaclara@plpasig.edu.ph']);
    });

    it('shows the form to HR Staff too', function () {
        JobTitle::factory()->create(['title' => 'Lecturer']);

        $this->actingAs(hrStaff())->get(route('employees.create'))->assertOk()->assertSee('Lecturer');
    });
});

describe('the profile', function () {
    it('shows the details, the masked IDs, the base rate, and the yearly leave credits', function () {
        $this->seed(LeavePolicySeeder::class);
        RateTier::factory()->create(['salary_grade' => 12, 'monthly_amount' => 30200, 'effective_date' => '2020-01-01']);
        $employee = Employee::factory()->create(['first_name' => 'Maila', 'middle_name' => null, 'last_name' => 'Unsay', 'hired_on' => now()->subYear()->toDateString()]);
        $employee->setGovernmentIds(['tin' => '123-456-789-000']);
        $employee->save();

        $this->actingAs(hrStaff())->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('Maila Unsay')
            ->assertSee($employee->employee_no)
            ->assertSee('•••• 9000')
            ->assertDontSee('123-456-789-000')
            ->assertSee('₱30,200.00')
            ->assertSee('Vacation Leave')
            ->assertSeeInOrder(['Vacation Leave', '15', '0', '0', '15']);
    });

    it('lists only vacation and sick credits for part-time staff', function () {
        $this->seed(LeavePolicySeeder::class);
        $employee = Employee::factory()->partTime()->create(['hired_on' => now()->subYear()->toDateString()]);

        $this->actingAs(hrAdmin())->get(route('employees.show', $employee))->assertSee('Vacation Leave')->assertSee('7.5')->assertDontSee('Special Privilege Leave');
    });

    it('shows the HR Admin the transfer, step, and archive controls and the government ID reveal control but not HR Staff', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(hrAdmin())->get(route('employees.show', $employee))
            ->assertSee('Edit the contract')
            ->assertSee('Archive Employee')
            ->assertSee(route('government-ids.reveal', [$employee, 'tin']), escape: false)
            ->assertSee('Reveal');

        $this->actingAs(hrStaff())->get(route('employees.show', $employee))
            ->assertOk()
            ->assertDontSee('Edit the contract')
            ->assertDontSee('Archive Employee')
            ->assertDontSee('Reveal');
    });

    it('is not found for an unknown or malformed employee number', function () {
        $this->actingAs(hrAdmin())->get('/employees/99-99999')->assertNotFound();
        $this->get('/employees/not-an-id')->assertNotFound();
    });
});

describe('editing an employee', function () {
    it('saves personal and contact details and records the old and new values', function () {
        $employee = Employee::factory()->create(['phone' => '09171234567', 'address' => 'Old address']);

        $this->actingAs(hrStaff())->put(route('employees.update', $employee), [
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'phone' => '09181234567',
            'address' => 'New address',
        ])->assertRedirect(route('employees.show', $employee));

        $entry = AuditLog::query()->where('action', AuditAction::Update)->where('entity_id', $employee->id)->sole();

        expect($employee->fresh()->phone)->toBe('09181234567')
            ->and($entry->changes['address'])->toEqual(['old' => 'Old address', 'new' => 'New address']);
    });

    it('keeps the government IDs on file when the boxes are left blank, and replaces one that is typed', function () {
        $employee = Employee::factory()->create();
        $employee->forceFill(['government_ids' => null, 'government_id_hints' => null]);
        $employee->setGovernmentIds(['tin' => '111-111-111-000', 'sss' => '11-1111111-1']);
        $employee->save();

        $this->actingAs(hrAdmin())->put(route('employees.update', $employee), [
            'last_name' => $employee->last_name, 'first_name' => $employee->first_name, 'tin' => '', 'sss' => '22-2222222-2',
        ]);

        expect($employee->fresh()->government_ids)->toBe(['tin' => '111-111-111-000', 'sss' => '22-2222222-2']);
    });

    it('does not change the assignment, even when HR sends one', function () {
        $employee = Employee::factory()->create();
        $other = JobTitle::factory()->create();

        $this->actingAs(hrAdmin())->put(route('employees.update', $employee), [
            'last_name' => $employee->last_name, 'first_name' => $employee->first_name,
            'job_title_id' => $other->id, 'status' => 'terminated', 'employment_type' => 'part_time',
        ]);

        $fresh = $employee->fresh();

        expect($fresh->job_title_id)->toBe($employee->job_title_id)
            ->and($fresh->status)->toBe(EmployeeStatus::Active)
            ->and($fresh->employment_type)->toBe(EmploymentType::FullTime);
    });

    it('never shows a government ID number on the edit form', function () {
        $employee = Employee::factory()->create();
        $employee->setGovernmentIds(['tin' => '123-456-789-000']);
        $employee->save();

        $this->actingAs(hrAdmin())->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertDontSee('123-456-789-000')
            ->assertSee('•••• 9000');
    });

    it('rejects an invalid phone number', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(hrAdmin())->put(route('employees.update', $employee), [
            'last_name' => $employee->last_name, 'first_name' => $employee->first_name, 'phone' => 'call me',
        ])->assertSessionHasErrors('phone');
    });
});

it('prints the signed-in user in the header and the dashboard greeting', function () {
    $head = User::factory()->hrAdmin()->create(['name' => 'Niccolo Franco Yumul', 'email' => 'yumul_niccolofranco@plpasig.edu.ph']);

    $this->actingAs($head)->get(route('dashboard'))
        ->assertSee('NY')
        ->assertSee('Niccolo Franco Yumul')
        ->assertSee('HR Admin')
        ->assertSee('yumul_niccolofranco@plpasig.edu.ph')
        ->assertSee('Welcome, Niccolo');
});

describe('employee documents', function () {
    it('lets HR add a document to the 201 file, stores it in the database, and shows it on the profile', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(hrStaff())->post(route('employees.documents.store', $employee), ['kind' => 'diploma', 'file' => UploadedFile::fake()->create('diploma.pdf', 80, 'application/pdf')])->assertSessionHas('success');

        expect($employee->documents()->sole()->original_name)->toBe('diploma.pdf');
        $this->get(route('employees.show', $employee))->assertSee('diploma.pdf')->assertSee('Documents');
    });

    it('rejects a missing or oversized file', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(hrAdmin())->post(route('employees.documents.store', $employee), ['kind' => 'diploma'])->assertSessionHasErrors('file');
        $this->post(route('employees.documents.store', $employee), ['kind' => 'diploma', 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('file');
    });

    it('shows the contract, agreements, and transfer history together, with the edit form only for the HR Admin', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(hrAdmin())->get(route('employees.show', $employee))->assertSee('Contract and Agreements')->assertSee('Transfer History')->assertSee('Edit Contract')->assertSee('data-part-time-fields', escape: false);
        $this->actingAs(hrStaff())->get(route('employees.show', $employee))->assertSee('Transfer History')->assertDontSee('Edit Contract');
    });
});
