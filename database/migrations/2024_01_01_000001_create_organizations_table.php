<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('vat_number')->nullable()->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('status')->default('active')->index();
            $table->decimal('deposit_balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->string('user_tier')->default('trial')->index(); // trial, silver, golden
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
