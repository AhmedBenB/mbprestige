<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('role', 20)->default('client');
            $table->boolean('is_active')->default(true);
        });

        Schema::table('customer_searches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->backfillUsers();
        $this->backfillCustomerSearchOwners();
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'role',
                'is_active',
            ]);
        });
    }

    private function backfillUsers(): void
    {
        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            [$firstName, $lastName] = $this->splitName((string) $user->name);

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'first_name' => $user->first_name ?: $firstName,
                    'last_name' => $user->last_name ?: $lastName,
                    'role' => $user->is_admin ? 'admin' : 'client',
                    'is_active' => 1,
                ]);
        }
    }

    private function backfillCustomerSearchOwners(): void
    {
        $now = now();
        $searches = DB::table('customer_searches')
            ->whereNotNull('client_email')
            ->orderBy('id')
            ->get();

        foreach ($searches as $search) {
            $email = Str::lower(trim((string) $search->client_email));

            if ($email === '') {
                continue;
            }

            $user = DB::table('users')->where('email', $email)->first();

            if (! $user) {
                [$firstName, $lastName] = $this->splitName((string) $search->client_name);
                $displayName = trim(implode(' ', array_filter([
                    $search->client_first_name ?: $firstName,
                    $search->client_last_name ?: $lastName,
                ])));

                $userId = DB::table('users')->insertGetId([
                    'name' => $displayName !== '' ? $displayName : $email,
                    'first_name' => $search->client_first_name ?: $firstName,
                    'last_name' => $search->client_last_name ?: $lastName,
                    'email' => $email,
                    'phone' => $this->cleanValue($search->client_phone),
                    'password' => Hash::make(Str::random(40)),
                    'is_admin' => 0,
                    'role' => 'client',
                    'is_active' => 1,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $userId = $user->id;
                $updates = [];

                if (! $user->is_admin) {
                    if (! $user->first_name && $search->client_first_name) {
                        $updates['first_name'] = $search->client_first_name;
                    }

                    if (! $user->last_name && $search->client_last_name) {
                        $updates['last_name'] = $search->client_last_name;
                    }

                    if (! $user->phone && $search->client_phone) {
                        $updates['phone'] = $this->cleanValue($search->client_phone);
                    }

                    if ((! $user->name || trim((string) $user->name) === '') && $search->client_name) {
                        $updates['name'] = $search->client_name;
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = $now;
                        DB::table('users')->where('id', $userId)->update($updates);
                    }
                }
            }

            DB::table('customer_searches')
                ->where('id', $search->id)
                ->update([
                    'user_id' => $userId,
                ]);
        }
    }

    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    private function cleanValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
};
