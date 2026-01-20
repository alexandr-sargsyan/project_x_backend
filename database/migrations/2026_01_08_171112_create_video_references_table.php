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
        Schema::create('video_references', function (Blueprint $table) {
            // Display Fields
            $table->id();
            $table->string('title');
            $table->string('source_url');
            $table->text('preview_embed')->nullable();
            $table->text('public_summary')->nullable();
            $table->json('details_public')->nullable();
            $table->integer('duration_sec')->nullable();

            // Filter Fields
            $table->string('platform')->nullable();
            $table->string('pacing')->nullable();
            $table->foreignId('hook_id')->nullable()->constrained('hooks')->onDelete('set null');
            $table->string('production_level')->nullable();
            $table->boolean('has_visual_effects')->default(false);
            $table->boolean('has_3d')->default(false);
            $table->boolean('has_animations')->default(false);
            $table->boolean('has_typography')->default(false);
            $table->boolean('has_sound_design')->default(false);

            // Search Fields
            $table->text('search_profile');
            $table->text('search_metadata')->nullable();

            // Ранжирование
            $table->integer('quality_score')->default(0);
            $table->json('completeness_flags')->nullable();
            $table->integer('rating')->default(1)->comment('Rating from 0 to 10 for sorting');

            // Служебные
            $table->timestamps();
        });

        // Создаём computed column для full-text search
        DB::statement('
            ALTER TABLE video_references 
            ADD COLUMN search_vector tsvector 
            GENERATED ALWAYS AS (
                to_tsvector(\'english\', 
                    coalesce(title, \'\') || \' \' || 
                    coalesce(public_summary, \'\') || \' \' ||
                    coalesce(search_profile, \'\') || \' \' || 
                    coalesce(search_metadata, \'\')
                )
            ) STORED
        ');

        // Создаём GIN индекс для быстрого поиска
        DB::statement('CREATE INDEX video_references_search_vector_idx ON video_references USING GIN (search_vector)');
        
        // Создаём индекс для сортировки по рейтингу
        DB::statement('CREATE INDEX video_references_rating_idx ON video_references (rating DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_references');
    }
};
