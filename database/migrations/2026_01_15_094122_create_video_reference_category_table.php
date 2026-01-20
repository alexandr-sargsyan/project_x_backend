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
        Schema::create('video_reference_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_reference_id')->constrained('video_references')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['video_reference_id', 'category_id']);
            $table->index('video_reference_id');
            $table->index('category_id');
        });

        // Функция для обновления search_categories в video_references
        DB::statement("
            CREATE OR REPLACE FUNCTION update_video_reference_search_categories()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_video_id BIGINT;
            BEGIN
                -- Определяем video_reference_id в зависимости от операции
                IF TG_OP = 'DELETE' THEN
                    v_video_id := OLD.video_reference_id;
                ELSE
                    v_video_id := NEW.video_reference_id;
                END IF;

                -- Обновляем search_categories для соответствующего video_reference
                -- Включаем как категорию, так и её slug для лучшего поиска
                UPDATE video_references
                SET search_categories = (
                    SELECT COALESCE(string_agg(c.name || ' ' || COALESCE(c.slug, ''), ' '), '')
                    FROM video_reference_category vrc
                    JOIN categories c ON c.id = vrc.category_id
                    WHERE vrc.video_reference_id = v_video_id
                )
                WHERE id = v_video_id;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                ELSE
                    RETURN NEW;
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Триггер для INSERT
        DB::statement("
            CREATE TRIGGER trigger_update_search_categories_insert
            AFTER INSERT ON video_reference_category
            FOR EACH ROW
            EXECUTE FUNCTION update_video_reference_search_categories();
        ");

        // Триггер для DELETE
        DB::statement("
            CREATE TRIGGER trigger_update_search_categories_delete
            AFTER DELETE ON video_reference_category
            FOR EACH ROW
            EXECUTE FUNCTION update_video_reference_search_categories();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_search_categories_insert ON video_reference_category');
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_search_categories_delete ON video_reference_category');
        DB::statement('DROP FUNCTION IF EXISTS update_video_reference_search_categories()');
        Schema::dropIfExists('video_reference_category');
    }
};
