<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courrier_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_hash', 64)->unique();
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('imported_rows');
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->foreignId('imported_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_imports');
    }
};
