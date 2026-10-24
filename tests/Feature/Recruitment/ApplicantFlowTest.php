<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\College;
use App\Models\JobTitle;
use App\Models\StoredDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function openPosition(array $state = []): JobTitle
{
    return JobTitle::factory()->create([
        'title' => 'Instructor I',
        'is_published' => true,
        'openings' => 2,
        'employment_type' => 'full_time',
        'location' => 'Pasig City',
        'summary' => 'Teaches courses.',
        'description' => 'Teaches undergraduate courses.',
        'responsibilities' => ['Teach'],
        'qualifications' => ['Master’s degree'],
        'requirements' => ['Resume'],
        ...$state,
    ]);
}

function applicationInput(JobTitle $position, array $overrides = []): array
{
    return [
        'job_title_id' => $position->id,
        'education' => [['level' => 'Bachelor', 'school' => 'PLP', 'course' => 'BSEd', 'year_graduated' => '2018']],
        'work_history' => [['employer' => 'Sample School', 'position' => 'Teacher', 'from' => '2019-06', 'to' => 'Present']],
        'resume' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
        'portfolio_url' => 'https://example.test/portfolio',
        ...$overrides,
    ];
}

describe('the public careers website', function () {
    it('lists open positions from the database with colleges wording and no benefits', function () {
        $college = College::factory()->create(['name' => 'College of Education']);
        openPosition(['title' => 'Instructor I', 'college_id' => $college->id]);
        JobTitle::factory()->create(['title' => 'Hidden Draft', 'is_published' => false, 'openings' => 3]);
        JobTitle::factory()->create(['title' => 'Closed Post', 'is_published' => true, 'openings' => 0]);

        $this->get(route('careers.index'))
            ->assertOk()
            ->assertSee('Instructor I')
            ->assertSee('Openings in all')
            ->assertDontSee('Hidden Draft')
            ->assertDontSee('Closed Post')
            ->assertDontSee('Departments')
            ->assertDontSee('Benefits of working');
    });

    it('shows a position without a benefits section and with its part-time contract term', function () {
        $position = openPosition(['employment_type' => 'part_time', 'contract_term' => 'second_semester', 'academic_year' => 2026]);

        $this->get(route('careers.show', $position->id))
            ->assertOk()
            ->assertSee('2nd Semester, AY 2026-2027')
            ->assertDontSee('Benefits');
    });

    it('does not show an unpublished position', function () {
        $this->get(route('careers.show', JobTitle::factory()->create(['is_published' => false])->id))->assertNotFound();
    });

    it('sends the root address to login and keeps careers public', function () {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('careers.index'))->assertOk();
    });
});

describe('creating an applicant account', function () {
    it('takes a separate first, middle, and last name, signs in, and asks for the profile', function () {
        $this->post(route('applicant.register.store'), [
            'first_name' => 'Maria', 'middle_name' => 'Clara', 'last_name' => 'Santos', 'email' => 'maria@example.test',
            'password' => 'Tr1cky-Horse-Battery', 'password_confirmation' => 'Tr1cky-Horse-Battery', 'terms' => '1',
        ])->assertRedirect(route('applicant.profile'));

        $user = User::query()->where('email', 'maria@example.test')->sole();

        expect($user->role)->toBe(Role::Applicant)
            ->and($user->applicantProfile->first_name)->toBe('Maria')
            ->and($user->applicantProfile->middle_name)->toBe('Clara')
            ->and($user->applicantProfile->last_name)->toBe('Santos')
            ->and($user->name)->toBe('Maria Clara Santos');
        $this->assertAuthenticatedAs($user);
    });

    it('refuses a weak password, a missing terms box, a taken email, and a PLP staff address', function (array $override, string $field) {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('applicant.register.store'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'new@example.test',
            'password' => 'Tr1cky-Horse-Battery', 'password_confirmation' => 'Tr1cky-Horse-Battery', 'terms' => '1', ...$override,
        ])->assertSessionHasErrors($field);
    })->with([
        'an easy password' => [['password' => 'password1', 'password_confirmation' => 'password1'], 'password'],
        'no terms' => [['terms' => null], 'terms'],
        'a taken email' => [['email' => 'taken@example.test'], 'email'],
        'a plpasig address' => [['email' => 'santos_maria@plpasig.edu.ph'], 'email'],
        'no first name' => [['first_name' => ''], 'first_name'],
    ]);
});

