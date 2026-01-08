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
        // Root categories
        $advertising = Category::create([
            'name' => 'Advertising',
            'slug' => 'advertising',
            'parent_id' => null,
            'order' => 1,
        ]);

        $documentary = Category::create([
            'name' => 'Documentary',
            'slug' => 'documentary',
            'parent_id' => null,
            'order' => 2,
        ]);

        $musicVideo = Category::create([
            'name' => 'Music Video',
            'slug' => 'music-video',
            'parent_id' => null,
            'order' => 3,
        ]);

        $shortFilm = Category::create([
            'name' => 'Short Film',
            'slug' => 'short-film',
            'parent_id' => null,
            'order' => 4,
        ]);

        // Subcategories for advertising
        Category::create([
            'name' => 'Commercial Advertising',
            'slug' => 'commercial-advertising',
            'parent_id' => $advertising->id,
            'order' => 1,
        ]);

        Category::create([
            'name' => 'Social Advertising',
            'slug' => 'social-advertising',
            'parent_id' => $advertising->id,
            'order' => 2,
        ]);

        // Subcategories for documentary
        Category::create([
            'name' => 'Science Documentary',
            'slug' => 'science-documentary',
            'parent_id' => $documentary->id,
            'order' => 1,
        ]);

        Category::create([
            'name' => 'Historical Documentary',
            'slug' => 'historical-documentary',
            'parent_id' => $documentary->id,
            'order' => 2,
        ]);
    }
}
