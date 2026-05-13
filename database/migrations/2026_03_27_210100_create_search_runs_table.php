<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_search_id')->constrained('customer_searches')->cascadeOnDelete();
            $table->string('source')->default('ecarstrade');
            $table->string('zone')->default('all_cars');
            $table->string('status')->default('pending');
            $table->json('query_payload')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_runs');
    }
};
