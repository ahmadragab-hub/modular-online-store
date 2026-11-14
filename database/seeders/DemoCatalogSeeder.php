<?php

namespace Database\Seeders;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent demo catalog for local storefront exploration.
 *
 * Run separately: php artisan db:seed --class=DemoCatalogSeeder
 *
 * Does not modify exchange rates, orders, payments, or carts.
 */
class DemoCatalogSeeder extends Seeder
{
    private const IMAGE_PATH = 'demo/catalog/placeholder.svg';

    public function run(): void
    {
        $sizes = $this->seedSizes();
        $colors = $this->seedColors();
        $categories = $this->seedCategories();

        $products = [
            [
                'slug' => 'classic-oxford-shirt',
                'brand' => 'Atelier Line',
                'price' => '49.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'white', 'sku' => 'DEMO-OXF-M-WHT', 'stock' => 12],
                    ['size' => 'L', 'color' => 'navy', 'sku' => 'DEMO-OXF-L-NVY', 'stock' => 8],
                ],
                'category' => 'menswear',
                'en' => [
                    'name' => 'Classic Oxford Shirt',
                    'short' => 'Crisp cotton oxford for every day.',
                    'description' => 'A breathable oxford weave with a structured collar. Layer under knitwear or wear solo.',
                ],
                'ar' => [
                    'name' => 'قميص أوكسفورد كلاسيكي',
                    'short' => 'قطن أوكسفورد منعش للاستخدام اليومي.',
                    'description' => 'نسيج أوكسفورد مريح مع ياقة متماسكة. ارتدِه لوحده أو تحت قطعة محبوكة.',
                ],
            ],
            [
                'slug' => 'linen-relaxed-trousers',
                'brand' => 'Atelier Line',
                'price' => '72.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'sand', 'sku' => 'DEMO-LIN-M-SND', 'stock' => 10],
                    ['size' => 'L', 'color' => 'sand', 'sku' => 'DEMO-LIN-L-SND', 'stock' => 6],
                ],
                'category' => 'menswear',
                'en' => [
                    'name' => 'Linen Relaxed Trousers',
                    'short' => 'Lightweight linen with a relaxed leg.',
                    'description' => 'Tailored from stone-washed linen with an easy drape and elasticated back waist.',
                ],
                'ar' => [
                    'name' => 'بنطال كتان مريح',
                    'short' => 'كتان خفيف بقصة مريحة.',
                    'description' => 'مصنوع من كتان مغسول بنسيج ناعم وخصر خلفي مرن.',
                ],
            ],
            [
                'slug' => 'midi-satin-slip-dress',
                'brand' => 'Maison Rue',
                'price' => '98.00',
                'skus' => [
                    ['size' => 'S', 'color' => 'black', 'sku' => 'DEMO-SLP-S-BLK', 'stock' => 7],
                    ['size' => 'M', 'color' => 'black', 'sku' => 'DEMO-SLP-M-BLK', 'stock' => 9],
                ],
                'category' => 'womenswear',
                'en' => [
                    'name' => 'Midi Satin Slip Dress',
                    'short' => 'Bias-cut satin with a soft sheen.',
                    'description' => 'An evening-ready midi silhouette with adjustable straps and a fluid drape.',
                ],
                'ar' => [
                    'name' => 'فستان ساتان متوسط الطول',
                    'short' => 'ساتان بقصة انسيابية ولمعان ناعم.',
                    'description' => 'تصميم أنيق بأطراف قابلة للتعديل وانسيابية مثالية للمساء.',
                ],
            ],
            [
                'slug' => 'wool-blend-overcoat',
                'brand' => 'Maison Rue',
                'price' => '165.00',
                'skus' => [
                    ['size' => 'L', 'color' => 'camel', 'sku' => 'DEMO-COT-L-CML', 'stock' => 5],
                    ['size' => 'XL', 'color' => 'camel', 'sku' => 'DEMO-COT-XL-CML', 'stock' => 4],
                ],
                'category' => 'outerwear',
                'en' => [
                    'name' => 'Wool Blend Overcoat',
                    'short' => 'Structured coat in a warm wool blend.',
                    'description' => 'Single-breasted overcoat with notch lapels, fully lined, in a timeless camel hue.',
                ],
                'ar' => [
                    'name' => 'معطف صوف مخلوط',
                    'short' => 'معطف دافئ بقصة منظمة.',
                    'description' => 'معطف أحادي الصدر ببطانة كاملة ولون جملي كلاسيكي.',
                ],
            ],
            [
                'slug' => 'merino-rib-knit-sweater',
                'brand' => 'Atelier Line',
                'price' => '86.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'burgundy', 'sku' => 'DEMO-KNT-M-BRG', 'stock' => 11],
                    ['size' => 'L', 'color' => 'burgundy', 'sku' => 'DEMO-KNT-L-BRG', 'stock' => 8],
                ],
                'category' => 'womenswear',
                'en' => [
                    'name' => 'Merino Rib Knit Sweater',
                    'short' => 'Fine merino wool with rib texture.',
                    'description' => 'Crew-neck sweater spun from soft merino with a slim, layering-friendly fit.',
                ],
                'ar' => [
                    'name' => 'سويتر ميرينو بغرزة ضيقة',
                    'short' => 'صوف ميرينو ناعم بملمس مضلع.',
                    'description' => 'ياقة دائرية وقصة مناسبة للطبقات مصنوعة من ميرينو فاخر.',
                ],
            ],
            [
                'slug' => 'leather-crossbody-bag',
                'brand' => 'Maison Rue',
                'price' => '120.00',
                'skus' => [
                    ['size' => 'ONE', 'color' => 'black', 'sku' => 'DEMO-BAG-ONE-BLK', 'stock' => 15],
                ],
                'category' => 'accessories',
                'en' => [
                    'name' => 'Leather Crossbody Bag',
                    'short' => 'Compact crossbody in vegetable-tanned leather.',
                    'description' => 'Adjustable strap, interior card slot, and a soft structure for daily essentials.',
                ],
                'ar' => [
                    'name' => 'حقيبة كتف جلدية',
                    'short' => 'حقيبة يومية من جلد دبغ نباتي.',
                    'description' => 'حزام قابل للتعديل وجيب داخلي لحمل الأساسيات بأناقة.',
                ],
            ],
        ];

        foreach ($products as $definition) {
            $this->seedProduct($definition, $sizes, $colors, $categories);
        }
    }

    /**
     * @return array<string, Size>
     */
    private function seedSizes(): array
    {
        $rows = [
            ['code' => 'DEMO-S', 'label' => 'S', 'sort' => 1],
            ['code' => 'DEMO-M', 'label' => 'M', 'sort' => 2],
            ['code' => 'DEMO-L', 'label' => 'L', 'sort' => 3],
            ['code' => 'DEMO-XL', 'label' => 'XL', 'sort' => 4],
            ['code' => 'DEMO-ONE', 'label' => 'One Size', 'sort' => 5],
        ];

        $sizes = [];
        foreach ($rows as $row) {
            Size::query()->updateOrCreate(
                ['code' => $row['code']],
                ['label' => $row['label'], 'sort_order' => $row['sort']],
            );
            $sizes[$row['label']] = Size::query()->where('code', $row['code'])->firstOrFail();
            if ($row['label'] === 'One Size') {
                $sizes['ONE'] = $sizes[$row['label']];
            }
        }

        return $sizes;
    }

    /**
     * @return array<string, Color>
     */
    private function seedColors(): array
    {
        $rows = [
            ['slug' => 'demo-white', 'name' => 'White', 'hex' => '#FFFFFF', 'key' => 'white'],
            ['slug' => 'demo-navy', 'name' => 'Navy', 'hex' => '#1E3A5F', 'key' => 'navy'],
            ['slug' => 'demo-sand', 'name' => 'Sand', 'hex' => '#D6C4A8', 'key' => 'sand'],
            ['slug' => 'demo-black', 'name' => 'Black', 'hex' => '#111111', 'key' => 'black'],
            ['slug' => 'demo-camel', 'name' => 'Camel', 'hex' => '#C19A6B', 'key' => 'camel'],
            ['slug' => 'demo-burgundy', 'name' => 'Burgundy', 'hex' => '#722F37', 'key' => 'burgundy'],
        ];

        $colors = [];
        foreach ($rows as $row) {
            Color::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'hex_code' => $row['hex'], 'sort_order' => 0],
            );
            $colors[$row['key']] = Color::query()->where('slug', $row['slug'])->firstOrFail();
        }

        return $colors;
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $rows = [
            ['slug' => 'demo-menswear', 'name' => 'Menswear', 'key' => 'menswear'],
            ['slug' => 'demo-womenswear', 'name' => 'Womenswear', 'key' => 'womenswear'],
            ['slug' => 'demo-outerwear', 'name' => 'Outerwear', 'key' => 'outerwear'],
            ['slug' => 'demo-accessories', 'name' => 'Accessories', 'key' => 'accessories'],
        ];

        $categories = [];
        foreach ($rows as $row) {
            Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'sort_order' => 0,
                    'is_active' => true,
                ],
            );
            $categories[$row['key']] = Category::query()->where('slug', $row['slug'])->firstOrFail();
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, Size>  $sizes
     * @param  array<string, Color>  $colors
     * @param  array<string, Category>  $categories
     */
    private function seedProduct(array $definition, array $sizes, array $colors, array $categories): void
    {
        $product = Product::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['en']['name'],
                'short_description' => $definition['en']['short'],
                'description' => $definition['en']['description'],
                'status' => Product::STATUS_PUBLISHED,
                'published_at' => now(),
                'brand' => $definition['brand'],
            ],
        );

        foreach ([Product::LOCALE_EN => $definition['en'], Product::LOCALE_AR => $definition['ar']] as $locale => $content) {
            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'locale' => $locale],
                [
                    'name' => $content['name'],
                    'short_description' => $content['short'],
                    'description' => $content['description'],
                ],
            );
        }

        $category = $categories[$definition['category']];
        DB::table('category_product')->updateOrInsert(
            [
                'category_id' => $category->getKey(),
                'product_id' => $product->getKey(),
            ],
            ['is_primary' => true],
        );

        ProductImage::query()->updateOrCreate(
            [
                'product_id' => $product->getKey(),
                'path' => self::IMAGE_PATH,
            ],
            [
                'product_variant_id' => null,
                'disk' => 'public',
                'alt_text' => $definition['en']['name'],
                'sort_order' => 0,
            ],
        );

        foreach ($definition['skus'] as $skuRow) {
            $sizeKey = $skuRow['size'];
            $size = $sizes[$sizeKey] ?? $sizes['M'];
            $color = $colors[$skuRow['color']];

            $variant = ProductVariant::query()->updateOrCreate(
                ['sku' => $skuRow['sku']],
                [
                    'product_id' => $product->getKey(),
                    'size_id' => $size->getKey(),
                    'color_id' => $color->getKey(),
                    'price' => $definition['price'],
                    'status' => ProductVariant::STATUS_ACTIVE,
                ],
            );

            InventoryLevel::query()->firstOrCreate(
                ['product_variant_id' => $variant->getKey()],
                [
                    'quantity_on_hand' => $skuRow['stock'],
                    'quantity_reserved' => 0,
                ],
            );
        }
    }
}
