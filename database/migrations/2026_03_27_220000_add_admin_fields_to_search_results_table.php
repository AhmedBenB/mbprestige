<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('match_score')->default(0)->after('color');
            $table->string('match_status')->default('candidate')->after('match_score');
            $table->text('admin_summary')->nullable()->after('match_status');
            $table->text('review_notes')->nullable()->after('admin_summary');
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
            $table->timestamp('shared_with_client_at')->nullable()->after('reviewed_at');
            $table->string('shared_channel')->nullable()->after('shared_with_client_at');
            $table->text('shared_note')->nullable()->after('shared_channel');

            $table->index(['match_status', 'created_at']);
            $table->index(['customer_search_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::table('search_results', function (Blueprint $table) {
            $table->dropIndex(['match_status', 'created_at']);
            $table->dropIndex(['customer_search_id', 'match_status']);
            $table->dropColumn([
                'match_score',
                'match_status',
                'admin_summary',
                'review_notes',
                'reviewed_at',
                'shared_with_client_at',
                'shared_channel',
                'shared_note',
            ]);
        });
    }
};
