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
        // Включаем расширение pg_trgm для нечёткого поиска (триграммы)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

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

            // Search Fields (основные)
            $table->text('search_profile');
            $table->text('search_metadata')->nullable();
            
            // Search Fields (денормализованные для полнотекстового поиска)
            $table->text('search_tags')->default(''); // Денормализованные названия тегов
            $table->text('search_categories')->default(''); // Денормализованные названия категорий
            $table->text('search_hook')->default(''); // Денормализованное название хука

            // Ранжирование
            $table->integer('quality_score')->default(0);
            $table->json('completeness_flags')->nullable();
            $table->integer('rating')->default(1)->comment('Rating from 0 to 10 for sorting');

            // Служебные
            $table->timestamps();
            
            // Индексы для фильтров
            $table->index('platform');
            $table->index('pacing');
            $table->index('production_level');
            $table->index('hook_id');
            $table->index(['rating', 'created_at']); // Составной индекс для сортировки
            $table->index('created_at');
        });

        // Создаём computed column для full-text search с весами
        // A - title (самый высокий приоритет)
        // B - public_summary, search_tags, search_categories, search_hook
        // C - search_profile
        // D - search_metadata (самый низкий приоритет)
        DB::statement("
            ALTER TABLE video_references 
            ADD COLUMN search_vector tsvector 
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(public_summary, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(search_tags, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(search_categories, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(search_hook, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(search_profile, '')), 'C') ||
                setweight(to_tsvector('english', coalesce(search_metadata, '')), 'D')
            ) STORED
        ");

        // Создаём GIN индекс для быстрого full-text поиска
        DB::statement('CREATE INDEX video_references_search_vector_idx ON video_references USING GIN (search_vector)');
        
        // Создаём триграммные индексы для нечёткого поиска (fuzzy search)
        DB::statement('CREATE INDEX video_references_title_trgm_idx ON video_references USING GIN (title gin_trgm_ops)');
        DB::statement('CREATE INDEX video_references_search_tags_trgm_idx ON video_references USING GIN (search_tags gin_trgm_ops)');
        DB::statement('CREATE INDEX video_references_search_categories_trgm_idx ON video_references USING GIN (search_categories gin_trgm_ops)');
        
        // Составной индекс для частых комбинаций фильтров
        DB::statement('CREATE INDEX video_references_filters_idx ON video_references (platform, pacing, production_level, rating DESC)');

        // Триггер для автоматического обновления search_hook при изменении hook_id
        DB::statement("
            CREATE OR REPLACE FUNCTION update_video_reference_search_hook()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.hook_id IS NOT NULL THEN
                    NEW.search_hook := (SELECT name FROM hooks WHERE id = NEW.hook_id);
                ELSE
                    NEW.search_hook := '';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trigger_update_search_hook
            BEFORE INSERT OR UPDATE OF hook_id ON video_references
            FOR EACH ROW
            EXECUTE FUNCTION update_video_reference_search_hook();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_search_hook ON video_references');
        DB::statement('DROP FUNCTION IF EXISTS update_video_reference_search_hook()');
        Schema::dropIfExists('video_references');
    }
};
