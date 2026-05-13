<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->string('client_first_name')->nullable()->after('client_name');
            $table->string('client_last_name')->nullable()->after('client_first_name');
            $table->text('client_comment')->nullable()->after('client_phone');
        });

        DB::table('customer_searches')
            ->select('id', 'client_name')
            ->orderBy('id')
            ->get()
            ->each(function (object $search): void {
                $fullName = trim((string) ($search->client_name ?? ''));

                if ($fullName === '') {
                    return;
                }

                $parts = preg_split('/\s+/', $fullName, 2) ?: [];

                DB::table('customer_searches')
                    ->where('id', $search->id)
                    ->update([
                        'client_first_name' => $parts[0] ?? null,
                        'client_last_name' => $parts[1] ?? null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('customer_searches', function (Blueprint $table) {
            $table->dropColumn([
                'client_first_name',
                'client_last_name',
                'client_comment',
            ]);
        });
    }
};
