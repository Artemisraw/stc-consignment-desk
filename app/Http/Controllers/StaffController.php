<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Staff/Index', [
            'staff' => User::query()->with('branch:id,name')->orderBy('name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'branch' => $user->branch?->name,
                    'is_active' => $user->is_active,
                    'can_toggle' => ! request()->user()->is($user),
                ]),
            'branches' => Branch::query()->orderBy('route_order')->get(['id', 'name']),
            'roles' => collect(Role::cases())->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => str($role->value)->replace('_', ' ')->title()->toString(),
            ]),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return back()->with('success', 'Staff account created.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Staff/Edit', [
            'staffMember' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
                'is_self' => request()->user()->is($user),
            ],
            'branches' => Branch::query()->orderBy('route_order')->get(['id', 'name']),
            'roles' => collect(Role::cases())->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => str($role->value)->replace('_', ' ')->title()->toString(),
            ]),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return redirect()->route('staff.index')->with('success', 'Staff account updated.');
    }

    public function toggle(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if (request()->user()->is($user)) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Staff account activated.' : 'Staff account deactivated.');
    }
}
