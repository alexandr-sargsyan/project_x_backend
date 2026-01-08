<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'cinematic',
            'minimalist',
            'colorful',
            'dark',
            'fast-paced',
            'slow-motion',
            'drone',
            'handheld',
            'stabilized',
            'vfx',
            '3d',
            'animation',
            'typography',
            'sound-design',
            'music',
            'dialogue',
            'silent',
            'narrative',
            'abstract',
            'experimental',
        ];

        foreach ($tags as $tagName) {
            Tag::create(['name' => $tagName]);
        }
    }
}
