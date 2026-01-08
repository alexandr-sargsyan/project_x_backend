<?php

namespace Database\Seeders;

use App\Models\Category;
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

        // Test video references (20-30 items)
        $videoReferences = [
            ['title' => 'Test Video Reference 1', 'source_url' => 'https://www.youtube.com/watch?v=test1', 'search_profile' => 'Test video reference 1'],
            ['title' => 'Test Video Reference 2', 'source_url' => 'https://www.youtube.com/watch?v=test2', 'search_profile' => 'Test video reference 2'],
            ['title' => 'Test Video Reference 3', 'source_url' => 'https://www.youtube.com/watch?v=test3', 'search_profile' => 'Test video reference 3'],
            ['title' => 'Test Video Reference 4', 'source_url' => 'https://www.youtube.com/watch?v=test4', 'search_profile' => 'Test video reference 4'],
            ['title' => 'Test Video Reference 5', 'source_url' => 'https://www.youtube.com/watch?v=test5', 'search_profile' => 'Test video reference 5'],
            ['title' => 'Test Video Reference 6', 'source_url' => 'https://www.youtube.com/watch?v=test6', 'search_profile' => 'Test video reference 6'],
            ['title' => 'Test Video Reference 7', 'source_url' => 'https://www.youtube.com/watch?v=test7', 'search_profile' => 'Test video reference 7'],
            ['title' => 'Test Video Reference 8', 'source_url' => 'https://www.youtube.com/watch?v=test8', 'search_profile' => 'Test video reference 8'],
            ['title' => 'Test Video Reference 9', 'source_url' => 'https://www.youtube.com/watch?v=test9', 'search_profile' => 'Test video reference 9'],
            ['title' => 'Test Video Reference 10', 'source_url' => 'https://www.youtube.com/watch?v=test10', 'search_profile' => 'Test video reference 10'],
            ['title' => 'Test Video Reference 11', 'source_url' => 'https://www.youtube.com/watch?v=test11', 'search_profile' => 'Test video reference 11'],
            ['title' => 'Test Video Reference 12', 'source_url' => 'https://www.youtube.com/watch?v=test12', 'search_profile' => 'Test video reference 12'],
            ['title' => 'Test Video Reference 13', 'source_url' => 'https://www.youtube.com/watch?v=test13', 'search_profile' => 'Test video reference 13'],
            ['title' => 'Test Video Reference 14', 'source_url' => 'https://www.youtube.com/watch?v=test14', 'search_profile' => 'Test video reference 14'],
            ['title' => 'Test Video Reference 15', 'source_url' => 'https://www.youtube.com/watch?v=test15', 'search_profile' => 'Test video reference 15'],
            ['title' => 'Test Video Reference 16', 'source_url' => 'https://www.youtube.com/watch?v=test16', 'search_profile' => 'Test video reference 16'],
            ['title' => 'Test Video Reference 17', 'source_url' => 'https://www.youtube.com/watch?v=test17', 'search_profile' => 'Test video reference 17'],
            ['title' => 'Test Video Reference 18', 'source_url' => 'https://www.youtube.com/watch?v=test18', 'search_profile' => 'Test video reference 18'],
            ['title' => 'Test Video Reference 19', 'source_url' => 'https://www.youtube.com/watch?v=test19', 'search_profile' => 'Test video reference 19'],
            ['title' => 'Test Video Reference 20', 'source_url' => 'https://www.youtube.com/watch?v=test20', 'search_profile' => 'Test video reference 20'],
            ['title' => 'Test Video Reference 21', 'source_url' => 'https://www.youtube.com/watch?v=test21', 'search_profile' => 'Test video reference 21'],
            ['title' => 'Test Video Reference 22', 'source_url' => 'https://www.youtube.com/watch?v=test22', 'search_profile' => 'Test video reference 22'],
            ['title' => 'Test Video Reference 23', 'source_url' => 'https://www.youtube.com/watch?v=test23', 'search_profile' => 'Test video reference 23'],
            ['title' => 'Test Video Reference 24', 'source_url' => 'https://www.youtube.com/watch?v=test24', 'search_profile' => 'Test video reference 24'],
            ['title' => 'Test Video Reference 25', 'source_url' => 'https://www.youtube.com/watch?v=test25', 'search_profile' => 'Test video reference 25'],
            ['title' => 'Test Video Reference 26', 'source_url' => 'https://www.youtube.com/watch?v=test26', 'search_profile' => 'Test video reference 26'],
            ['title' => 'Test Video Reference 27', 'source_url' => 'https://www.youtube.com/watch?v=test27', 'search_profile' => 'Test video reference 27'],
            ['title' => 'Test Video Reference 28', 'source_url' => 'https://www.youtube.com/watch?v=test28', 'search_profile' => 'Test video reference 28'],
            ['title' => 'Test Video Reference 29', 'source_url' => 'https://www.youtube.com/watch?v=test29', 'search_profile' => 'Test video reference 29'],
            ['title' => 'Test Video Reference 30', 'source_url' => 'https://www.youtube.com/watch?v=test30', 'search_profile' => 'Test video reference 30'],
        ];

        foreach ($videoReferences as $index => $videoData) {
            // Распределяем по категориям циклически
            $category = $categories[$index % $categories->count()];
            
            VideoReference::create([
                'title' => $videoData['title'],
                'source_url' => $videoData['source_url'],
                'search_profile' => $videoData['search_profile'],
                'category_id' => $category->id,
            ]);
        }
    }
}
