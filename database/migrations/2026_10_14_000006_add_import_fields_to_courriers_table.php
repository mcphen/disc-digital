<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->string('legacy_agent_name')->nullable()->after('assigned_agent_id');
            $table->string('source_sheet')->nullable()->after('notes');
            $table->unsignedInteger('source_row')->nullable()->after('source_sheet');
            $table->string('source_hash', 64)->nullable()->unique()->after('source_row');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropUnique(['source_hash']);
            $table->dropColumn(['legacy_agent_name', 'source_sheet', 'source_row', 'source_hash']);
        });
    }
};
