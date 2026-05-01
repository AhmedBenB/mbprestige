<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('auction_mode')->index(); // open, blind
            $table->string('status')->default('scheduled')->index();
            // scheduled, live, ending_soon, ended_waiting_validation, winner_selected, not_awarded, cancelled
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('soft_close_seconds')->default(120);
            $table->unsignedInteger('extend_if_bid_in_last_seconds')->default(120);
            $table->decimal('minimum_increment', 12, 2)->default(100);
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->foreignId('winner_bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->string('decision_status')->nullable()->index(); // winner_selected, not_awarded
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
