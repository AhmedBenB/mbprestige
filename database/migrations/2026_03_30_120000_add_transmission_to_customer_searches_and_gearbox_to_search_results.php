<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_searches', function (Blueprint $table): void {
            if (!Schema::hasColumn('customer_searches', 'transmission')) {
                $table->string('transmission')->nullable()->after('fuel');
            }
        });

        Schema::table('search_results', function (Blueprint $table): void {
            if (!Schema::hasColumn('search_results', 'gearbox')) {
                $table->string('gearbox')->nullable()->after('fuel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_searches', 'transmission')) {
                $table->dropColumn('transmission');
            }
        });

        Schema::table('search_results', function (Blueprint $table): void {
            if (Schema::hasColumn('search_results', 'gearbox')) {
                $table->dropColumn('gearbox');
            }
        });
    }
};
