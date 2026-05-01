<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vin')->nullable()->index();
            $table->string('make')->index();
            $table->string('model')->index();
            $table->string('version')->nullable()->index();
            $table->string('body_type')->nullable()->index();
            $table->string('fuel_type')->nullable()->index();
            $table->string('gearbox')->nullable()->index();
            $table->string('transmission')->nullable()->index();
            $table->unsignedInteger('engine_size_cc')->nullable();
            $table->unsignedInteger('power_hp')->nullable()->index();
            $table->unsignedInteger('power_kw')->nullable();
            $table->unsignedSmallInteger('co2')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('color')->nullable()->index();
            $table->string('color_code')->nullable();
            $table->string('origin_country', 2)->nullable()->index();
            $table->date('first_registration_date')->nullable()->index();
            $table->unsignedInteger('mileage')->nullable()->index();
            $table->string('emission_class')->nullable();
            $table->string('service_history')->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedSmallInteger('registration_year')->nullable();
            } else {
                $table->unsignedSmallInteger('registration_year')
                    ->virtualAs('YEAR(first_registration_date)')
                    ->nullable();
            }
            $table->timestamps();

            $table->index(['make', 'model']);
            $table->index(['make', 'model', 'fuel_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
