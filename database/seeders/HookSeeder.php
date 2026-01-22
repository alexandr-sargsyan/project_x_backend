<?php

namespace Database\Seeders;

use App\Models\Hook;
use Illuminate\Database\Seeder;

class HookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hooks = [
            'Visual',
            'Animation',
            'Audio',
            'Text',
            'Speech',
            'Music',
            'Emotional',
        ];

        foreach ($hooks as $hookName) {
            Hook::firstOrCreate(['name' => $hookName]);
        }
    }
}
