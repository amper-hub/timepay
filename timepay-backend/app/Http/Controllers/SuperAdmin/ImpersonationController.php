<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin && $admin->isSuperAdmin(), 403, 'Only super admins can impersonate users.');
        abort_unless($user->isEmployer(), 404, 'Only employer accounts can be impersonated.');

        $request->session()->put('impersonated_by', $admin->id);

        Auth::logout();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('employer.dashboard')
            ->with('success', "You are now impersonating {$user->name}.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonated_by');

        if (! $adminId) {
            return redirect()->route('employer.dashboard');
        }

        $admin = User::withoutGlobalScopes()->findOrFail($adminId);

        abort_unless($admin->isSuperAdmin(), 403, 'The original user is not a super admin.');

        $request->session()->forget('impersonated_by');

        Auth::logout();
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('super-admin.employers.index')
            ->with('success', 'You have returned to your Super Admin account.');
    }
}
