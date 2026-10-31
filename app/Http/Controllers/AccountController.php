<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\AccountException;
use App\Actions\Accounts\ChangeStaffRole;
use App\Actions\Accounts\CreateStaffAccount;
use App\Actions\Accounts\DeactivateStaffAccount;
use App\Actions\Accounts\ReactivateStaffAccount;
use App\Actions\Accounts\ResetStaffPassword;
use App\Enums\Role;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRoleRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * HR Admin account administration: issue logins, reset passwords, deactivate, and assign roles.
 */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        return view('accounts.index', [
            'filters' => $filters,
            'accounts' => User::query()->with('employee')
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($match) => $match->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'employeesWithoutAccount' => Employee::query()->whereDoesntHave('user')->orderBy('last_name')->orderBy('first_name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateStaffAccount $createAccount): RedirectResponse
    {
        return $this->attempt(function () use ($request, $createAccount): RedirectResponse {
            [$user, $temporaryPassword] = $createAccount->handle(
                $request->user(),
                Employee::query()->findOrFail($request->integer('employee_id')),
                Role::from($request->string('role')->toString()),
            );

            return $this->issued($user, $temporaryPassword, 'Account created.');
        });
    }

    public function resetPassword(Request $request, User $user, ResetStaffPassword $resetPassword): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        return $this->attempt(function () use ($request, $user, $resetPassword): RedirectResponse {
            $temporaryPassword = $resetPassword->handle($request->user(), $user);

            return $this->issued($user, $temporaryPassword, 'Password reset.');
        });
    }

    public function deactivate(Request $request, User $user, DeactivateStaffAccount $deactivate): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        return $this->attempt(function () use ($request, $user, $deactivate): RedirectResponse {
            $deactivate->handle($request->user(), $user);

            return back()->with('success', "{$user->name} was deactivated and signed out.");
        });
    }

    public function reactivate(Request $request, User $user, ReactivateStaffAccount $reactivate): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $reactivate->handle($request->user(), $user);

        return back()->with('success', "{$user->name} was reactivated.");
    }

    public function updateRole(UpdateAccountRoleRequest $request, User $user, ChangeStaffRole $changeRole): RedirectResponse
    {
        Gate::authorize('changeRole', $user);

        return $this->attempt(function () use ($request, $user, $changeRole): RedirectResponse {
            $changeRole->handle($request->user(), $user, Role::from($request->string('role')->toString()));

            return back()->with('success', "The role of {$user->name} was updated.");
        });
    }

    /**
     * Runs an account action, turning a rule violation into an error message on the page.
     *
     * @param  callable(): RedirectResponse  $action
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (AccountException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Redirects back showing the one-time temporary password once; it is never stored in readable form.
     */
    private function issued(User $user, string $temporaryPassword, string $message): RedirectResponse
    {
        return back()->with('success', $message)->with('issued_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temporaryPassword,
            'expires' => $user->temp_password_expires_at?->format('M j, Y g:i A'),
        ]);
    }
}
