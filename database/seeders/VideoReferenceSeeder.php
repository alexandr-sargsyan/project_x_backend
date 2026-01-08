<?php

namespace Database\Seeders;

use App\Enums\PacingEnum;
use App\Enums\PlatformEnum;
use App\Enums\ProductionLevelEnum;
use App\Models\Category;
use App\Models\Tag;
use App\Models\VideoReference;
use Illuminate\Database\Seeder;

class VideoReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();
        $tags = Tag::all();

        // Примеры видео-референсов
        $examples = [
            [
                'title' => 'Cinematic Product Commercial',
                'source_url' => 'https://www.youtube.com/watch?v=example1',
                'preview_url' => 'https://example.com/preview1.jpg',
                'public_summary' => 'Красивый коммерческий ролик с кинематографическим подходом',
                'details_public' => ['color_grade' => 'warm', 'mood' => 'cinematic'],
                'duration_sec' => 30,
                'category_id' => $categories->where('slug', 'commercial-advertising')->first()->id,
                'platform' => PlatformEnum::YOUTUBE->value,
                'pacing' => PacingEnum::SLOW->value,
                'hook_type' => 'visual',
                'production_level' => ProductionLevelEnum::HIGH->value,
                'has_visual_effects' => true,
                'has_3d' => false,
                'has_animations' => false,
                'has_typography' => true,
                'has_sound_design' => true,
                'search_profile' => 'Кинематографический коммерческий ролик с теплой цветокоррекцией, медленным темпом, типографикой и саунд-дизайном',
                'search_metadata' => 'cinematic commercial product warm color grade typography sound design',
            ],
            [
                'title' => 'Fast-Paced Social Media Ad',
                'source_url' => 'https://www.instagram.com/p/example2',
                'preview_url' => 'https://example.com/preview2.jpg',
                'public_summary' => 'Динамичная реклама для социальных сетей',
                'details_public' => ['format' => 'vertical', 'style' => 'trendy'],
                'duration_sec' => 15,
                'category_id' => $categories->where('slug', 'social-advertising')->first()->id,
                'platform' => PlatformEnum::INSTAGRAM->value,
                'pacing' => PacingEnum::FAST->value,
                'hook_type' => 'action',
                'production_level' => ProductionLevelEnum::MID->value,
                'has_visual_effects' => false,
                'has_3d' => false,
                'has_animations' => true,
                'has_typography' => true,
                'has_sound_design' => false,
                'search_profile' => 'Быстрая вертикальная реклама для Instagram с анимацией и типографикой',
                'search_metadata' => 'fast paced vertical instagram animation typography trendy',
            ],
            [
                'title' => 'Documentary Style Short Film',
                'source_url' => 'https://www.youtube.com/watch?v=example3',
                'preview_url' => 'https://example.com/preview3.jpg',
                'public_summary' => 'Короткометражный фильм в документальном стиле',
                'details_public' => ['style' => 'documentary', 'camera' => 'handheld'],
                'duration_sec' => 180,
                'category_id' => $categories->where('slug', 'short-film')->first()->id,
                'platform' => PlatformEnum::YOUTUBE->value,
                'pacing' => PacingEnum::MIXED->value,
                'hook_type' => 'narrative',
                'production_level' => ProductionLevelEnum::LOW->value,
                'has_visual_effects' => false,
                'has_3d' => false,
                'has_animations' => false,
                'has_typography' => false,
                'has_sound_design' => true,
                'search_profile' => 'Документальный короткометражный фильм с ручной камерой, смешанным темпом и саунд-дизайном',
                'search_metadata' => 'documentary short film handheld camera narrative sound design',
            ],
        ];

        foreach ($examples as $example) {
            $videoReference = VideoReference::create($example);

            // Привязываем случайные теги
            $randomTags = $tags->random(rand(2, 5));
            $videoReference->tags()->attach($randomTags->pluck('id'));

            // Добавляем tutorials для некоторых видео
            if (rand(0, 1)) {
                $videoReference->tutorials()->create([
                    'tutorial_url' => 'https://www.youtube.com/watch?v=tutorial1',
                    'label' => 'Color Grading',
                    'start_sec' => 10,
                    'end_sec' => 25,
                ]);
            }
        }
    }
}
