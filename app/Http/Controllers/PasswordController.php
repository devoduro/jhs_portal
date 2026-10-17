<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit()
    {
        $user = Auth::user();
        return view('auth.change-password', [
            'is_first_login' => $user->first_login,
            'role' => $user->role
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) {
                if (!Hash::check($value, Auth::user()->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            // Easy to choose and remember: at least 8 characters, confirmed, and different from the current one.
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.min' => 'Your new password must be at least 8 characters.',
            'password.different' => 'Choose a password that is different from your current one.',
        ]);

        $user = Auth::user();
        
        $user->update([
            'password' => Hash::make($request->password),
            'first_login' => false,
        ]);

        $redirectRoute = $user->role === 'student' ? 'student.dashboard' : 'dashboard';
        return redirect()->route($redirectRoute)
            ->with('success', 'Password updated successfully. Please use your new password for future logins.');
    }
}
