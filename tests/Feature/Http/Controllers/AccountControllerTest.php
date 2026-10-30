<?php

use App\Enums\AuditAction;
use App\Enums\EmploymentType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the accounts page to the HR Admin only', function () {
    $this->actingAs(hrAdmin())->get(route('accounts.index'))->assertOk()->assertSee('Create an account');

    $this->actingAs(hrStaff())->get(route('accounts.index'))->assertForbidden();
    $this->actingAs(employeeUser())->get(route('accounts.index'))->assertForbidden();
});

it('lists 15 accounts a page and keeps the search and filters on the other pages', function () {
    $admin = hrAdmin();
    User::factory()->count(16)->sequence(fn ($sequence) => ['name' => sprintf('Pager %02d', $sequence->index + 1)])->create();

    $this->actingAs($admin)->get(route('accounts.index', ['q' => 'Pager']))
        ->assertOk()
        ->assertViewHas('accounts', fn ($accounts) => $accounts->count() === 15 && $accounts->total() === 16)
        ->assertSee('Pager 15')
        ->assertDontSee('Pager 16')
        ->assertSee(route('accounts.index', ['q' => 'Pager', 'page' => 2]));

    $this->get(route('accounts.index', ['q' => 'Pager', 'page' => 2]))->assertSee('Pager 16')->assertDontSee('Pager 15');
});

describe('creating an account', function () {
    it('issues a login whose first password is the employee number, with first-login setup pending', function () {
        $head = hrAdmin();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($head)->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'employee']);

        $user = User::query()->where('email', $employee->official_email)->sole();
        $issued = session('issued_password');

        $response->assertRedirect()->assertSessionHas('success');
        expect($user->employee_id)->toBe($employee->id)
            ->and($user->role)->toBe(Role::Employee)
            ->and($user->must_change_password)->toBeTrue()
            ->and($user->name)->toBe($employee->full_name)
            ->and($user->temp_password_expires_at)->toBeNull()
            ->and($user->onboarding_completed_at)->toBeNull()
            ->and($issued['password'])->toBe($employee->employee_no)
            ->and(Hash::check($issued['password'], $user->password))->toBeTrue()
            ->and($user->password)->not->toContain($issued['password']);
    });

    it('lets the issued password sign in and then forces a change', function () {
        $employee = Employee::factory()->create();
        $this->actingAs(hrAdmin())->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'hr_staff']);
        $password = session('issued_password')['password'];
        auth()->logout();

        $this->post(route('login.store'), ['email' => $employee->official_email, 'password' => $password])
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    });

    it('audits the new account without recording the password', function () {
        $head = hrAdmin();
        $employee = Employee::factory()->create();

        $this->actingAs($head)->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'employee']);

        $entry = AuditLog::query()->where('action', AuditAction::Create)->where('entity_type', (new User)->getMorphClass())->latest('id')->first();

        expect($entry->user_id)->toBe($head->id)
            ->and($entry->changes)->toEqual(['role' => 'employee', 'email' => $employee->official_email])
            ->and(json_encode($entry->changes))->not->toContain(session('issued_password')['password']);
    });

    it('makes a part-time employee full-time when the new account has an HR role', function () {
        $employee = Employee::factory()->partTime()->create([
            'weekly_hours' => 18,
            'contract_ends_on' => now()->addMonths(3)->toDateString(),
        ]);

        $this->actingAs(hrAdmin())
            ->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'hr_staff'])
            ->assertSessionHas('success');

        $fresh = $employee->fresh();

        expect($fresh->employment_type)->toBe(EmploymentType::FullTime)
            ->and($fresh->weekly_hours)->toBe(Employee::FULL_TIME_HOURS)
            ->and($fresh->contract_ends_on)->toBeNull();
    });

    it('leaves a part-time employee part-time when the new account is not an HR role', function () {
        $end = now()->addMonths(3)->toDateString();
        $employee = Employee::factory()->partTime()->create(['weekly_hours' => 18, 'contract_ends_on' => $end]);

        $this->actingAs(hrAdmin())
            ->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'employee'])
            ->assertSessionHas('success');

        $fresh = $employee->fresh();

        expect($fresh->employment_type)->toBe(EmploymentType::PartTime)
            ->and($fresh->weekly_hours)->toBe(18)
            ->and($fresh->contract_ends_on->toDateString())->toBe($end);
    });

    it('refuses a second account for the same employee', function () {
        $employee = Employee::factory()->create();
        User::factory()->create(['employee_id' => $employee->id, 'email' => $employee->official_email]);

        $this->actingAs(hrAdmin())
            ->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'employee'])
            ->assertSessionHas('error');

        expect(User::query()->where('employee_id', $employee->id)->count())->toBe(1);
    });

    it('rejects an unknown employee or role', function () {
        $this->actingAs(hrAdmin())
            ->post(route('accounts.store'), ['employee_id' => 999999, 'role' => 'superuser'])
            ->assertSessionHasErrors(['employee_id', 'role']);
    });

    it('forbids HR Staff and employees', function (Closure $makeUser) {
        $employee = Employee::factory()->create();

        $this->actingAs($makeUser())
            ->post(route('accounts.store'), ['employee_id' => $employee->id, 'role' => 'employee'])
            ->assertForbidden();

        expect(User::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
    })->with([
        'hr staff' => [fn () => hrStaff()],
        'employee' => [fn () => employeeUser()],
    ]);
});

