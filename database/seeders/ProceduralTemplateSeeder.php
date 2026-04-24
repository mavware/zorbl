<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Services\GridTemplateProvider;
use Illuminate\Database\Seeder;

class ProceduralTemplateSeeder extends Seeder
{
    /**
     * Populate the templates table with the procedurally generated layouts that
     * previously only existed in-memory via GridTemplateProvider::generateTemplates().
     * Safe to run more than once — templates with the same name + dimensions are skipped.
     */
    public function run(): void
    {
        $provider = app(GridTemplateProvider::class);

        foreach (range(5, 21) as $size) {
            $templates = $provider->generateTemplates($size, $size);

            foreach ($templates as $index => $template) {
                $exists = Template::query()
                    ->where('name', $template['name'])
                    ->where('width', $size)
                    ->where('height', $size)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Template::create([
                    'name' => $template['name'],
                    'width' => $size,
                    'height' => $size,
                    'grid' => $template['grid'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }
        }
    }
}
