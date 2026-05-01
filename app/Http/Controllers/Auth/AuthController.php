<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // SHOW FORMS
    // ─────────────────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────────

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Rate limiting : 5 tentatives / 60s par IP+email
        $key = 'login.' . Str::lower($request->email) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            Log::warning('Web login failed', [
                'email' => Str::lower($request->email),
                'ip' => $request->ip(),
            ]);
            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if (auth()->user()->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Compte désactivé. Contactez le support.',
            ]);
        }

        // Mise à jour last_login_at
        auth()->user()->update(['last_login_at' => now()]);
        Log::info('Web login success', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        // Redirection selon le rôle
        $intended = session()->pull('url.intended');
        if ($intended) return redirect($intended);

        return match(auth()->user()->role) {
            'admin'  => \Illuminate\Support\Facades\Route::has('admin.listings.index')
                ? redirect()->route('admin.listings.index')
                : redirect()->route('app.dashboard'),
            default  => redirect()->route('app.dashboard'),
        };
    }

    // ─────────────────────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────────────────────

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'         => ['required', 'string', 'max:30'],
            'password'      => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'company_name'  => ['required', 'string', 'max:255'],
            'vat_number'    => ['nullable', 'string', 'max:50'],
            'country'       => ['required', 'string', 'size:2'],
            'accept_terms'  => ['accepted'],
        ]);

        // Créer l'organisation
        $org = Organization::create([
            'name'       => $data['company_name'],
            'vat_number' => $data['vat_number'] ?? null,
            'country'    => $data['country'],
            'status'     => 'active',
            'user_tier'  => 'trial',
        ]);

        // Créer l'utilisateur
        $user = User::create([
            'name'            => $data['first_name'] . ' ' . $data['last_name'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'],
            'password'        => Hash::make($data['password']),
            'organization_id' => $org->id,
            'role'            => 'client',
            'status'          => 'active',
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Web register success', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('app.dashboard')
            ->with('success', "Bienvenue {$user->first_name} ! Votre compte a été créé.");
    }

    // ─────────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────────

    public function logout(Request $request): RedirectResponse
    {
        Log::info('Web logout', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Vous avez été déconnecté.');
    }

    // ─────────────────────────────────────────────────────────────
    // FORGOT PASSWORD
    // ─────────────────────────────────────────────────────────────

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Un email de réinitialisation a été envoyé.')
            : back()->withErrors(['email' => __($status)]);
    }

    // ─────────────────────────────────────────────────────────────
    // RESET PASSWORD
    // ─────────────────────────────────────────────────────────────

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])
                     ->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mot de passe modifié avec succès.')
            : back()->withErrors(['email' => __($status)]);
    }
}
