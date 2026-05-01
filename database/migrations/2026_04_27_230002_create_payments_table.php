<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->default('deposit')->index();
            // deposit, balance, full
            $table->string('provider')->default('stripe')->index();
            $table->string('provider_session_id')->nullable()->index();
            $table->string('provider_payment_intent_id')->nullable()->index();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending')->index();
            // pending, paid, failed, cancelled, refunded

            $table->timestamp('paid_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'status']);
            $table->index(['listing_id', 'status']);
            $table->index(['provider', 'status']);
            $table->unique(['provider', 'provider_session_id'], 'payments_provider_session_unique');
            $table->unique(['provider', 'provider_payment_intent_id'], 'payments_provider_intent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
