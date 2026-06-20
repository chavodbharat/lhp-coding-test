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
            // Covers: WHERE status = ? ORDER BY created_time DESC
            $table->index(['status', 'created_time'], 'events_status_created_time_index');

            // Covers: WHERE type = ? ORDER BY created_time DESC
            $table->index(['type', 'created_time'], 'events_type_created_time_index');

            // Covers: WHERE status = ? AND type = ? ORDER BY created_time DESC
            $table->index(['status', 'type', 'created_time'], 'events_status_type_created_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_created_time_index');
            $table->dropIndex('events_type_created_time_index');
            $table->dropIndex('events_status_type_created_time_index');
        });
    }
};
