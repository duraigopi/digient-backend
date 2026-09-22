<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            // Nullable rather than NOT NULL: changing an existing column's
            // nullability needs doctrine/dbal, which this project does not
            // pull in. The guard treats a NULL expiry as expired, so the
            // column failing open is not possible.
            $table->timestamp('expires_at')->nullable()->after('last_used_at');

            // Every authenticated request filters on the hash and then checks
            // this, and expired rows are swept by date.
            $table->index('expires_at');
        });

        // Tokens issued before this migration have no expiry. Give them the
        // same one-day window measured from when they were created, so
        // existing sessions end on schedule instead of being cut immediately.
        DB::table('api_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL 1 DAY)')]);
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
