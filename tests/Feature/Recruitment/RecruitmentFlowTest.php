<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\EmploymentType;
use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\OfferStatus;
use App\Enums\Role;
use App\Mail\HiredMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\College;
use App\Models\CollegeDean;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobTitle;
use App\Models\Offer;
use App\Models\User;
use App\Services\PersonalMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->college = College::factory()->create(['code' => 'COED']);
    $this->position = JobTitle::factory()->create(['college_id' => $this->college->id, 'title' => 'Instructor I', 'is_published' => true, 'openings' => 2, 'employment_type' => 'full_time']);
    $this->admin = hrAdmin();
    $this->staff = hrStaff();
    $this->application = Application::factory()->create(['job_title_id' => $this->position->id]);
    $this->applicant = $this->application->applicant;
    $this->tomorrow = now()->addDays(3);
});

function scheduleInput(array $overrides = []): array
{
    return [
        'date' => test()->tomorrow->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'mode' => 'face_to_face',
        'venue' => 'HR Office',
        'interviewer_id' => test()->staff->id,
        ...$overrides,
    ];
}

function deanAndSecretary(College $college): array
{
    $dean = Employee::factory()->create(['college_id' => $college->id]);
    CollegeDean::query()->create(['college_id' => $college->id, 'employee_id' => $dean->id]);
    $secretaryTitle = JobTitle::factory()->create(['college_id' => $college->id, 'title' => 'College Secretary']);
    $secretary = Employee::factory()->create(['job_title_id' => $secretaryTitle->id, 'college_id' => $college->id]);

    return [
        User::factory()->create(['employee_id' => $dean->id]),
        User::factory()->create(['employee_id' => $secretary->id]),
    ];
}

describe('scheduling the demo interview', function () {
    it('sets it, moves the application to Demo Interview, and notifies the applicant, the interviewer, the dean, and the College Secretary', function () {
        [$deanUser, $secretaryUser] = deanAndSecretary($this->college);

        $this->actingAs($this->admin)->post(route('recruitment.interviews.store', $this->application), scheduleInput())->assertSessionHas('success');

        $interview = Interview::query()->sole();

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::DemoInterview)
            ->and($interview->interviewer_id)->toBe($this->staff->id)
            ->and($interview->scheduled_by)->toBe($this->admin->id)
            ->and($interview->mode)->toBe(InterviewMode::FaceToFace)
            ->and($this->applicant->notifications()->first()->data['body'])->toContain('cannot cancel within 24 hours')
            ->and($this->staff->notifications()->count())->toBe(1)
            ->and($deanUser->notifications()->count())->toBe(1)
            ->and($secretaryUser->notifications()->count())->toBe(1)
            ->and($this->admin->notifications()->count())->toBe(0);
    });

    it('needs a venue for face to face and a link for online', function () {
        $this->actingAs($this->admin)->post(route('recruitment.interviews.store', $this->application), scheduleInput(['venue' => '']))->assertSessionHasErrors('venue');
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['mode' => 'online', 'venue' => '']))->assertSessionHasErrors('meeting_link');
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['mode' => 'online', 'meeting_link' => 'https://meet.example.test/x']))->assertSessionHasNoErrors();
    });

    it('refuses a past day, an end before the start, and an interviewer who is not HR', function () {
        $this->actingAs($this->admin);

        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('date');
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['end_time' => '09:00']))->assertSessionHasErrors('end_time');
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['interviewer_id' => employeeUser()->id]))->assertSessionHas('error');
        expect(Interview::query()->count())->toBe(0);
    });

    it('refuses an interviewer who is double-booked and suggests who is free', function () {
        $other = Application::factory()->create();
        Interview::factory()->create(['application_id' => $other->id, 'interviewer_id' => $this->staff->id, 'starts_at' => $this->tomorrow->copy()->setTime(10, 30), 'ends_at' => $this->tomorrow->copy()->setTime(11, 30)]);

        $this->actingAs($this->admin)->post(route('recruitment.interviews.store', $this->application), scheduleInput())
            ->assertSessionHas('conflicts', fn ($conflicts) => $conflicts['form'] === 'schedule' && str_contains($conflicts['messages'][0], 'already interviews') && collect($conflicts['free'])->pluck('id')->contains($this->admin->id) && ! collect($conflicts['free'])->pluck('id')->contains($this->staff->id));

        expect($this->application->interviews()->count())->toBe(0);

        // An overlap cannot be overridden: HR has to pick another time or another interviewer.
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput() + ['acknowledge_conflicts' => '1'])->assertSessionHas('conflicts');
        expect($this->application->interviews()->count())->toBe(0);
    });

    it('refuses to schedule a second interview while one is waiting', function () {
        Interview::factory()->create(['application_id' => $this->application->id]);

        $this->actingAs($this->admin)->post(route('recruitment.interviews.store', $this->application), scheduleInput())->assertSessionHas('error');
    });
});

