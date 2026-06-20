<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // location_name is filtered via exact-match dropdown, not free text.
            // B-tree index narrows each query to ~17K rows per city before FULLTEXT
            // or any other filter runs — instead of scanning all 1.25M rows.
            $table->index('location_name', 'events_location_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_location_name_index');
        });
    }
};
