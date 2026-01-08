<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Корневые категории
        $advertising = Category::create([
            'name' => 'Реклама',
            'slug' => 'advertising',
            'parent_id' => null,
            'order' => 1,
        ]);

        $documentary = Category::create([
            'name' => 'Документалистика',
            'slug' => 'documentary',
            'parent_id' => null,
            'order' => 2,
        ]);

        $musicVideo = Category::create([
            'name' => 'Музыкальные клипы',
            'slug' => 'music-video',
            'parent_id' => null,
            'order' => 3,
        ]);

        $shortFilm = Category::create([
            'name' => 'Короткометражки',
            'slug' => 'short-film',
            'parent_id' => null,
            'order' => 4,
        ]);

        // Подкатегории для рекламы
        Category::create([
            'name' => 'Коммерческая реклама',
            'slug' => 'commercial-advertising',
            'parent_id' => $advertising->id,
            'order' => 1,
        ]);

        Category::create([
            'name' => 'Социальная реклама',
            'slug' => 'social-advertising',
            'parent_id' => $advertising->id,
            'order' => 2,
        ]);

        // Подкатегории для документалистики
        Category::create([
            'name' => 'Научная документалистика',
            'slug' => 'science-documentary',
            'parent_id' => $documentary->id,
            'order' => 1,
        ]);

        Category::create([
            'name' => 'Историческая документалистика',
            'slug' => 'historical-documentary',
            'parent_id' => $documentary->id,
            'order' => 2,
        ]);
    }
}