describe('moving and changing the interview', function () {
    beforeEach(function () {
        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        $this->interview = Interview::factory()->create(['application_id' => $this->application->id, 'interviewer_id' => $this->staff->id, 'scheduled_by' => $this->admin->id, 'starts_at' => $this->tomorrow->copy()->setTime(10, 0), 'ends_at' => $this->tomorrow->copy()->setTime(11, 0)]);
    });

    it('moves it with a required reason and tells the applicant and the interviewer', function () {
        $day = now()->addDays(5)->toDateString();

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->patch(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'start_time' => '14:00', 'end_time' => '15:00', 'reason' => 'Room is taken'])->assertSessionHas('success');

        $fresh = $this->interview->fresh();

        expect($fresh->starts_at->toDateTimeString())->toBe("{$day} 14:00:00")
            ->and($this->applicant->notifications()->latest()->first()->data['body'])->toContain('Room is taken')
            ->and($this->staff->notifications()->count())->toBe(1)
            ->and($this->application->events()->where('type', 'demo_rescheduled')->sole()->details['reason'])->toBe('Room is taken');
    });

    it('keeps the length when dragged to a day on the calendar, and never to the past', function () {
        $day = now()->addDays(6)->toDateString();

        $this->actingAs($this->admin)->patchJson(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'reason' => 'Dragged'])->assertOk()->assertJson(['ok' => true]);
        expect($this->interview->fresh()->ends_at->toDateTimeString())->toBe("{$day} 11:00:00");

        $this->patchJson(route('recruitment.interviews.reschedule', $this->interview), ['date' => now()->subDay()->toDateString(), 'reason' => 'Past'])->assertUnprocessable();
    });

    it('refuses a drag that overlaps as JSON, even when an old form sends an acknowledgement', function () {
        $day = now()->addDays(6)->toDateString();
        Interview::factory()->create(['interviewer_id' => $this->staff->id, 'starts_at' => "{$day} 10:00:00", 'ends_at' => "{$day} 11:00:00"]);

        $this->actingAs($this->admin)->patchJson(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'reason' => 'Dragged'])
            ->assertStatus(409)->assertJson(['conflict' => true]);

        $this->patchJson(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'reason' => 'Dragged', 'acknowledge_conflicts' => true])->assertStatus(409);

        expect($this->interview->fresh()->starts_at->toDateString())->toBe($this->tomorrow->toDateString());
    });

    it('keeps the reschedule form open with the overlap warning after a refused move', function () {
        $day = now()->addDays(6)->toDateString();
        Interview::factory()->create(['interviewer_id' => $this->staff->id, 'starts_at' => "{$day} 14:00:00", 'ends_at' => "{$day} 15:00:00"]);

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.reschedule', $this->interview), ['date' => $day, 'start_time' => '14:00', 'end_time' => '15:00', 'reason' => 'Room is taken'])
            ->assertSessionHas('conflicts', fn ($conflicts) => $conflicts['form'] === 'reschedule');

        $this->get(route('recruitment.show', $this->application))
            ->assertSee('That time overlaps something, so nothing was changed.')
            ->assertSeeInOrder(['data-accordion-open', 'Reschedule</div>'], false);
    });

    it('cannot move a finished interview', function () {
        $this->interview->update(['status' => InterviewStatus::Completed]);

        $this->actingAs($this->admin)->patchJson(route('recruitment.interviews.reschedule', $this->interview), ['date' => now()->addDays(5)->toDateString(), 'reason' => 'x'])->assertUnprocessable();
    });

    it('changes the interviewer and tells both the old and the new one', function () {
        $new = hrStaff();

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.interviewer', $this->interview), ['interviewer_id' => $new->id])->assertSessionHas('success');

        expect($this->interview->fresh()->interviewer_id)->toBe($new->id)
            ->and($this->staff->notifications()->first()->data['title'])->toBe('You are no longer the interviewer')
            ->and($new->notifications()->first()->data['title'])->toBe('You are assigned to a demo interview');
    });

    it('flags a busy new interviewer', function () {
        $busy = hrStaff();
        Interview::factory()->create(['interviewer_id' => $busy->id, 'starts_at' => $this->interview->starts_at, 'ends_at' => $this->interview->ends_at]);

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.interviewer', $this->interview), ['interviewer_id' => $busy->id])
            ->assertSessionHas('conflicts', fn ($conflicts) => $conflicts['form'] === 'interviewer');
        expect($this->interview->fresh()->interviewer_id)->toBe($this->staff->id);
    });

    it('cancels it with a reason, puts the application back to Applied, and tells everyone concerned', function () {
        [$deanUser] = deanAndSecretary($this->college);

        $this->actingAs($this->admin)->post(route('recruitment.interviews.cancel', $this->interview), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('recruitment.interviews.cancel', $this->interview), ['reason' => 'Interviewer is ill'])->assertSessionHas('success');

        expect($this->interview->fresh()->status)->toBe(InterviewStatus::Cancelled)
            ->and($this->application->fresh()->status)->toBe(ApplicationStatus::Applied)
            ->and($this->applicant->notifications()->count())->toBe(1)
            ->and($deanUser->notifications()->count())->toBe(1);
    });

    it('marks it completed only after it started', function () {
        $this->actingAs($this->admin)->post(route('recruitment.interviews.complete', $this->interview))->assertSessionHas('error');

        $this->interview->update(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $this->post(route('recruitment.interviews.complete', $this->interview))->assertSessionHas('success');

        expect($this->interview->fresh()->status)->toBe(InterviewStatus::Completed);
    });
});

