<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imputations multiples. La division (division_id) et l'agent (assigned_agent_id) du courrier
 * restent le pilote : ils rédigent, soumettent et font valider la réponse unique. Ces tables
 * ajoutent les divisions contributrices et les co-agents associés au dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courrier_contributing_divisions', function (Blueprint $table) {
            $table->foreignId('courrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['courrier_id', 'division_id']);
            $table->index('division_id');
        });

        Schema::create('courrier_co_agents', function (Blueprint $table) {
            $table->foreignId('courrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['courrier_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_co_agents');
        Schema::dropIfExists('courrier_contributing_divisions');
    }
};
