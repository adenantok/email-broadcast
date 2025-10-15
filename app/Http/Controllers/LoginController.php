<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $envUser = env('LOGIN_USER');
        $envPass = env('LOGIN_PASS');

        if ($request->username === $envUser && $request->password === $envPass) {
            session(['logged_in' => true]);
            return redirect('/broadcast')->with('success', 'Login berhasil!');
        }

        return back()->withErrors(['message' => 'Username atau password salah.']);
    }

    public function logout()
    {
        session()->forget('logged_in');
        return redirect('/login')->with('success', 'Berhasil logout.');
    }
}