describe('the applicant side of the interview', function () {
    beforeEach(function () {
        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        $this->interview = Interview::factory()->create(['application_id' => $this->application->id, 'interviewer_id' => $this->staff->id, 'scheduled_by' => $this->admin->id, 'starts_at' => now()->addDays(4)->setTime(10, 0), 'ends_at' => now()->addDays(4)->setTime(11, 0)]);
    });

    it('asks to reschedule with a reason and the days they are free, which notifies the interviewer and whoever scheduled it', function () {
        $this->actingAs($this->applicant)->post(route('applicant.interviews.reschedule', [$this->application, $this->interview]), ['reason' => '', 'available_days' => ''])->assertSessionHasErrors(['reason', 'available_days']);
        $this->post(route('applicant.interviews.reschedule', [$this->application, $this->interview]), ['reason' => 'Class that day', 'available_days' => 'Fridays'])->assertSessionHas('success');

        expect($this->interview->fresh()->reschedule_reason)->toBe('Class that day')
            ->and($this->interview->fresh()->reschedule_available_days)->toBe('Fridays')
            ->and($this->staff->notifications()->count())->toBe(1)
            ->and($this->admin->notifications()->count())->toBe(1);

        $this->actingAs($this->admin)->get(route('recruitment.show', $this->application))->assertSee('The applicant asked to reschedule')->assertSee('Fridays');
    });

    it('clears the request when HR moves the interview', function () {
        $this->actingAs($this->applicant)->post(route('applicant.interviews.reschedule', [$this->application, $this->interview]), ['reason' => 'x', 'available_days' => 'y']);

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.reschedule', $this->interview), ['date' => now()->addDays(8)->toDateString(), 'reason' => 'Requested']);

        expect($this->interview->fresh()->reschedule_requested_at)->toBeNull();
    });

    it('cancels the application, which is flagged Cancelled and cancels the interview', function () {
        $this->actingAs($this->applicant)->post(route('applicant.applications.cancel', $this->application), ['reason' => 'Found another job'])->assertSessionHas('success');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Cancelled)
            ->and($this->interview->fresh()->status)->toBe(InterviewStatus::Cancelled)
            ->and($this->staff->notifications()->count())->toBe(1);
    });

    it('cannot cancel within 24 hours of the demo interview', function () {
        $this->interview->update(['starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11)]);

        $this->actingAs($this->applicant)->post(route('applicant.applications.cancel', $this->application))->assertSessionHas('error');
        $this->get(route('applicant.applications.show', $this->application))->assertSee('can no longer be cancelled');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::DemoInterview);
    });

    it('shows the interview and the 24-hour notice on the application page and the calendar', function () {
        $this->actingAs($this->applicant)->get(route('applicant.applications.show', $this->application))->assertSee('Your demo interview')->assertSee('HR Office')->assertSee('cannot cancel your application within 24 hours');
        $this->get(route('applicant.calendar'))->assertSee('Instructor I')->assertSee('Upcoming');
    });

    it('does not show another applicant\'s application', function () {
        $this->actingAs(applicantUser())->get(route('applicant.applications.show', $this->application))->assertNotFound();
    });
});

