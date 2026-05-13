<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('partner_code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::table('customer_searches', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
        });

        $now = now();
        $defaultOrganizationId = DB::table('organizations')->insertGetId([
            'name' => 'AutoSourcing Direct',
            'slug' => 'autosourcing-direct',
            'partner_code' => 'AUTO0001',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')
            ->where('is_admin', true)
            ->update([
                'role' => 'super_admin',
                'organization_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('users')
            ->where('is_admin', false)
            ->whereNull('organization_id')
            ->update([
                'organization_id' => $defaultOrganizationId,
                'updated_at' => $now,
            ]);

        DB::table('customer_searches')
            ->whereNull('organization_id')
            ->update([
                'organization_id' => $defaultOrganizationId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
