<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->date('division_validated_at')->nullable()->after('submitted_at');
            $table->foreignId('division_validated_by_id')
                ->nullable()
                ->after('division_validated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_validated_by_id');
            $table->dropColumn('division_validated_at');
        });
    }
};
