<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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

        Schema::create('source_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('sync_limit')->default(20);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('source_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_import_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('status', 40)->default('pending');
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_import_id', 'external_id']);
            $table->index(['status', 'processed_at']);
        });

        Schema::create('external_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('title', 255)->nullable();
            $table->string('slug', 255)->nullable();
            $table->string('listing_url', 600)->nullable();
            $table->string('listing_type', 40)->default('unknown');
            $table->string('source_status', 40)->default('unknown');
            $table->string('status', 40)->default('draft');
            $table->string('currency', 8)->default('EUR');
            $table->boolean('price_visible')->default(false);
            $table->decimal('price_amount', 12, 2)->nullable();
            $table->timestamp('auction_end_at')->nullable();
            $table->string('make', 120)->nullable();
            $table->string('model', 160)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('fuel', 60)->nullable();
            $table->string('transmission', 60)->nullable();
            $table->string('color', 60)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('location', 160)->nullable();
            $table->json('images')->nullable();
            $table->json('technical_data')->nullable();
            $table->json('equipment')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['status', 'listing_type']);
            $table->index(['make', 'model', 'year']);
        });

        Schema::create('listing_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_listing_id')->constrained('external_listings')->cascadeOnDelete();
            $table->string('document_type', 80)->default('other');
            $table->string('title', 255)->nullable();
            $table->string('file_url', 700);
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('listing_price_estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_listing_id')->constrained('external_listings')->cascadeOnDelete();
            $table->decimal('estimated_price_min', 12, 2);
            $table->decimal('estimated_price_max', 12, 2);
            $table->decimal('estimated_price_confidence', 5, 2)->default(0);
            $table->string('confidence_label', 40)->nullable();
            $table->text('estimated_price_reason')->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_similarities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_listing_id')->constrained('external_listings')->cascadeOnDelete();
            $table->foreignId('similar_external_listing_id')->constrained('external_listings')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['external_listing_id', 'similar_external_listing_id'], 'uniq_listing_similarity');
            $table->index(['external_listing_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_similarities');
        Schema::dropIfExists('listing_price_estimates');
        Schema::dropIfExists('listing_documents');
        Schema::dropIfExists('external_listings');
        Schema::dropIfExists('source_import_items');
        Schema::dropIfExists('source_imports');
        Schema::dropIfExists('sources');
    }
};
