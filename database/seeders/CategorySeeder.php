<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The shop's category tree (o_id = original WooCommerce category id).
 */
class CategorySeeder extends Seeder
{
    private const IMAGE_BASE = 'http://localhost/spaalita/wp-content/uploads/2021/06/';

    public function run(): void
    {
        foreach ($this->categories() as $root) {
            $parent = $this->upsert($root);

            foreach ($root['children'] ?? [] as $position => $child) {
                $this->upsert($child, $parent, $position + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsert(array $data, ?Category $parent = null, int $position = 0): Category
    {
        return Category::query()->updateOrCreate(
            ['o_id' => $data['oId']],
            [
                'parent_id' => $parent?->id,
                'position' => $position,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? '',
                'image_id' => $data['image']['id'] ?? null,
                'image_src' => $data['image']['src'] ?? null,
                'count' => $data['count'],
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        return [
            [
                'oId' => 17, 'name' => 'Comfort zone', 'slug' => 'comfort-zone', 'count' => 80,
                'image' => ['id' => 17, 'src' => self::IMAGE_BASE.'comfort_zone.jpg'],
                'children' => [
                    ['oId' => 25, 'name' => 'Active Pureness', 'slug' => 'active-pureness', 'count' => 5],
                    ['oId' => 21, 'name' => 'Body Strategist', 'slug' => 'body-strategist', 'count' => 11],
                    ['oId' => 26, 'name' => 'Essential', 'slug' => 'essential', 'count' => 7],
                    ['oId' => 22, 'name' => 'Hydramemory', 'slug' => 'hydramemory', 'count' => 12],
                    ['oId' => 27, 'name' => 'Remedy', 'slug' => 'remedy', 'count' => 6],
                    ['oId' => 28, 'name' => 'Renight', 'slug' => 'renight', 'count' => 6],
                    ['oId' => 29, 'name' => 'Skin Regimen', 'slug' => 'skin-regimen', 'count' => 6],
                    ['oId' => 30, 'name' => 'Sublime Skin', 'slug' => 'sublime-skin', 'count' => 13],
                    [
                        'oId' => 20, 'name' => 'Tranquillity', 'slug' => 'tranquillity', 'count' => 13,
                        'image' => ['id' => 16, 'src' => self::IMAGE_BASE.'retail_products.jpg'],
                    ],
                    ['oId' => 65, 'name' => 'Sacred Nature', 'slug' => 'sacrednature', 'description' => 'Sacred Nature Products', 'count' => 1],
                ],
            ],
            [
                'oId' => 18, 'name' => 'Jane Iredale', 'slug' => 'jane-iradale', 'count' => 76,
                'children' => [
                    ['oId' => 32, 'name' => 'Blushes, Highlighters & Bronzers', 'slug' => 'blushes-highlighters-bronzers', 'count' => 10],
                    ['oId' => 33, 'name' => 'Brows', 'slug' => 'brows', 'count' => 1],
                    ['oId' => 31, 'name' => 'Brushes Animal-Free Vegan', 'slug' => 'brushes-animal-free-vegan', 'count' => 33],
                    ['oId' => 34, 'name' => 'Concealers', 'slug' => 'concealers', 'count' => 0],
                    ['oId' => 47, 'name' => 'Cosmetic Bags', 'slug' => 'cosmetic-bags', 'count' => 0],
                    ['oId' => 35, 'name' => 'Eye Shadow', 'slug' => 'eye-shadow', 'count' => 0],
                    ['oId' => 36, 'name' => 'Eye Shadow Kits', 'slug' => 'eye-shadow-kits', 'count' => 1],
                    ['oId' => 37, 'name' => 'Eyeliner', 'slug' => 'eyeliner', 'count' => 0],
                    ['oId' => 38, 'name' => 'Foundation', 'slug' => 'foundation', 'count' => 3],
                    ['oId' => 39, 'name' => 'Hydration Sprays', 'slug' => 'hydration-sprays', 'count' => 11],
                    ['oId' => 40, 'name' => 'Lashes', 'slug' => 'lashes', 'count' => 3],
                    ['oId' => 41, 'name' => 'Lips', 'slug' => 'lips', 'count' => 7],
                    ['oId' => 42, 'name' => 'Makeup Kits', 'slug' => 'makeup-kits', 'count' => 0],
                    ['oId' => 43, 'name' => 'Oil Control', 'slug' => 'oil-control', 'count' => 1],
                    ['oId' => 44, 'name' => 'Pencil Sharpeners', 'slug' => 'pencil-sharpeners', 'count' => 2],
                    ['oId' => 45, 'name' => 'Primers', 'slug' => 'primers', 'count' => 2],
                    ['oId' => 46, 'name' => 'Skin & Body', 'slug' => 'skin-and-body', 'count' => 3],
                ],
            ],
            ['oId' => 19, 'name' => 'Footlogix', 'slug' => 'footlogix', 'count' => 22],
            ['oId' => 48, 'name' => 'Gift Card', 'slug' => 'gift-card', 'count' => 6],
            ['oId' => 15, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'count' => 0],
        ];
    }
}
