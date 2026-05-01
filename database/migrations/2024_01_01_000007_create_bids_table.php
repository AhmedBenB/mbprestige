<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending')->index();
            // pending, leading, outbid, won_pending_validation, accepted, rejected, cancelled, expired
            $table->string('bid_type')->default('manual')->index(); // manual, auto
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'amount']);
            $table->index(['listing_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