describe('the one-time profile', function () {
    it('holds only personal and contact information', function () {
        $user = User::factory()->applicant()->create();
        $user->applicantProfile->update(['birthdate' => null, 'sex' => null, 'marital_status' => null, 'phone' => null, 'address' => null]);

        $this->actingAs($user)->get(route('applicant.profile'))
            ->assertOk()
            ->assertSee('Personal Information')
            ->assertSee('Contact Information')
            ->assertDontSee('Education')
            ->assertDontSee('Work History')
            ->assertDontSee('Emergency')
            ->assertDontSee('TIN');

        $this->put(route('applicant.profile.update'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'birthdate' => '1995-04-02', 'sex' => 'Female', 'marital_status' => 'Single',
            'phone' => '09171234567', 'address' => 'Pasig City',
        ])->assertRedirect(route('applicant.profile'));

        expect($user->fresh()->applicantProfile->isComplete())->toBeTrue();
    });

    it('sends an applicant with an incomplete profile to complete it before applying', function () {
        $user = User::factory()->applicant()->create();
        $user->applicantProfile->update(['phone' => null]);

        $this->actingAs($user)->get(route('applicant.apply', ['position' => openPosition()->id]))->assertRedirect();
    });
});

describe('applying', function () {
    it('files the application with the resume stored in the database, the position automatic, and tells HR', function () {
        $hr = hrStaff();
        $position = openPosition();
        $user = applicantUser();

        $this->actingAs($user)->get(route('applicant.apply', ['position' => $position->id]))->assertOk()->assertSee('Position Applied For')->assertSee('Instructor I');
        $this->post(route('applicant.applications.store'), applicationInput($position, ['portfolio' => UploadedFile::fake()->create('port.pdf', 50, 'application/pdf')]))
            ->assertRedirect();

        $application = Application::query()->sole();

        expect($application->status)->toBe(ApplicationStatus::Applied)
            ->and($application->job_title_id)->toBe($position->id)
            ->and($application->education)->toHaveCount(1)
            ->and(StoredDocument::query()->where('kind', 'resume')->count())->toBe(1)
            ->and(StoredDocument::query()->where('kind', 'portfolio')->count())->toBe(1)
            ->and($application->events->first()->type)->toBe('applied')
            ->and($hr->notifications()->count())->toBe(1);
    });

    it('requires a resume file and an education entry, and refuses a second application for the same position', function () {
        $position = openPosition();
        $user = applicantUser();

        $this->actingAs($user)->post(route('applicant.applications.store'), applicationInput($position, ['resume' => null]))->assertSessionHasErrors('resume');
        $this->post(route('applicant.applications.store'), applicationInput($position, ['education' => [['school' => '']]]))->assertSessionHasErrors('education');
        $this->post(route('applicant.applications.store'), applicationInput($position))->assertSessionHasNoErrors();
        $this->post(route('applicant.applications.store'), applicationInput($position))->assertSessionHas('error');

        expect(Application::query()->count())->toBe(1);
    });

    it('refuses a closed position', function () {
        $position = openPosition(['openings' => 0]);

        $this->actingAs(applicantUser())->post(route('applicant.applications.store'), applicationInput($position))->assertSessionHas('error');
    });

    it('lets only the owner and HR open the stored documents', function () {
        $position = openPosition();
        $owner = applicantUser();
        $this->actingAs($owner)->post(route('applicant.applications.store'), applicationInput($position));
        $document = StoredDocument::query()->sole();

        $this->get(route('documents.show', $document->uuid))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(hrStaff())->get(route('documents.show', $document->uuid))->assertOk();
        $this->actingAs(applicantUser())->get(route('documents.show', $document->uuid))->assertNotFound();
        $this->actingAs(employeeUser())->get(route('documents.show', $document->uuid))->assertNotFound();
    });
});