describe('rejecting', function () {
    it('needs a reason, tells the applicant, and cancels a waiting interview', function () {
        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        $interview = Interview::factory()->create(['application_id' => $this->application->id]);

        $this->actingAs($this->staff)->post(route('recruitment.reject', $this->application), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('recruitment.reject', $this->application), ['reason' => 'Not a fit'])->assertSessionHas('success');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Rejected)
            ->and($interview->fresh()->status)->toBe(InterviewStatus::Cancelled)
            ->and($this->applicant->notifications()->first()->data['body'])->toContain('Not a fit');
    });

    it('is final', function () {
        $this->application->update(['status' => ApplicationStatus::Hired]);

        $this->actingAs($this->admin)->post(route('recruitment.reject', $this->application), ['reason' => 'x'])->assertSessionHas('error');
    });

    it('shows the reopen option instead of a next step', function () {
        $this->application->update(['status' => ApplicationStatus::Rejected, 'status_reason' => 'Not a fit']);

        $this->actingAs($this->staff)->get(route('recruitment.show', $this->application))
            ->assertSee('Reopen application')
            ->assertSee('Not a fit')
            ->assertDontSee('Next step');
    });

    it('reopens a rejected application, tells the applicant, and lets HR schedule a new demo interview', function () {
        $this->application->update(['status' => ApplicationStatus::Rejected, 'status_reason' => 'Not a fit']);

        $this->actingAs($this->staff)->post(route('recruitment.reopen', $this->application))->assertSessionHas('success');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Applied)
            ->and($this->application->fresh()->status_reason)->toBeNull()
            ->and($this->application->events()->where('type', 'reopened')->exists())->toBeTrue()
            ->and($this->applicant->notifications()->first()->data['title'])->toBe('Application reopened');

        $this->get(route('recruitment.show', $this->application))
            ->assertSee('Schedule the demo interview')
            ->assertDontSee('Reopen application');

        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput())->assertSessionHas('success');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::DemoInterview);
    });

    it('cannot reopen an application that is not rejected', function () {
        $this->actingAs($this->admin)->post(route('recruitment.reopen', $this->application))->assertSessionHas('error');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Applied);
    });
});