describe('resetting a password', function () {
    it('issues a new temporary password, ends the old one, and requires a change', function () {
        $target = User::factory()->state(['password' => 'old-password-long-enough'])->create();
        $target->forceFill(['remember_token' => 'old-token'])->save();

        $this->actingAs(hrAdmin())->post(route('accounts.reset-password', $target))->assertSessionHas('issued_password');

        $target->refresh();
        $new = session('issued_password')['password'];

        expect(Hash::check('old-password-long-enough', $target->password))->toBeFalse()
            ->and(Hash::check($new, $target->password))->toBeTrue()
            ->and($target->must_change_password)->toBeTrue()
            ->and($target->temp_password_expires_at)->not->toBeNull()
            ->and($target->remember_token)->not->toBe('old-token');
    });

    it('shows the new temporary password once, masked, in a window on the accounts page', function () {
        $target = User::factory()->create();

        $page = $this->actingAs(hrAdmin())->from(route('accounts.index'))->followingRedirects()->post(route('accounts.reset-password', $target))
            ->assertSee('id="issued-password-modal"', escape: false)
            ->assertSee('Copy Password');
        preg_match('/<input type="password"[^>]*id="issued-password" value="([^"]+)"/', $page->getContent(), $match);

        expect(Hash::check(html_entity_decode($match[1] ?? '', ENT_QUOTES), $target->refresh()->password))->toBeTrue();
        $this->get(route('accounts.index'))->assertDontSee('issued-password-modal')->assertDontSee($match[1]);
    });

    it('lets one HR Admin reset another HR Admin, but nobody reset their own account here', function () {
        $first = hrAdmin();
        $second = hrAdmin();

        $this->actingAs($first)->post(route('accounts.reset-password', $second))->assertSessionHas('issued_password');
        $this->actingAs($first)->post(route('accounts.reset-password', $first))->assertForbidden();
    });

    it('is forbidden to HR Staff and employees', function (Closure $makeUser) {
        $this->actingAs($makeUser())->post(route('accounts.reset-password', User::factory()->create()))->assertForbidden();
    })->with([
        'hr staff' => [fn () => hrStaff()],
        'employee' => [fn () => employeeUser()],
    ]);
});

describe('deactivating an account', function () {
    it('blocks sign-in, audits it, and can be undone', function () {
        $head = hrAdmin();
        $target = User::factory()->state(['password' => 'correct-horse-battery'])->create();

        $this->actingAs($head)->post(route('accounts.deactivate', $target))->assertSessionHas('success');

        expect($target->fresh()->is_active)->toBeFalse()
            ->and(AuditLog::query()->where('entity_id', $target->id)->where('user_id', $head->id)->latest('id')->first()->changes)
            ->toEqual(['is_active' => ['old' => true, 'new' => false]]);

        $this->post(route('accounts.reactivate', $target));

        expect($target->fresh()->is_active)->toBeTrue();
    });

    it('refuses to deactivate your own account', function () {
        $head = hrAdmin();

        $this->actingAs($head)->post(route('accounts.deactivate', $head))->assertForbidden();
    });

    it('refuses to leave fewer than two active HR Admins', function () {
        $first = hrAdmin();
        $second = hrAdmin();

        $this->actingAs($first)->post(route('accounts.deactivate', $second))->assertSessionHas('error');

        expect($second->fresh()->is_active)->toBeTrue();
    });

    it('allows deactivating an HR Admin when two others remain', function () {
        $first = hrAdmin();
        hrAdmin();
        $third = hrAdmin();

        $this->actingAs($first)->post(route('accounts.deactivate', $third))->assertSessionHas('success');

        expect($third->fresh()->is_active)->toBeFalse();
    });

    it('is forbidden to HR Staff', function () {
        $this->actingAs(hrStaff())->post(route('accounts.deactivate', User::factory()->create()))->assertForbidden();
    });
});

describe('changing a role', function () {
    it('lets the HR Admin change a role and audits the old and new role', function () {
        $head = hrAdmin();
        $target = User::factory()->create();

        $this->actingAs($head)->put(route('accounts.role', $target), ['role' => 'hr_staff'])->assertSessionHas('success');

        expect($target->fresh()->role)->toBe(Role::HrStaff)
            ->and(AuditLog::query()->where('entity_id', $target->id)->latest('id')->first()->changes)
            ->toEqual(['role' => ['old' => 'employee', 'new' => 'hr_staff']]);
    });

    it('makes the employee full-time when their role becomes HR Staff', function () {
        $employee = Employee::factory()->partTime()->create([
            'weekly_hours' => 18,
            'contract_ends_on' => now()->addMonths(3)->toDateString(),
        ]);
        $target = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs(hrAdmin())->put(route('accounts.role', $target), ['role' => 'hr_staff'])->assertSessionHas('success');

        $fresh = $employee->fresh();

        expect($fresh->employment_type)->toBe(EmploymentType::FullTime)
            ->and($fresh->weekly_hours)->toBe(Employee::FULL_TIME_HOURS)
            ->and($fresh->contract_ends_on)->toBeNull();
    });

    it('refuses to change your own role', function () {
        $head = hrAdmin();

        $this->actingAs($head)->put(route('accounts.role', $head), ['role' => 'employee'])->assertForbidden();
    });

    it('refuses to demote an HR Admin when that leaves fewer than two', function () {
        $first = hrAdmin();
        $second = hrAdmin();

        $this->actingAs($first)->put(route('accounts.role', $second), ['role' => 'hr_staff'])->assertSessionHas('error');

        expect($second->fresh()->role)->toBe(Role::HrAdmin);
    });

    it('rejects an unknown role', function () {
        $this->actingAs(hrAdmin())->put(route('accounts.role', User::factory()->create()), ['role' => 'owner'])
            ->assertSessionHasErrors('role');
    });

    it('is forbidden to HR Staff', function () {
        $this->actingAs(hrStaff())->put(route('accounts.role', User::factory()->create()), ['role' => 'hr_admin'])->assertForbidden();
    });
});
