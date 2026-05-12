<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationEcarsTradeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request, OrganizationEcarsTradeAccountService $ecarsTradeAccounts): View
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $this->resolveOrganization($user);
        $ecarsTradeAccount = $user->isAdmin() ? $ecarsTradeAccounts->forOrganization($organization) : null;

        return view('app.profile.show', [
            'user' => $user,
            'organization' => $organization,
            'ecarsTradeAccount' => $ecarsTradeAccount,
            'ecarsTradeStatusLabel' => $ecarsTradeAccounts->statusLabel($ecarsTradeAccount?->last_auth_status),
            'ecarsTradeCheckedAt' => $ecarsTradeAccounts->formatCheckedAt($ecarsTradeAccount?->last_auth_checked_at),
            'defaultEcarsTradeBaseUrl' => $ecarsTradeAccounts->normalizeBaseUrl(''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($request->user()?->id)],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $emailChanged = strtolower((string) $user->email) !== strtolower((string) $data['email']);

        $user->fill($data);
        $user->name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $user->is_active = true;

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profil mis a jour avec succes.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($data['current_password'], (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'Mot de passe actuel incorrect.',
            ])->withInput();
        }

        $user->password = $data['password'];
        $user->save();

        return back()->with('success', 'Mot de passe mis a jour avec succes.');
    }

    public function updateEcarsTrade(Request $request, OrganizationEcarsTradeAccountService $ecarsTradeAccounts): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->isAdmin()) {
            abort(403, 'Acces reserve aux admins.');
        }

        $organization = $this->requireOrganization($user);

        $data = $request->validate([
            'login_email' => ['nullable', 'email', 'max:190', 'required_without:login_username'],
            'login_username' => ['nullable', 'string', 'max:190', 'required_without:login_email'],
            'password' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        try {
            $ecarsTradeAccounts->storeForOrganization($organization, $data);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'ecarstrade' => $exception->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Identifiants eCarsTrade enregistres.');
    }

    public function testEcarsTrade(Request $request, OrganizationEcarsTradeAccountService $ecarsTradeAccounts): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->isAdmin()) {
            abort(403, 'Acces reserve aux admins.');
        }

        $organization = $this->requireOrganization($user);
        $account = $ecarsTradeAccounts->forOrganization($organization);

        if (!$account) {
            return back()->withErrors([
                'ecarstrade' => 'Configure d abord les identifiants eCarsTrade.',
            ]);
        }

        try {
            $ecarsTradeAccounts->testAccount($account);

            return back()->with('success', 'Connexion eCarsTrade confirmee.');
        } catch (Throwable $exception) {
            return back()->withErrors([
                'ecarstrade' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveOrganization(User $user): ?Organization
    {
        if (!$user->organization_id) {
            return null;
        }

        return $user->relationLoaded('organization')
            ? $user->organization
            : $user->organization()->first();
    }

    private function requireOrganization(User $user): Organization
    {
        $organization = $this->resolveOrganization($user);
        abort_unless($organization !== null, 422, 'Cet admin n est rattache a aucune organisation.');

        return $organization;
    }
}

