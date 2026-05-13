<?php

namespace Database\Seeders;

use App\Models\AdminSetting;
use App\Models\User;
use App\Services\AdminSettingsService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ahmedbbrahim007@gmail.com'],
            [
                'name' => 'Admin',
                'password' => 'admin1234',
                'is_admin' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'is_admin' => false,
            ]
        );

        $defaults = app(AdminSettingsService::class)->defaults();

        foreach ($defaults as $key => $value) {
            AdminSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