describe('the offer', function () {
    function readyForOffer(Application $application): void
    {
        $application->update(['status' => ApplicationStatus::DemoInterview]);
        Interview::factory()->create(['application_id' => $application->id, 'status' => InterviewStatus::Completed, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
    }

    function offerInput(array $overrides = []): array
    {
        return ['employment_type' => 'full_time', 'start_date' => now()->addWeek()->toDateString(), ...$overrides];
    }

    it('is issued by an HR Admin after the interview, with a summary, terms, benefits, and no end for full-time', function () {
        readyForOffer($this->application);
        $benefit = Benefit::query()->create(['name' => 'GSIS', 'description' => 'x']);

        $this->actingAs($this->admin)->post(route('recruitment.offers.store', $this->application), offerInput(['benefit_ids' => [$benefit->id]]))->assertSessionHas('success');

        $offer = Offer::query()->sole();

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Offered)
            ->and($offer->end_date)->toBeNull()
            ->and($offer->summary['position'])->toBe('Instructor I')
            ->and($offer->summary['benefits'])->toBe(['GSIS'])
            ->and($offer->terms)->toContain('End of contract. A full-time appointment has no fixed end date')
            ->and($this->applicant->notifications()->first()->data['title'])->toBe('You have a job offer');
    });

    it('refuses an end of contract on a full-time offer, and needs one on a part-time offer', function () {
        readyForOffer($this->application);
        $this->actingAs($this->admin);

        $this->post(route('recruitment.offers.store', $this->application), offerInput(['end_date' => now()->addMonths(6)->toDateString()]))->assertSessionHas('error');
        $this->post(route('recruitment.offers.store', $this->application), offerInput(['employment_type' => 'part_time', 'weekly_hours' => 20]))->assertSessionHas('error');
        expect(Offer::query()->count())->toBe(0);

        $this->post(route('recruitment.offers.store', $this->application), offerInput(['employment_type' => 'part_time', 'weekly_hours' => 20, 'start_date' => '2026-12-01', 'contract_term' => 'second_semester', 'academic_year' => 2026]))->assertSessionHas('success');

        expect(Offer::query()->sole()->end_date->toDateString())->toBe('2027-03-31');
    });

    it('is closed to HR Staff and to an application that is not past the interview', function () {
        readyForOffer($this->application);

        $this->actingAs($this->staff)->post(route('recruitment.offers.store', $this->application), offerInput())->assertForbidden();

        $fresh = Application::factory()->create();
        $this->actingAs($this->admin)->post(route('recruitment.offers.store', $fresh), offerInput())->assertSessionHas('error');
    });

    it('is accepted by the applicant typing their name, which tells the HR Admins', function () {
        readyForOffer($this->application);
        $this->actingAs($this->admin)->post(route('recruitment.offers.store', $this->application), offerInput());
        $offer = Offer::query()->sole();
        $name = $this->applicant->applicantProfile;

        $this->actingAs($this->applicant)->get(route('applicant.applications.show', $this->application))->assertSee('Summary of Position')->assertSee('Terms and Conditions');
        $this->post(route('applicant.offers.accept', [$this->application, $offer]), ['signed_name' => 'Wrong Person', 'agree' => '1'])->assertSessionHas('error');
        $this->post(route('applicant.offers.accept', [$this->application, $offer]), ['signed_name' => "{$name->first_name} {$name->last_name}", 'agree' => '1'])->assertSessionHas('success');

        expect($offer->fresh()->status)->toBe(OfferStatus::Accepted)
            ->and($this->application->fresh()->status)->toBe(ApplicationStatus::Offered)
            ->and($this->admin->notifications()->where('data->title', 'Offer accepted')->count())->toBe(1);
    });

    it('is declined by the applicant, which flags the application Cancelled', function () {
        readyForOffer($this->application);
        $this->actingAs($this->admin)->post(route('recruitment.offers.store', $this->application), offerInput());

        $this->actingAs($this->applicant)->post(route('applicant.offers.decline', [$this->application, Offer::query()->sole()]), ['reason' => 'Salary'])->assertSessionHas('success');

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Cancelled);
    });
});

