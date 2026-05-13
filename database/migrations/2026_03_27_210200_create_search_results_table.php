<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_search_id')->constrained('customer_searches')->cascadeOnDelete();
            $table->foreignId('search_run_id')->constrained('search_runs')->cascadeOnDelete();
            $table->string('source_ref')->nullable();
            $table->text('listing_url');
            $table->string('title')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('color')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['customer_search_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_results');
    }
};
