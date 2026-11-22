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
                'price' => '980.00',
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
                'price' => '1440.00',
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
                'price' => '1960.00',
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
                'price' => '3300.00',
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
                'price' => '1720.00',
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
                'price' => '1200.00',
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
            [
                'slug' => 'slim-fit-chino',
                'brand' => 'Atelier Line',
                'price' => '890.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'navy', 'sku' => 'DEMO-CHI-M-NVY', 'stock' => 14],
                    ['size' => 'L', 'color' => 'sand', 'sku' => 'DEMO-CHI-L-SND', 'stock' => 9],
                ],
                'category' => 'menswear-tops',
                'en' => [
                    'name' => 'Slim Fit Chino',
                    'short' => 'Stretch cotton chino with a tapered leg.',
                    'description' => 'Mid-rise chino in garment-dyed cotton with a clean office-to-weekend profile.',
                ],
                'ar' => [
                    'name' => 'بنطال تشينو بقصة ضيقة',
                    'short' => 'قطن مريح بقصة مدببة.',
                    'description' => 'خصر متوسط ونسيج قطني مناسب للعمل والعطلة.',
                ],
            ],
            [
                'slug' => 'silk-button-blouse',
                'brand' => 'Maison Rue',
                'price' => '1450.00',
                'skus' => [
                    ['size' => 'S', 'color' => 'white', 'sku' => 'DEMO-BLS-S-WHT', 'stock' => 8],
                    ['size' => 'M', 'color' => 'burgundy', 'sku' => 'DEMO-BLS-M-BRG', 'stock' => 7],
                ],
                'category' => 'womenswear-dresses',
                'en' => [
                    'name' => 'Silk Button Blouse',
                    'short' => 'Fluid silk with mother-of-pearl buttons.',
                    'description' => 'Lightweight blouse with a relaxed collar and French cuffs.',
                ],
                'ar' => [
                    'name' => 'بلوزة حرير بأزرار',
                    'short' => 'حرير انسيابي بأزرار أنيقة.',
                    'description' => 'الياقة مريحة والأكمام الفرنسية لمظهر راقٍ.',
                ],
            ],
            [
                'slug' => 'denim-trucker-jacket',
                'brand' => 'Atelier Line',
                'price' => '1680.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'navy', 'sku' => 'DEMO-DEN-M-NVY', 'stock' => 6],
                    ['size' => 'L', 'color' => 'navy', 'sku' => 'DEMO-DEN-L-NVY', 'stock' => 5],
                ],
                'category' => 'outerwear',
                'en' => [
                    'name' => 'Denim Trucker Jacket',
                    'short' => 'Vintage-wash denim with brushed hardware.',
                    'description' => 'Classic trucker silhouette lined for transitional weather.',
                ],
                'ar' => [
                    'name' => 'جاكيت دنيم كلاسيكي',
                    'short' => 'دنيم بغسلة عتيقة.',
                    'description' => 'قصة تراكر تقليدية مع بطانة خفيفة.',
                ],
            ],
            [
                'slug' => 'cashmere-wrap-scarf',
                'brand' => 'Maison Rue',
                'price' => '750.00',
                'skus' => [
                    ['size' => 'ONE', 'color' => 'camel', 'sku' => 'DEMO-SCF-ONE-CML', 'stock' => 20],
                ],
                'category' => 'accessories',
                'en' => [
                    'name' => 'Cashmere Wrap Scarf',
                    'short' => 'Ultra-soft cashmere in generous length.',
                    'description' => 'Fringe-free hem for a clean drape over coats and knits.',
                ],
                'ar' => [
                    'name' => 'وشاح كشمير',
                    'short' => 'كشمير ناعم بطول سخي.',
                    'description' => 'حواف نظيفة لتنسيق أنيق فوق المعاطف.',
                ],
            ],
            [
                'slug' => 'pleated-midi-skirt',
                'brand' => 'Maison Rue',
                'price' => '1120.00',
                'skus' => [
                    ['size' => 'S', 'color' => 'black', 'sku' => 'DEMO-SKT-S-BLK', 'stock' => 9],
                    ['size' => 'M', 'color' => 'black', 'sku' => 'DEMO-SKT-M-BLK', 'stock' => 11],
                ],
                'category' => 'womenswear',
                'en' => [
                    'name' => 'Pleated Midi Skirt',
                    'short' => 'Permanent pleats with a satin sheen.',
                    'description' => 'Elastic waistband and midi length for day-to-night styling.',
                ],
                'ar' => [
                    'name' => 'تنورة متوسطة بطيات',
                    'short' => 'بطيات دائمة بلمعان ساتان.',
                    'description' => 'خصر مرن وطول متوسط للتنسيق اليومي والمسائي.',
                ],
            ],
            [
                'slug' => 'court-leather-sneaker',
                'brand' => 'Atelier Line',
                'price' => '2200.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'white', 'sku' => 'DEMO-SNK-M-WHT', 'stock' => 12],
                    ['size' => 'L', 'color' => 'white', 'sku' => 'DEMO-SNK-L-WHT', 'stock' => 10],
                ],
                'category' => 'accessories',
                'en' => [
                    'name' => 'Court Leather Sneaker',
                    'short' => 'Low-profile cupsole in full-grain leather.',
                    'description' => 'Minimal court sneaker with contrast heel tab and cushioned insole.',
                ],
                'ar' => [
                    'name' => 'حذاء رياضي جلدي',
                    'short' => 'نعل منخفض من الجلد الكامل.',
                    'description' => 'تصميم بسيط مع لسان خلفي مميز ووسادة داخلية مريحة.',
                ],
            ],
            [
                'slug' => 'structured-wool-blazer',
                'brand' => 'Atelier Line',
                'price' => '2890.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'navy', 'sku' => 'DEMO-BLZ-M-NVY', 'stock' => 5],
                    ['size' => 'L', 'color' => 'navy', 'sku' => 'DEMO-BLZ-L-NVY', 'stock' => 4],
                ],
                'category' => 'menswear',
                'en' => [
                    'name' => 'Structured Wool Blazer',
                    'short' => 'Half-canvas construction in tropical wool.',
                    'description' => 'Notch lapel blazer with horn buttons and double vent.',
                ],
                'ar' => [
                    'name' => 'بليزر صوف منظم',
                    'short' => 'تفصيل نصف كانفس من صوف خفيف.',
                    'description' => 'ياقة بحرف V وأزرار قرن وفتحة خلفية مزدوجة.',
                ],
            ],
            [
                'slug' => 'organic-cotton-tee-duo',
                'brand' => 'Atelier Line',
                'price' => '420.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'white', 'sku' => 'DEMO-TEE-M-WHT', 'stock' => 25],
                    ['size' => 'L', 'color' => 'sand', 'sku' => 'DEMO-TEE-L-SND', 'stock' => 22],
                ],
                'category' => 'menswear-tops',
                'en' => [
                    'name' => 'Organic Cotton Tee Duo',
                    'short' => 'Two-pack crew necks in breathable cotton.',
                    'description' => 'Ribbed collar and pre-washed hand feel for everyday layering.',
                ],
                'ar' => [
                    'name' => 'تيشيرت قطن عضوي - ثنائي',
                    'short' => 'حزمة من قطن ناعم.',
                    'description' => 'ياقة محبوكة وملمس مريح للطبقات اليومية.',
                ],
            ],
            [
                'slug' => 'satin-wrap-dress',
                'brand' => 'Maison Rue',
                'price' => '1980.00',
                'skus' => [
                    ['size' => 'S', 'color' => 'burgundy', 'sku' => 'DEMO-WRP-S-BRG', 'stock' => 6],
                    ['size' => 'M', 'color' => 'burgundy', 'sku' => 'DEMO-WRP-M-BRG', 'stock' => 7],
                ],
                'category' => 'womenswear-dresses',
                'en' => [
                    'name' => 'Satin Wrap Dress',
                    'short' => 'Wrap closure with tie waist.',
                    'description' => 'Midi satin dress with flutter sleeve and deep V neckline.',
                ],
                'ar' => [
                    'name' => 'فستان ساتان ملفوف',
                    'short' => 'إغلاق ملفوف مع حزام.',
                    'description' => 'أكمام منسدلة وخط رقبة عميق بطول متوسط.',
                ],
            ],
            [
                'slug' => 'merino-beanie',
                'brand' => 'Atelier Line',
                'price' => '380.00',
                'skus' => [
                    ['size' => 'ONE', 'color' => 'camel', 'sku' => 'DEMO-BEN-ONE-CML', 'stock' => 30],
                ],
                'category' => 'accessories',
                'en' => [
                    'name' => 'Merino Beanie',
                    'short' => 'Fine-gauge knit beanie.',
                    'description' => 'Double-layer cuff in soft merino for crisp weather.',
                ],
                'ar' => [
                    'name' => 'قبعة ميرينو',
                    'short' => 'حياكة رفيعة ناعمة.',
                    'description' => 'حاشية مزدوجة من الميرينو للطقس البارد.',
                ],
            ],
            [
                'slug' => 'tailored-linen-shorts',
                'brand' => 'Atelier Line',
                'price' => '680.00',
                'skus' => [
                    ['size' => 'M', 'color' => 'sand', 'sku' => 'DEMO-SHT-M-SND', 'stock' => 13],
                    ['size' => 'L', 'color' => 'sand', 'sku' => 'DEMO-SHT-L-SND', 'stock' => 11],
                ],
                'category' => 'menswear',
                'en' => [
                    'name' => 'Tailored Linen Shorts',
                    'short' => 'Pressed crease with side adjusters.',
                    'description' => 'Breathable linen shorts with lined pockets and mid-thigh length.',
                ],
                'ar' => [
                    'name' => 'شورت كتان أنيق',
                    'short' => 'طية أمامية وجيوب مبطنة.',
                    'description' => 'كتان منعش بطول فخذ متوسط للصيف.',
                ],
            ],
            [
                'slug' => 'canvas-market-tote',
                'brand' => 'Maison Rue',
                'price' => '520.00',
                'skus' => [
                    ['size' => 'ONE', 'color' => 'sand', 'sku' => 'DEMO-TOT-ONE-SND', 'stock' => 18],
                ],
                'category' => 'accessories',
                'en' => [
                    'name' => 'Canvas Market Tote',
                    'short' => 'Heavy canvas with leather handles.',
                    'description' => 'Roomy tote with interior zip pocket and reinforced base.',
                ],
                'ar' => [
                    'name' => 'حقيبة كانفاس',
                    'short' => 'كانفاس سميك بمقابض جلدية.',
                    'description' => 'جيب داخلي بسحاب وقاعدة معززة.',
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
            ['slug' => 'demo-menswear', 'name' => 'Menswear', 'key' => 'menswear', 'parent' => null],
            ['slug' => 'demo-womenswear', 'name' => 'Womenswear', 'key' => 'womenswear', 'parent' => null],
            ['slug' => 'demo-outerwear', 'name' => 'Outerwear', 'key' => 'outerwear', 'parent' => null],
            ['slug' => 'demo-accessories', 'name' => 'Accessories', 'key' => 'accessories', 'parent' => null],
            ['slug' => 'demo-menswear-tops', 'name' => 'Tops & Shirts', 'key' => 'menswear-tops', 'parent' => 'demo-menswear'],
            ['slug' => 'demo-womenswear-dresses', 'name' => 'Dresses & Blouses', 'key' => 'womenswear-dresses', 'parent' => 'demo-womenswear'],
        ];

        $categories = [];
        foreach ($rows as $row) {
            $parentId = null;
            if ($row['parent'] !== null) {
                $parent = Category::query()->where('slug', $row['parent'])->first();
                $parentId = $parent?->getKey();
            }

            Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'parent_id' => $parentId,
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
