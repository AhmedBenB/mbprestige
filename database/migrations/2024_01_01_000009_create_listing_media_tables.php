<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('source_url')->nullable();
            $table->string('local_path')->nullable();
            $table->string('cdn_url')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('processing_status')->default('pending')->index();
            // pending, downloading, processing, ready, failed
            $table->string('rights_status')->default('unknown')->index();
            // unknown, owned, licensed, public_domain, restricted
            $table->timestamps();

            $table->index(['listing_id', 'sort_order']);
        });

        Schema::create('listing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            // carpass, appraisal, invoice, inspection, other
            $table->string('file_path');
            $table->string('visibility')->default('private')->index();
            // public, authenticated, private
            $table->timestamps();
        });

        Schema::create('listing_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('group_name')->nullable()->index();
            // high_value_options, safety_security, multimedia, other_options
            $table->string('attribute_name')->index();
            $table->string('attribute_value')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['listing_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_attributes');
        Schema::dropIfExists('listing_documents');
        Schema::dropIfExists('listing_images');
    }
};
