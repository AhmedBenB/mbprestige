<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sources')) {
            Schema::create('sources', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->string('type', 80)->default('marketplace');
                $table->string('base_url', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('sources', function (Blueprint $table): void {
            if (!Schema::hasColumn('sources', 'code')) {
                $table->string('code', 80)->nullable()->after('id');
            }
            if (!Schema::hasColumn('sources', 'name')) {
                $table->string('name', 160)->nullable();
            }
            if (!Schema::hasColumn('sources', 'type')) {
                $table->string('type', 80)->default('marketplace');
            }
            if (!Schema::hasColumn('sources', 'base_url')) {
                $table->string('base_url', 255)->nullable();
            }
            if (!Schema::hasColumn('sources', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('sources', 'meta')) {
                $table->json('meta')->nullable();
            }
            if (!Schema::hasColumn('sources', 'created_at') || !Schema::hasColumn('sources', 'updated_at')) {
                $table->timestamps();
            }
        });

        // Backfill minimal values so updateOrCreate(code=...) works immediately.
        DB::table('sources')
            ->whereNull('code')
            ->orWhere('code', '')
            ->update([
                'code' => DB::raw("CONCAT('legacy-source-', id)"),
            ]);

        DB::table('sources')
            ->whereNull('name')
            ->orWhere('name', '')
            ->update([
                'name' => DB::raw("CONCAT('Source #', id)"),
            ]);

        // Try creating the unique index in a cross-db safe way.
        try {
            Schema::table('sources', function (Blueprint $table): void {
                $table->unique('code');
            });
        } catch (\Throwable $exception) {
            $message = strtolower($exception->getMessage());
            $alreadyExists = str_contains($message, 'duplicate key name')
                || str_contains($message, 'already exists')
                || str_contains($message, 'duplicate index');

            if (!$alreadyExists) {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        // No destructive rollback for compatibility fix.
    }
};
