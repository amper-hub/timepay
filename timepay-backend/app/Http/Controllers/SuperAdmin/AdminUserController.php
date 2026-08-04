<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $admins = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($hasStatusColumn && $request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.admins.index', compact('admins', 'hasStatusColumn'));
    }

    public function create(): View
    {
        return view('super-admin.admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'status' => [$hasStatusColumn ? 'required' : 'nullable', Rule::in(['active', 'suspended'])],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_ADMIN,
        ];

        if ($hasStatusColumn) {
            $attributes['status'] = $validated['status'];
        }

        User::create($attributes);

        return redirect()->route('super-admin.admins.index')->with('success', 'Admin account created.');
    }

    public function show(User $admin): View
    {
        abort_unless($admin->isAdmin(), 404);

        return view('super-admin.admins.show', compact('admin'));
    }

    public function edit(User $admin): View
    {
        abort_unless($admin->isAdmin(), 404);

        $hasStatusColumn = Schema::hasColumn('users', 'status');

        return view('super-admin.admins.edit', compact('admin', 'hasStatusColumn'));
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);

        $hasStatusColumn = Schema::hasColumn('users', 'status');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($admin)],
            'password' => 'nullable|min:8|confirmed',
            'status' => [$hasStatusColumn ? 'required' : 'nullable', Rule::in(['active', 'suspended'])],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        if ($hasStatusColumn) {
            $attributes['status'] = $validated['status'];
        }

        $admin->update($attributes);

        return redirect()->route('super-admin.admins.index')->with('success', 'Admin account updated.');
    }

    public function destroy(User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);

        abort_if(auth()->id() === $admin->id, 403, 'You cannot delete your own account.');

        $admin->delete();

        return redirect()->route('super-admin.admins.index')->with('success', 'Admin account deleted.');
    }
}
