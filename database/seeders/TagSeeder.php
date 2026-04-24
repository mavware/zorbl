<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Pop Culture',
            'Sports',
            'Science',
            'History',
            'Movies',
            'Music',
            'Geography',
            'Food',
            'Literature',
            'Current Events',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}