describe('hiring', function () {
    function acceptedOffer(Application $application, User $admin, array $overrides = []): Offer
    {
        readyForOffer($application);
        test()->actingAs($admin)->post(route('recruitment.offers.store', $application), offerInput($overrides));
        $profile = $application->applicant->applicantProfile;
        test()->actingAs($application->applicant)->post(route('applicant.offers.accept', [$application, Offer::query()->latest('id')->first()]), ['signed_name' => "{$profile->first_name} {$profile->last_name}", 'agree' => '1']);

        return Offer::query()->latest('id')->first();
    }

    it('creates the employee and the employee login from the applicant, keeps the applicant account, and emails only the personal address', function () {
        Mail::fake();
        $benefit = Benefit::query()->create(['name' => 'GSIS', 'description' => 'x']);
        $offer = acceptedOffer($this->application, $this->admin, ['start_date' => '2026-11-02', 'benefit_ids' => [$benefit->id]]);
        $profile = $this->applicant->applicantProfile;

        $this->actingAs($this->admin)->post(route('recruitment.hire', $this->application))->assertRedirect();

        $employee = Employee::query()->where('last_name', $profile->last_name)->sole();
        $account = User::query()->where('employee_id', $employee->id)->sole();

        expect($this->application->fresh()->status)->toBe(ApplicationStatus::Hired)
            ->and($this->application->fresh()->employee_id)->toBe($employee->id)
            ->and($employee->employee_no)->toMatch('/^26-\d{5}$/')
            ->and($employee->first_name)->toBe($profile->first_name)
            ->and($employee->personal_email)->toBe($this->applicant->email)
            ->and($employee->official_email)->toEndWith('@plpasig.edu.ph')
            ->and($employee->job_title_id)->toBe($this->position->id)
            ->and($employee->hired_on->toDateString())->toBe('2026-11-02')
            ->and($employee->benefits()->pluck('name')->all())->toBe(['GSIS'])
            ->and($account->role)->toBe(Role::Employee)
            ->and($account->must_change_password)->toBeTrue()
            ->and($account->onboarding_completed_at)->toBeNull()
            ->and(Hash::check($employee->employee_no, $account->password))->toBeTrue()
            ->and($this->position->fresh()->openings)->toBe(1)
            ->and(User::query()->find($this->applicant->id)->is_active)->toBeTrue();

        Mail::assertSent(HiredMail::class, fn (HiredMail $mail) => $mail->hasTo($this->applicant->email) && ! $mail->hasTo($employee->official_email));
    });

    it('never emails an official PLP address', function () {
        Mail::fake();

        expect(app(PersonalMail::class)->send('santos_maria@plpasig.edu.ph', new HiredMail('X', 'Y', 'a', 'b', now())))->toBeFalse();
        Mail::assertNothingSent();
    });

    it('creates a part-time employee with the contract end and weekly hours', function () {
        Mail::fake();
        acceptedOffer($this->application, $this->admin, ['employment_type' => 'part_time', 'weekly_hours' => 15, 'start_date' => '2026-12-01', 'contract_term' => 'second_semester', 'academic_year' => 2026]);

        $this->actingAs($this->admin)->post(route('recruitment.hire', $this->application));

        $employee = Employee::query()->sole();

        expect($employee->employment_type)->toBe(EmploymentType::PartTime)
            ->and($employee->weekly_hours)->toBe(15)
            ->and($employee->contract_ends_on->toDateString())->toBe('2027-03-31');
    });

    it('needs an accepted offer and an HR Admin', function () {
        readyForOffer($this->application);
        $this->actingAs($this->admin)->post(route('recruitment.offers.store', $this->application), offerInput());

        $this->post(route('recruitment.hire', $this->application))->assertSessionHas('error');
        $this->actingAs($this->staff)->post(route('recruitment.hire', $this->application))->assertForbidden();
        expect(Employee::query()->count())->toBe(0);
    });

    it('lets the hired applicant see how to sign in, with the employee ID as the first password', function () {
        Mail::fake();
        acceptedOffer($this->application, $this->admin);
        $this->actingAs($this->admin)->post(route('recruitment.hire', $this->application));
        $employee = Employee::query()->sole();

        $this->actingAs($this->applicant)->get(route('applicant.applications.show', $this->application))
            ->assertSee('You are hired')
            ->assertSee($employee->official_email)
            ->assertSee($employee->employee_no);
    });
});

