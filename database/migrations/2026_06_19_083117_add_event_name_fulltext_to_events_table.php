<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Stored generated column so MySQL persists the extracted name on disk.
        // This lets us put a normal B-tree AND a FULLTEXT index on it, which
        // is impossible on raw JSON columns. storedAs rewrites the table once
        // (slow on large data, fast thereafter).
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_name', 500)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.name'))")
                ->nullable()
                ->after('payload');
        });

        // FULLTEXT lets us do MATCH…AGAINST instead of LIKE '%term%'.
        // LIKE with a leading wildcard can never use any index.
        DB::statement('ALTER TABLE events ADD FULLTEXT INDEX events_event_name_ft (event_name)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP INDEX events_event_name_ft');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_name');
        });
    }
};
