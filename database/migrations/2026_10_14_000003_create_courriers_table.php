<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sequence_number');
            $table->unsignedSmallInteger('registration_year');
            $table->date('arrival_date');
            $table->string('reference')->nullable();
            $table->string('origin_service');
            $table->foreignId('courrier_type_id')->constrained();
            $table->text('subject');
            $table->string('arrival_number')->nullable();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->date('division_assigned_at')->nullable();
            $table->date('agent_assigned_at')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('submitted_at')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status', 40)->index();
            $table->unsignedSmallInteger('agent_delay_days')->nullable();
            $table->unsignedSmallInteger('director_delay_days')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['registration_year', 'sequence_number']);
            $table->index(['status', 'arrival_date']);
            $table->index(['division_id', 'assigned_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
