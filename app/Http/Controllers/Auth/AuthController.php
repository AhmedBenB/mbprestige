<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login.' . Str::lower((string) $request->email) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Reessayez dans {$seconds} secondes.",
            ]);
        }

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            Log::warning('Web login failed', [
                'email' => Str::lower((string) $request->email),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var User $user */
        $user = auth()->user();

        if ($user->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Compte desactive. Contactez le support.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        Log::info('Web login success', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        $intended = session()->pull('url.intended');
        if ($intended) {
            return redirect($intended);
        }

        if ((bool) ($user->is_admin ?? false) || in_array((string) $user->role, ['admin', 'super_admin'], true)) {
            return \Illuminate\Support\Facades\Route::has('admin.external_listings.index')
                ? redirect()->route('admin.external_listings.index')
                : (\Illuminate\Support\Facades\Route::has('admin.listings.index')
                    ? redirect()->route('admin.listings.index')
                    : redirect()->route('app.dashboard'));
        }

        return redirect()->route('app.dashboard');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_country_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone_local' => ['required', 'string', 'regex:/^[0-9\s().-]{4,20}$/'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'referral_code' => ['required', 'string'],
        ]);

        $expectedCode = Setting::get('registration_code', 'MBP95');
        if (strtoupper(trim($data['referral_code'])) !== strtoupper($expectedCode)) {
            throw ValidationException::withMessages([
                'referral_code' => 'Code de parrainage invalide.',
            ]);
        }

        $phone = $this->formatInternationalPhone($data['phone_country_code'], $data['phone_local']);

        $user = User::create([
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'email' => $data['email'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_CLIENT,
            'status' => 'active',
            'is_active' => true,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        Log::info('Web register success', [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('app.dashboard')
            ->with('success', "Bienvenue {$user->first_name} ! Votre compte a ete cree.");
    }

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
            ->with('success', 'Vous avez ete deconnecte.');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Un email de reinitialisation a ete envoye.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mot de passe modifie avec succes.')
            : back()->withErrors(['email' => __($status)]);
    }

    private function formatInternationalPhone(string $countryCode, string $localNumber): string
    {
        $code = preg_replace('/\D+/', '', $countryCode) ?? '';
        $number = preg_replace('/\D+/', '', $localNumber) ?? '';
        $number = ltrim($number, '0');

        return '+' . $code . $number;
    }
}
