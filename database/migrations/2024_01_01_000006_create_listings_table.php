<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_external_id')->nullable()->index();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // Types et statuts
            $table->string('listing_type')->index(); // auction_open, auction_blind, fixed_price, partner_stock
            $table->string('publication_status')->default('draft')->index();
            $table->string('auction_status')->nullable()->index();

            // Contenu
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('long_description')->nullable();

            // Pricing
            $table->string('currency', 3)->default('EUR');
            $table->decimal('starting_price', 12, 2)->nullable();
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->decimal('buy_now_price', 12, 2)->nullable();
            $table->decimal('current_bid', 12, 2)->nullable();
            $table->decimal('estimate_price', 12, 2)->nullable();
            $table->decimal('minimum_increment', 12, 2)->default(100);
            $table->unsignedInteger('bid_count')->default(0);

            // Scheduling
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('seller_decision_deadline_at')->nullable();

            // Timestamps métier
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_source_sync_at')->nullable();
            $table->string('source_payload_hash')->nullable()->index();

            // Flags
            $table->boolean('vat_deductible')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();

            $table->timestamps();

            $table->index(['listing_type', 'publication_status']);
            $table->index(['publication_status', 'ends_at']);
            $table->index(['source_id', 'source_external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
