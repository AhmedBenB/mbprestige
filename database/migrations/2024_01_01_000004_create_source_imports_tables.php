<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('pending')->index(); // pending, running, done, failed
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->longText('raw_log')->nullable();
            $table->timestamps();
        });

        Schema::create('source_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_import_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('status')->default('pending')->index(); // pending, processed, failed, skipped
            $table->json('raw_payload');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_import_items');
        Schema::dropIfExists('source_imports');
    }
};
