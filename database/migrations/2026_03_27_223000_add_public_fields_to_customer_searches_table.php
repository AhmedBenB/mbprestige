<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->boolean('consent_email')->default(false)->after('client_phone');
            $table->boolean('consent_sms')->default(false)->after('consent_email');
            $table->string('manage_token', 80)->nullable()->unique()->after('last_run_at');
            $table->string('unsubscribe_token', 80)->nullable()->unique()->after('manage_token');
        });
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->dropUnique(['manage_token']);
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn([
                'consent_email',
                'consent_sms',
                'manage_token',
                'unsubscribe_token',
            ]);
        });
    }
};