describe('the Recruitment page', function () {
    it('lists the applications with the pipeline including Cancelled and without a plain Interview stage', function () {
        Application::factory()->create(['status' => ApplicationStatus::Cancelled]);

        $this->actingAs($this->staff)->get(route('recruitment.index'))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('Demo Interview')
            ->assertDontSee('>Interview<', escape: false);

        $this->get(route('recruitment.index', ['status' => 'cancelled']))->assertOk()->assertDontSee($this->applicant->applicantProfile->last_name);
    });

    it('filters the applications by the date range they were filed in', function () {
        $this->application->update(['applied_on' => '2026-05-10']);
        $other = Application::factory()->create(['applied_on' => '2026-08-20']);
        $filed = fn (array $range) => $this->actingAs($this->staff)->get(route('recruitment.index', $range))->viewData('applications')->pluck('id')->all();

        expect($filed(['applied_from' => '2026-05-01', 'applied_to' => '2026-05-31']))->toBe([$this->application->id])
            ->and($filed(['applied_from' => '2026-08-01']))->toBe([$other->id])
            ->and($filed(['applied_to' => '2026-08-31']))->toContain($this->application->id, $other->id);

        $this->get(route('recruitment.index', ['applied_from' => '2026-06-01', 'applied_to' => '2026-05-01']))->assertSessionHasErrors('applied_to');
    });

    it('opens an application with the documents shown at once and the applicant information collapsible', function () {
        $this->application->documents()->create(['uuid' => (string) Str::uuid(), 'kind' => 'resume', 'original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size' => 4, 'sha256' => 'x', 'contents' => '%PDF']);

        $this->actingAs($this->staff)->get(route('recruitment.show', $this->application))
            ->assertOk()
            ->assertSee('Documents')
            ->assertSee('cv.pdf')
            ->assertSee('<iframe', escape: false)
            ->assertSee('<div class="fs-5 fw-semibold" data-accordion-header>Applicant information</div>', escape: false)
            ->assertSee('Schedule the demo interview');
    });

    it('lets only the HR Admin download the report, and logs it', function () {
        $this->actingAs($this->staff)->get(route('recruitment.report'))->assertForbidden();

        $response = $this->actingAs($this->admin)->get(route('recruitment.report'));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect(AuditLog::query()->where('action', AuditAction::Export)->count())->toBe(1);
    });
});

