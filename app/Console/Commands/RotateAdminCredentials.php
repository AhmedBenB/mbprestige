<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RotateAdminCredentials extends Command
{
    protected $signature = 'admin:rotate-credentials
        {old_email : Ancien email de connexion admin}
        {new_email : Nouvel email de connexion admin}
        {new_password? : Nouveau mot de passe admin (optionnel, sera demande en mode masque si absent)}
        {--user-id= : ID utilisateur cible (optionnel)}';

    protected $description = 'Change les identifiants admin et invalide les sessions actives';

    public function handle(): int
    {
        $oldEmail = Str::lower(trim((string) $this->argument('old_email')));
        $newEmail = Str::lower(trim((string) $this->argument('new_email')));
        $newPassword = (string) ($this->argument('new_password') ?? '');
        if ($newPassword === '') {
            $newPassword = (string) $this->secret('Nouveau mot de passe admin');
        }
        $forcedUserId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;

        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Nouvel email invalide.');
            return self::FAILURE;
        }

        if (mb_strlen($newPassword) < 8) {
            $this->error('Le nouveau mot de passe doit contenir au moins 8 caracteres.');
            return self::FAILURE;
        }

        $affectedUserIds = [];

        DB::transaction(function () use ($oldEmail, $newEmail, $newPassword, $forcedUserId, &$affectedUserIds): void {
            $oldUsers = User::query()->whereRaw('LOWER(email) = ?', [$oldEmail])->get();
            $newUser = User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->first();

            $target = null;

            if ($forcedUserId) {
                $target = User::query()->find($forcedUserId);
                if (! $target) {
                    throw new \RuntimeException("Utilisateur #{$forcedUserId} introuvable.");
                }
            } elseif ($newUser) {
                $target = $newUser;
            } elseif ($oldUsers->isNotEmpty()) {
                $target = $oldUsers->first();
            }

            if (! $target) {
                throw new \RuntimeException('Aucun utilisateur cible trouve (ancien/nouvel email absents).');
            }

            $target->email = $newEmail;
            $target->password = Hash::make($newPassword);
            $target->remember_token = Str::random(60);

            if (Schema::hasColumn('users', 'is_admin')) {
                $target->is_admin = true;
            }
            if (Schema::hasColumn('users', 'role') && empty($target->role)) {
                $target->role = 'admin';
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $target->is_active = true;
            }
            if (Schema::hasColumn('users', 'status') && in_array($target->status ?? null, ['inactive', 'disabled'], true)) {
                $target->status = 'active';
            }

            $target->save();
            $affectedUserIds[] = (int) $target->id;

            $otherOldUsers = $oldUsers->where('id', '!=', $target->id);
            foreach ($otherOldUsers as $oldUser) {
                $oldUser->email = 'archived+' . $oldUser->id . '+' . now()->timestamp . '@local.invalid';
                $oldUser->password = Hash::make(Str::random(40));
                $oldUser->remember_token = Str::random(60);

                if (Schema::hasColumn('users', 'is_active')) {
                    $oldUser->is_active = false;
                }
                if (Schema::hasColumn('users', 'status')) {
                    $oldUser->status = 'inactive';
                }
                if (Schema::hasColumn('users', 'is_admin')) {
                    $oldUser->is_admin = false;
                }

                $oldUser->save();
                $affectedUserIds[] = (int) $oldUser->id;
            }

            $affectedUserIds = array_values(array_unique($affectedUserIds));

            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->whereIn('user_id', $affectedUserIds)->delete();
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $affectedUserIds)
                    ->delete();
            }
        });

        $verifyUser = User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->first();
        $passwordOk = $verifyUser ? Hash::check($newPassword, (string) $verifyUser->password) : false;

        $this->info('Rotation terminee.');
        $this->line('Nouvel email: ' . $newEmail);
        $this->line('User ID cible: ' . ($verifyUser?->id ?? 'N/A'));
        $this->line('Mot de passe verifie: ' . ($passwordOk ? 'oui' : 'non'));
        $this->line('Utilisateurs impactes: ' . implode(', ', $affectedUserIds));

        return $passwordOk ? self::SUCCESS : self::FAILURE;
    }
}
