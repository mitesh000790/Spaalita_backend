<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Reference catalogue product. Categories are given as WooCommerce ids
 * (o_id) and linked to the matching seeded categories.
 */
class ProductSeeder extends Seeder
{
    private const IMAGE_BASE = 'http://localhost/spaalita/wp-content/uploads/2023/09/JANE-IREDALE-ColorLuxe-Hydrating-Cream-Lipstick';

    public function run(): void
    {
        $product = Product::query()->updateOrCreate(
            ['sku' => 'JI11812'],
            [
                'product_name' => 'JANE IREDALE ColorLuxe Hydrating Cream Lipstick',
                'price' => 42,
                'regular_price' => '',
                'sale_price' => '',
                'qty' => 1,
                'product_images' => array_map(fn (string $suffix) => self::IMAGE_BASE.$suffix, [
                    '-scaled.jpg',
                    '-scaled.jpg',
                    '1-scaled.jpg',
                    '3-scaled.jpg',
                    '2.jpg',
                    '-Rosebud-scaled.jpg',
                    '-Poppy1.jpg',
                    '-Poppy-scaled.jpg',
                    '-Peony-1.jpg',
                    '-Peony-scaled.jpg',
                    '-Passion-Fruit-1.jpg',
                    '-Passion-Fruit-scaled.jpg',
                    '-Mulberry-1.jpg',
                    '-Mulberry-scaled.jpg',
                ]),
                'description' => "<h4>Introducing NEW ColorLuxe Hydrating Cream Lipstick in 15 rich-yet-weightless, pigment-packed shades that glide on smooth and wrap lips in moisture, for bold payoff and lasting softness.</h4>\n<ul>\n<li>Highly pigmented lip color for bold payoff</li>\n<li>Smooth, satin finish</li>\n<li>Creamy, no-tug texture</li>\n<li>Rich-yet-weightless feel</li>\n</ul>\n",
                'short_description' => "<p>COLORLUXE HYDRATING CREAM LIPSTICK IN HIGHLY PIGMENTED SHADES FOR BOLD PAYOFF.</p>\n",
                'status' => 'active',
                'slug' => 'jane-iredale-colorluxe-hydrating-cream-lipstick',
                'tax_status' => 'taxable',
                'shipping_required' => true,
                'shipping_taxable' => true,
                'stock_status' => 'instock',
                'related_ids' => [],
            ],
        );

        $categoryIds = Category::query()->whereIn('o_id', [18, 41])->orderBy('o_id')->pluck('id')->all();
        $product->syncCategoriesInOrder($categoryIds);
    }
}