describe('working hours and the next step panel', function () {
    it('refuses an interview that starts before 8 AM or ends after 5 PM, when scheduling and when moving', function () {
        $this->actingAs($this->admin);

        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['start_time' => '07:30', 'end_time' => '08:30']))->assertSessionHas('error');
        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['start_time' => '16:30', 'end_time' => '17:30']))->assertSessionHas('error');
        expect(Interview::query()->count())->toBe(0);

        $this->post(route('recruitment.interviews.store', $this->application), scheduleInput(['start_time' => '08:00', 'end_time' => '17:00']))->assertSessionHas('success');

        $interview = Interview::query()->sole();
        $this->patch(route('recruitment.interviews.reschedule', $interview), ['date' => now()->addDays(5)->toDateString(), 'start_time' => '17:00', 'end_time' => '18:00', 'reason' => 'x'])->assertSessionHas('error');
        $this->patch(route('recruitment.interviews.reschedule', $interview), ['date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'reason' => 'x'])->assertSessionHas('success');
    });

    it('shows the schedule form only while the application is Applied, not after the interview is finished', function () {
        $this->actingAs($this->admin)->get(route('recruitment.show', $this->application))->assertSee('Schedule the demo interview');

        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        Interview::factory()->create(['application_id' => $this->application->id, 'status' => InterviewStatus::Completed, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

        $this->get(route('recruitment.show', $this->application))->assertDontSee('Schedule the demo interview')->assertSee('The demo interview is done');
    });

    it('marks the venue and meeting link as dependent on the interview type, and the offer form part-time fields as dependent on the employment type', function () {
        $this->actingAs($this->admin)->get(route('recruitment.show', $this->application))
            ->assertSee('data-mode-field="face_to_face"', escape: false)
            ->assertSee('data-mode-field="online"', escape: false);

        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        Interview::factory()->create(['application_id' => $this->application->id, 'status' => InterviewStatus::Completed, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

        $this->get(route('recruitment.show', $this->application))->assertSee('data-part-time-fields', escape: false)->assertDontSee('Or an exact end date');
    });

    it('filters demo interviews by not yet, finished, and none', function () {
        $upcoming = Application::factory()->create(['status' => ApplicationStatus::DemoInterview]);
        Interview::factory()->create(['application_id' => $upcoming->id]);
        $finished = Application::factory()->create(['status' => ApplicationStatus::DemoInterview]);
        Interview::factory()->create(['application_id' => $finished->id, 'status' => InterviewStatus::Completed, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

        $ids = fn (string $state) => collect($this->actingAs($this->admin)->get(route('recruitment.index', ['interview' => $state]))->viewData('applications')->items())->pluck('id')->all();

        expect($ids('upcoming'))->toBe([$upcoming->id])
            ->and($ids('finished'))->toBe([$finished->id])
            ->and($ids('none'))->toBe([$this->application->id]);
    });
});

describe('editing a sent offer', function () {
    function pendingOfferFor(Application $application, User $admin): Offer
    {
        readyForOffer($application);
        test()->actingAs($admin)->post(route('recruitment.offers.store', $application), offerInput());

        return Offer::query()->sole();
    }

    it('lets the HR Admin change a waiting offer, rebuilds its summary and terms, and tells the applicant', function () {
        $offer = pendingOfferFor($this->application, $this->admin);
        $benefit = Benefit::query()->create(['name' => 'PhilHealth', 'description' => 'x']);

        $this->get(route('recruitment.show', $this->application))->assertSee('Edit the offer');
        $this->put(route('recruitment.offers.update', [$this->application, $offer]), [
            'employment_type' => 'part_time', 'weekly_hours' => 12, 'start_date' => '2026-12-01', 'contract_term' => 'second_semester', 'academic_year' => 2026, 'benefit_ids' => [$benefit->id],
        ])->assertSessionHas('success');

        $fresh = $offer->fresh();

        expect($fresh->employment_type)->toBe(EmploymentType::PartTime)
            ->and($fresh->weekly_hours)->toBe(12)
            ->and($fresh->end_date->toDateString())->toBe('2027-03-31')
            ->and($fresh->summary['benefits'])->toBe(['PhilHealth'])
            ->and($fresh->terms)->toContain('ending on March 31, 2027')
            ->and($this->applicant->notifications()->where('data->title', 'Your job offer was updated')->count())->toBe(1)
            ->and($this->application->events()->where('type', 'offer_updated')->count())->toBe(1);
    });

    it('keeps HR written terms and is closed to HR Staff', function () {
        $offer = pendingOfferFor($this->application, $this->admin);

        $this->put(route('recruitment.offers.update', [$this->application, $offer]), ['employment_type' => 'full_time', 'start_date' => now()->addWeek()->toDateString(), 'terms' => 'My own terms.'])->assertSessionHas('success');
        expect($offer->fresh()->terms)->toBe('My own terms.');

        $this->actingAs($this->staff)->put(route('recruitment.offers.update', [$this->application, $offer]), ['employment_type' => 'full_time', 'start_date' => now()->addWeek()->toDateString()])->assertForbidden();
    });

    it('cannot be edited after the applicant answered it', function () {
        $offer = pendingOfferFor($this->application, $this->admin);
        $name = $this->applicant->applicantProfile;
        $this->actingAs($this->applicant)->post(route('applicant.offers.accept', [$this->application, $offer]), ['signed_name' => "{$name->first_name} {$name->last_name}", 'agree' => '1']);

        $this->actingAs($this->admin)->put(route('recruitment.offers.update', [$this->application, $offer]), ['employment_type' => 'full_time', 'start_date' => now()->addWeek()->toDateString()])->assertSessionHas('error');
    });
});

describe('answering a reschedule request', function () {
    it('needs no reason when the applicant asked, but needs one when HR moves it on its own', function () {
        $this->application->update(['status' => ApplicationStatus::DemoInterview]);
        $interview = Interview::factory()->create(['application_id' => $this->application->id, 'interviewer_id' => $this->staff->id, 'starts_at' => now()->addDays(4)->setTime(10, 0), 'ends_at' => now()->addDays(4)->setTime(11, 0)]);
        $move = ['date' => now()->addDays(6)->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00'];

        $this->actingAs($this->admin)->patch(route('recruitment.interviews.reschedule', $interview), $move)->assertSessionHasErrors('reason');

        $interview->update(['reschedule_requested_at' => now(), 'reschedule_reason' => 'Class', 'reschedule_available_days' => 'Fridays']);
        $this->patch(route('recruitment.interviews.reschedule', $interview), $move)->assertSessionHas('success');

        expect($interview->fresh()->starts_at->toDateString())->toBe($move['date'])
            ->and($interview->fresh()->reschedule_requested_at)->toBeNull();
    });
});
