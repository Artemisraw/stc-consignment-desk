<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Account/Password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => $password]);
        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed.');
    }
}
