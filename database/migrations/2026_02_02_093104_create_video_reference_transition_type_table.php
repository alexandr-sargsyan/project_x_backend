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
        Schema::create('video_reference_transition_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_reference_id')->constrained('video_references')->onDelete('cascade');
            $table->foreignId('transition_type_id')->constrained('transition_types')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['video_reference_id', 'transition_type_id']);
            $table->index('video_reference_id');
            $table->index('transition_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_reference_transition_type');
    }
};
