<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('make');
            $table->string('model');
            $table->decimal('budget_max', 10, 2);
            $table->unsignedSmallInteger('year_min');
            $table->string('fuel')->nullable();
            $table->unsignedInteger('mileage_max')->nullable();
            $table->unsignedInteger('mileage_tolerance')->default(10000);
            $table->string('color')->nullable();
            $table->string('source_zone')->default('all_cars');
            $table->string('status')->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_searches');
    }
};
