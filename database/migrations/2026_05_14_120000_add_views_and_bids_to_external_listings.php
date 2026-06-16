<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_listings', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->after('published_at');
        });

        Schema::create('external_listing_bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_listing_id')->constrained('external_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('placed_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['external_listing_id', 'amount']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_listing_bids');

        Schema::table('external_listings', function (Blueprint $table): void {
            $table->dropColumn('views_count');
        });
    }
};
