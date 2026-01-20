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
        Schema::create('video_reference_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_reference_id')->constrained('video_references')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['video_reference_id', 'tag_id']);
            $table->index('video_reference_id');
            $table->index('tag_id');
        });

        // Функция для обновления search_tags в video_references
        DB::statement("
            CREATE OR REPLACE FUNCTION update_video_reference_search_tags()
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

                -- Обновляем search_tags для соответствующего video_reference
                UPDATE video_references
                SET search_tags = (
                    SELECT COALESCE(string_agg(t.name, ' '), '')
                    FROM video_reference_tag vrt
                    JOIN tags t ON t.id = vrt.tag_id
                    WHERE vrt.video_reference_id = v_video_id
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
            CREATE TRIGGER trigger_update_search_tags_insert
            AFTER INSERT ON video_reference_tag
            FOR EACH ROW
            EXECUTE FUNCTION update_video_reference_search_tags();
        ");

        // Триггер для DELETE
        DB::statement("
            CREATE TRIGGER trigger_update_search_tags_delete
            AFTER DELETE ON video_reference_tag
            FOR EACH ROW
            EXECUTE FUNCTION update_video_reference_search_tags();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_search_tags_insert ON video_reference_tag');
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_search_tags_delete ON video_reference_tag');
        DB::statement('DROP FUNCTION IF EXISTS update_video_reference_search_tags()');
        Schema::dropIfExists('video_reference_tag');
    }
};
