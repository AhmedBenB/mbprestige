<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_source_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40)->default('ecarstrade');
            $table->string('login_email')->nullable();
            $table->string('login_username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_auth_status', 40)->default('never_tested');
            $table->text('last_auth_error')->nullable();
            $table->timestamp('last_auth_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'source']);
            $table->index(['source', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_source_accounts');
    }
};
