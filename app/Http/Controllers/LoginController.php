<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login.
 *
 * Tidak ada pendaftaran mandiri — akun dibuat oleh administrator. Ini
 * menggantikan satu password global di sistem lama, yang dipakai bersama
 * sehingga tidak ada jejak siapa menginput apa.
 */
class LoginController extends Controller
{
    public function form(): View
    {
        return view('auth.login');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'email',
            'password' => 'password',
        ]);

        // Pesan gagalnya sengaja tidak membedakan "email tidak ada" dan
        // "password salah" — membedakannya memberi tahu penebak bahwa
        // emailnya sudah benar.
        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak cocok.',
            ]);
        }

        if (! Auth::user()->aktif) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini sudah dinonaktifkan. Hubungi administrator.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
