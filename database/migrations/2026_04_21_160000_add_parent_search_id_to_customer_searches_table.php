<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->foreignId('parent_search_id')
                ->nullable()
                ->after('id')
                ->constrained('customer_searches')
                ->cascadeOnDelete();

            $table->index(['parent_search_id', 'organization_id'], 'customer_searches_parent_org_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->dropIndex('customer_searches_parent_org_idx');
            $table->dropConstrainedForeignId('parent_search_id');
        });
    }
};
