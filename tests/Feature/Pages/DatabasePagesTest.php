<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\CalendarEvent;
use App\Models\College;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobTitle;
use App\Models\LeaveRequest;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\InAppNotification;
use Illuminate\Http\UploadedFile;

describe('the dashboard', function () {
    it('reads every figure from the database', function () {
        $college = College::factory()->create(['name' => 'College of Testing']);
        $title = JobTitle::factory()->create(['college_id' => $college->id, 'is_published' => true, 'openings' => 1]);
        Employee::factory()->count(3)->create(['job_title_id' => $title->id, 'hired_on' => '2024-05-01']);
        Employee::factory()->create(['job_title_id' => $title->id, 'hired_on' => '2023-02-01', 'status' => EmployeeStatus::Resigned]);
        Application::factory()->count(2)->create(['status' => ApplicationStatus::Applied]);
        Application::factory()->create(['status' => ApplicationStatus::Cancelled]);
        LeaveRequest::factory()->underway()->create(['employee_id' => Employee::query()->first()->id]);

        $this->actingAs(hrAdmin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Head Count by Colleges')
            ->assertSeeInOrder(['College of Testing', '3'])
            ->assertSee('Recruitment Pipeline')
            ->assertSee('Cancelled')
            ->assertDontSee('Interview<', escape: false)
            ->assertSee('Hires per year')
            ->assertSee('2024')
            ->assertSee('2023')
            ->assertSee('Recent hires')
            ->assertSee('Recent activity');
    });

    it('names the pipeline stages Applied, Demo Interview, Offered, Hired, Rejected, and Cancelled', function () {
        expect(collect(ApplicationStatus::pipeline())->map->label()->all())->toBe(['Applied', 'Demo Interview', 'Offered', 'Hired', 'Rejected', 'Cancelled']);
    });

    it('greets the signed-in user and shows recent activity from the audit log to HR Staff too', function () {
        AuditLog::query()->create(['user_id' => null, 'actor_type' => 'system', 'action' => AuditAction::Create, 'entity_type' => Employee::class, 'entity_id' => 1]);

        $this->actingAs(hrStaff())->get(route('dashboard'))->assertSee('Added employee')->assertDontSee('View all audit logs');
    });
});

describe('the audit logs', function () {
    it('lists entries from the database with filters, for the HR Admin only', function () {
        $actor = hrAdmin();
        AuditLog::query()->create(['user_id' => $actor->id, 'actor_type' => 'user', 'action' => AuditAction::Login]);
        AuditLog::query()->create(['user_id' => $actor->id, 'actor_type' => 'user', 'action' => AuditAction::Create, 'entity_type' => Employee::class, 'entity_id' => 7, 'changes' => ['employee_no' => '26-00007']]);

        $this->actingAs(hrStaff())->get(route('audit.index'))->assertForbidden();
        $this->actingAs($actor)->get(route('audit.index'))->assertOk()->assertSee('Signed in')->assertSee('Added employee');
        $this->get(route('audit.index', ['action' => 'login']))->assertSee('Signed in')->assertDontSee('Added employee');
    });

    it('downloads a report as a logged CSV', function () {
        $actor = hrAdmin();

        $this->actingAs($actor)->get(route('audit.report'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect(AuditLog::query()->where('action', AuditAction::Export)->count())->toBe(1);
        $this->actingAs(hrStaff())->get(route('audit.report'))->assertForbidden();
    });
});

describe('notifications', function () {
    it('lists, opens (marking read), and marks all read for each role', function (Closure $makeUser) {
        $user = $makeUser();
        $user->notify(new InAppNotification('Hello', 'A message', url('/notifications')));
        $user->notify(new InAppNotification('Second', 'Another', null));

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Hello')->assertSee('Second');

        $first = $user->notifications()->latest()->first();
        $this->post(route('notifications.open', $first->id))->assertRedirect();
        expect($user->unreadNotifications()->count())->toBe(1);

        $this->post(route('notifications.read-all'))->assertRedirect();
        expect($user->unreadNotifications()->count())->toBe(0);
    })->with([
        'hr admin' => [fn () => hrAdmin()],
        'hr staff' => [fn () => hrStaff()],
        'employee' => [fn () => employeeUser()],
        'applicant' => [fn () => applicantUser()],
    ]);

    it('shows the bell with unread notifications in the employee header, and keeps notifications private', function () {
        $user = employeeUser();
        $user->notify(new InAppNotification('For you', 'Body', null));
        $other = employeeUser();
        $other->notify(new InAppNotification('Not yours', 'Body', null));

        $this->actingAs($user)->get(route('employee.dashboard'))->assertSee('For you')->assertDontSee('Not yours');
        $this->post(route('notifications.open', $other->notifications()->first()->id))->assertNotFound();
    });

    it('does not use the mail channel', function () {
        expect((new InAppNotification('t', 'b'))->via(new User))->toBe(['database']);
    });
});

describe('the employee portal', function () {
    it('shows the signed-in employee, not a fixed person', function () {
        $employee = Employee::factory()->create(['first_name' => 'Zenaida', 'last_name' => 'Quizon']);
        $user = User::factory()->create(['employee_id' => $employee->id, 'name' => 'Zenaida Quizon']);

        $this->actingAs($user)->get(route('employee.dashboard'))->assertOk()->assertSee('Welcome, Zenaida')->assertDontSee('Juan Carlos');
        $this->get(route('employee.profile'))->assertSee('Zenaida Quizon')->assertSee($employee->employee_no);
    });

    it('lays out the dashboard with summary cards, upcoming leave, information, quick links, and benefits', function () {
        $employee = Employee::factory()->partTime()->create(['phone' => '09178889999']);
        $user = User::factory()->create(['employee_id' => $employee->id]);
        LeaveRequest::factory()->approved()->create(['employee_id' => $employee->id, 'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(), 'days' => 2]);
        LeaveRequest::factory()->create(['employee_id' => $employee->id]);
        Benefit::query()->create(['name' => 'Rice allowance', 'description' => 'Monthly', 'full_time_only' => false, 'is_active' => true]);
        Benefit::query()->create(['name' => 'Full-time bonus', 'description' => 'Yearly', 'full_time_only' => true, 'is_active' => true]);

        $this->actingAs($user)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Welcome,', 'Status', 'Leave Taken', 'Upcoming Leave', 'Awaiting Approval',
                'Upcoming approved leave', 'My information', 'Quick links', 'My benefits', 'Leave credits', 'Upcoming', 'Notifications'])
            ->assertSee('In 5 days')
            ->assertSee('09178889999')
            ->assertSee(route('employee.profile'), escape: false)
            ->assertSee(route('employee.files'), escape: false)
            ->assertSee(route('employee.benefits'), escape: false)
            ->assertSee('Rice allowance')
            ->assertDontSee('Full-time bonus');
    });

    it('opens each part of the employee\'s profile in its own window from buttons on the page', function () {
        $employee = Employee::factory()->create(['phone' => '09175556666']);
        $employee->emergencyContacts()->create(['name' => 'Rosa Ramos', 'relationship' => 'Parent', 'phone' => '09170000001', 'is_primary' => true]);
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $page = $this->actingAs($user)->get(route('employee.profile'))->assertOk();

        foreach (['employment', 'government', 'contact', 'emergency', 'security'] as $section) {
            $page->assertSee('data-bs-target="#profile-'.$section.'-modal"', escape: false)
                ->assertSee('id="profile-'.$section.'-modal"', escape: false);
        }

        $page->assertSeeInOrder(['Employment Information', 'Government IDs and Tax IDs', 'Contact Details', 'Emergency Contacts', 'Security'])
            ->assertSee(route('employee.profile.update'), escape: false)
            ->assertSee('09175556666')
            ->assertSee('Rosa Ramos')
            ->assertSee(route('employee.emergency-contacts.store'), escape: false)
            ->assertSee(route('password.change'), escape: false);
    });

    it('updates the employee\'s own contact details and emergency contacts, and nothing else', function () {
        $employee = Employee::factory()->create();
        $employee->emergencyContacts()->create(['name' => 'First', 'relationship' => 'Parent', 'phone' => '09170000001', 'is_primary' => true]);
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($user)->put(route('employee.profile.update'), ['phone' => '09171112222', 'address' => 'New address', 'last_name' => 'Hacker'])->assertSessionHas('success');
        expect($employee->fresh()->phone)->toBe('09171112222')->and($employee->fresh()->last_name)->not->toBe('Hacker');

        $this->post(route('employee.emergency-contacts.store'), ['name' => 'Second', 'relationship' => 'Sibling', 'phone' => '09173334444'])->assertSessionHas('success');
        expect($employee->emergencyContacts()->count())->toBe(2);

        $this->delete(route('employee.emergency-contacts.destroy', $employee->emergencyContacts()->where('name', 'Second')->first()))->assertSessionHas('success');
        $this->delete(route('employee.emergency-contacts.destroy', $employee->emergencyContacts()->first()))->assertSessionHas('error');
    });

    it('stores uploaded files in the database and lets the employee open only their own', function () {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($user)->post(route('employee.files.store'), ['kind' => 'diploma', 'file' => UploadedFile::fake()->create('diploma.pdf', 80, 'application/pdf')])->assertSessionHas('success');
        $document = $employee->documents()->sole();

        $this->get(route('employee.files'))->assertSee('diploma.pdf');
        $this->get(route('documents.show', $document->uuid))->assertOk();
        $this->actingAs(employeeUser())->get(route('documents.show', $document->uuid))->assertNotFound();
        $this->actingAs(hrStaff())->get(route('documents.show', $document->uuid))->assertOk();
    });

    it('lists only the benefits HR gave the employee, and the other benefits separately', function () {
        $employee = Employee::factory()->create();
        $mine = Benefit::query()->create(['name' => 'PhilHealth coverage', 'description' => 'Health']);
        Benefit::query()->create(['name' => 'Loyalty award', 'description' => 'Award']);
        $employee->benefits()->attach($mine->id, ['granted_on' => '2024-01-01']);

        $this->actingAs(User::factory()->create(['employee_id' => $employee->id]))->get(route('employee.benefits'))
            ->assertSeeInOrder(['Your benefits', 'PhilHealth coverage', 'Other benefits', 'Loyalty award']);
    });

    it('shows the directory with internal details only', function () {
        $person = Employee::factory()->create(['last_name' => 'Directoryperson', 'phone' => '09179998888', 'personal_email' => 'secret@example.test']);

        $this->actingAs(employeeUser())->get(route('employee.directory'))->assertSee('Directoryperson')->assertSee($person->official_email)->assertDontSee('09179998888')->assertDontSee('secret@example.test');
    });

    it('shows shared events and the employee\'s own approved leave on the calendar', function () {
        $employee = Employee::factory()->create();
        CalendarEvent::query()->create(['title' => 'Everyone event', 'type' => 'meeting', 'starts_at' => now()->addDay(), 'for_everyone' => true, 'status' => 'scheduled']);
        CalendarEvent::query()->create(['title' => 'HR only event', 'type' => 'meeting', 'starts_at' => now()->addDay(), 'for_everyone' => false, 'status' => 'scheduled']);
        LeaveRequest::factory()->underway()->create(['employee_id' => $employee->id]);

        $this->actingAs(User::factory()->create(['employee_id' => $employee->id]))->get(route('employee.calendar'))->assertSee('Everyone event')->assertDontSee('HR only event')->assertSee('Vacation Leave');
    });

    it('shows the contract end and a pending agreement on the dashboard', function () {
        $employee = Employee::factory()->partTime()->create(['contract_ends_on' => now()->addDays(10)]);
        Offer::factory()->create(['employee_id' => $employee->id, 'kind' => 'renewal']);

        $this->actingAs(User::factory()->create(['employee_id' => $employee->id]))->get(route('employee.dashboard'))->assertSee('Your contract ends on')->assertSee('waiting for your answer');
    });
});

describe('the calendar', function () {
    it('shows interviews, events, and approved leave for the month, and flags a double-booked interviewer', function () {
        $staff = hrStaff();
        $day = now()->addDays(3);
        $first = Interview::factory()->create(['interviewer_id' => $staff->id, 'starts_at' => $day->copy()->setTime(10, 0), 'ends_at' => $day->copy()->setTime(11, 0)]);
        Interview::factory()->create(['interviewer_id' => $staff->id, 'starts_at' => $day->copy()->setTime(10, 30), 'ends_at' => $day->copy()->setTime(11, 30)]);
        CalendarEvent::query()->create(['title' => 'Staff meeting', 'type' => 'meeting', 'starts_at' => $day->copy()->setTime(14, 0), 'status' => 'scheduled']);
        LeaveRequest::factory()->underway()->create();

        $this->actingAs(hrAdmin())->get(route('calendar.index', ['month' => $day->format('Y-m')]))
            ->assertOk()
            ->assertSee('Staff meeting')
            ->assertSee('data-movable', escape: false)
            ->assertSee('border-danger')
            ->assertSee('Schedule', escape: false);

        expect($first->id)->toBeInt();
    });

    it('does not let a finished interview be dragged', function () {
        Interview::factory()->create(['status' => 'completed', 'starts_at' => now()->subDay()->setTime(10, 0), 'ends_at' => now()->subDay()->setTime(11, 0)]);

        $this->actingAs(hrAdmin())->get(route('calendar.index', ['month' => now()->subDay()->format('Y-m')]))->assertDontSee('data-movable', escape: false);
    });

    it('limits the view to the user\'s own interviews on request', function () {
        $mine = hrStaff();
        Interview::factory()->create(['interviewer_id' => $mine->id, 'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(10, 0)]);
        $other = Interview::factory()->create(['starts_at' => now()->addDay()->setTime(13, 0), 'ends_at' => now()->addDay()->setTime(14, 0)]);

        $page = $this->actingAs($mine)->get(route('calendar.index', ['month' => now()->addDay()->format('Y-m'), 'who' => 'mine']));
        $page->assertSee('9:00 AM');
        $page->assertDontSee('1:00 PM');

        expect($other->id)->toBeInt();
    });

    it('adds, edits, moves with a reason (never to the past), and cancels an event', function () {
        $this->actingAs(hrAdmin());
        $day = now()->addDays(4)->toDateString();

        $this->post(route('calendar.events.store'), ['title' => 'Training', 'type' => 'training', 'date' => $day, 'start_time' => '09:00', 'end_time' => '11:00', 'for_everyone' => '1'])->assertSessionHas('success');
        $event = CalendarEvent::query()->sole();

        expect($event->for_everyone)->toBeTrue();

        $this->put(route('calendar.events.update', $event), ['title' => 'Training day', 'type' => 'training', 'date' => $day, 'start_time' => '09:00'])->assertSessionHas('success');
        $this->patch(route('calendar.events.move', $event), ['date' => now()->addDays(6)->toDateString(), 'reason' => ''])->assertSessionHasErrors('reason');
        $this->patch(route('calendar.events.move', $event), ['date' => now()->subDay()->toDateString(), 'reason' => 'x'])->assertSessionHasErrors('date');
        $this->patch(route('calendar.events.move', $event), ['date' => now()->addDays(6)->toDateString(), 'reason' => 'Venue taken'])->assertSessionHas('success');
        expect($event->fresh()->starts_at->toDateString())->toBe(now()->addDays(6)->toDateString())
            ->and(AuditLog::query()->where('changes->reason', 'Venue taken')->count())->toBe(1);

        $this->post(route('calendar.events.cancel', $event), ['reason' => 'Postponed'])->assertSessionHas('success');
        expect($event->fresh()->status)->toBe('cancelled');
    });

    it('is closed to employees and applicants', function () {
        $this->actingAs(employeeUser())->get(route('calendar.index'))->assertForbidden();
        $this->actingAs(applicantUser())->get(route('calendar.index'))->assertForbidden();
    });
});

describe('job positions', function () {
    it('lists postings from the database and lets only the HR Admin edit them', function () {
        $college = College::factory()->create();
        $open = JobTitle::factory()->create(['college_id' => $college->id, 'title' => 'Open Post', 'is_published' => true, 'openings' => 2, 'employment_type' => 'full_time']);
        JobTitle::factory()->create(['college_id' => $college->id, 'title' => 'Closed Post', 'is_published' => false, 'openings' => 0, 'summary' => 'x']);

        $this->actingAs(hrStaff())->get(route('job-positions.index'))->assertOk()->assertSee('Open Post')->assertDontSee('Open a new posting');
        $this->get(route('job-positions.index', ['status' => 'open']))->assertSee('Open Post')->assertDontSee('Closed Post');
        $this->get(route('job-positions.edit', $open))->assertForbidden();
        $this->actingAs(hrAdmin())->get(route('job-positions.edit', $open))->assertOk()->assertDontSee('Benefits <small')
            ->assertSee(route('job-positions.close', $open), escape: false);
        $this->get(route('job-positions.index'))->assertSee('data-row-href="'.route('job-positions.edit', $open).'"', escape: false);
    });

    it('opens a posting with a part-time term that sets the end of contract, and closes it', function () {
        $title = JobTitle::factory()->create(['title' => 'Instructor II']);
        $this->actingAs(hrAdmin());

        $input = ['employment_type' => 'part_time', 'location' => 'Pasig', 'openings' => 2, 'is_published' => '1', 'summary' => 'Short', 'description' => 'Long', 'responsibilities' => "A\nB", 'qualifications' => 'Q'];

        $this->put(route('job-positions.update', $title), $input)->assertSessionHasErrors('contract_term');
        $this->put(route('job-positions.update', $title), $input + ['contract_term' => 'first_semester', 'academic_year' => 2026])->assertSessionHas('success');

        $fresh = $title->fresh();
        expect($fresh->isOpen())->toBeTrue()
            ->and($fresh->contractEndDate()->toDateString())->toBe('2026-11-07')
            ->and($fresh->responsibilities)->toBe(['A', 'B']);

        $this->post(route('job-positions.close', $title))->assertSessionHas('success');
        expect($title->fresh()->isOpen())->toBeFalse();
    });

    it('clears the term for a full-time posting', function () {
        $title = JobTitle::factory()->create(['contract_term' => 'academic_year', 'academic_year' => 2026]);

        $this->actingAs(hrAdmin())->put(route('job-positions.update', $title), ['employment_type' => 'full_time', 'location' => 'Pasig', 'openings' => 1, 'summary' => 'S', 'description' => 'D', 'responsibilities' => 'R', 'qualifications' => 'Q'])->assertSessionHas('success');

        expect($title->fresh()->contract_term)->toBeNull();
    });
});

describe('contracts', function () {
    it('lists contracts that are ending or ended for the HR Admin', function () {
        $ending = Employee::factory()->partTime()->create(['last_name' => 'Endingperson', 'contract_ends_on' => now()->addDays(20)]);
        Employee::factory()->partTime()->create(['last_name' => 'Endedperson', 'contract_ends_on' => now()->subDays(5)]);
        Employee::factory()->partTime()->create(['last_name' => 'Farperson', 'contract_ends_on' => now()->addMonths(6)]);
        Employee::factory()->create(['last_name' => 'Fulltimeperson']);

        $this->actingAs(hrAdmin())->get(route('contracts.index'))->assertSee('Endingperson')->assertSee('Endedperson')->assertSee('Ended')->assertDontSee('Farperson')->assertDontSee('Fulltimeperson')
            ->assertSee('data-row-href="'.route('employees.show', $ending).'#contract-title"', escape: false);
        $this->actingAs(hrStaff())->get(route('contracts.index'))->assertForbidden();
    });
});

it('counts approved leave only', function () {
    expect(LeaveStatus::Approved->value)->toBe('approved');
});
