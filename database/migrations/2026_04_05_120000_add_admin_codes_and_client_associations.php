<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('location')->nullable()->after('name');
            $table->text('description')->nullable()->after('location');
            $table->string('admin_code', 20)->nullable()->after('partner_code')->unique();
        });

        Schema::create('client_association_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 24)->unique();
            $table->string('label')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('client_association_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('client_message')->nullable();
            $table->text('admin_response')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        $organizations = DB::table('organizations')
            ->select('id', 'name', 'partner_code')
            ->orderBy('id')
            ->get();

        foreach ($organizations as $organization) {
            DB::table('organizations')
                ->where('id', $organization->id)
                ->update([
                    'admin_code' => $this->generateUniqueCode('ADM', 'organizations', 'admin_code'),
                ]);

            if (! empty($organization->partner_code)) {
                DB::table('client_association_codes')->insert([
                    'organization_id' => $organization->id,
                    'created_by_user_id' => null,
                    'code' => $organization->partner_code,
                    'label' => 'Code permanent initial',
                    'max_uses' => null,
                    'use_count' => 0,
                    'is_active' => 1,
                    'last_used_at' => null,
                    'expires_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_association_requests');
        Schema::dropIfExists('client_association_codes');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['admin_code']);
            $table->dropColumn(['location', 'description', 'admin_code']);
        });
    }

    private function generateUniqueCode(string $prefix, string $table, string $column): string
    {
        do {
            $candidate = strtoupper($prefix) . random_int(100000, 999999);
        } while (DB::table($table)->where($column, $candidate)->exists());

        return $candidate;
    }
};
