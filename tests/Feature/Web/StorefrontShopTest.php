<?php

namespace Tests\Feature\Web;

use App\Domains\Catalog\Models\Product;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesStorefrontCatalogProducts;
use Tests\TestCase;

class StorefrontShopTest extends TestCase
{
    use CreatesStorefrontCatalogProducts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StoreLocaleSeeder::class);
        Storage::fake('public');
    }

    public function test_shop_page_loads_successfully(): void
    {
        $this->createStorefrontProduct('shop-item');

        $this->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee(__('storefront.shop.title', [], 'en'), false);
    }

    public function test_shop_lists_only_eligible_products(): void
    {
        $this->createStorefrontProduct('visible-item', enName: 'Visible Product');

        Product::query()->create([
            'name' => 'Draft',
            'slug' => 'hidden-draft',
            'status' => Product::STATUS_DRAFT,
        ]);

        $response = $this->get(route('storefront.shop.index'));

        $response->assertSee('Visible Product', false);
        $response->assertDontSee('hidden-draft', false);
    }

    public function test_shop_uses_requested_locale(): void
    {
        $this->createStorefrontProduct('locale-item', enName: 'English Name', arName: 'اسم عربي');

        $this->from(route('storefront.shop.index'))
            ->post(route('storefront.locale'), ['locale' => 'ar']);

        $this->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee('اسم عربي', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_shop_pagination_works(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createStorefrontProduct('paginate-'.$i, enName: 'Product '.$i);
        }

        $this->get(route('storefront.shop.index', ['per_page' => 2, 'page' => 2]))
            ->assertOk()
            ->assertSee('Product 3', false)
            ->assertDontSee('Product 1', false);
    }

    public function test_shop_empty_state_when_no_products(): void
    {
        $this->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee(__('storefront.shop.empty_title', [], 'en'), false);
    }

    public function test_product_detail_loads_for_eligible_product(): void
    {
        $this->createStorefrontProduct('detail-item', enName: 'Detail Product');

        $this->get(route('storefront.products.show', ['slug' => 'detail-item']))
            ->assertOk()
            ->assertSee('Detail Product', false)
            ->assertSee('Small / Teal', false)
            ->assertSee('$80.00', false);
    }

    public function test_unknown_and_ineligible_slugs_return_404(): void
    {
        $this->get(route('storefront.products.show', ['slug' => 'missing']))->assertNotFound();

        Product::query()->create([
            'name' => 'Draft',
            'slug' => 'draft-only',
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->get(route('storefront.products.show', ['slug' => 'draft-only']))->assertNotFound();
    }

    public function test_out_of_stock_variant_is_presented(): void
    {
        $this->createStorefrontProduct('sold-out', onHand: 1, reserved: 1);

        $this->get(route('storefront.products.show', ['slug' => 'sold-out']))
            ->assertOk()
            ->assertSee(__('storefront.products.out_of_stock', [], 'en'), false);
    }

    public function test_in_stock_variant_shows_available_count(): void
    {
        $this->createStorefrontProduct('in-stock', onHand: 5, reserved: 2);

        $this->get(route('storefront.products.show', ['slug' => 'in-stock']))
            ->assertOk()
            ->assertSee(__('storefront.products.in_stock', ['count' => 3], 'en'), false);
    }

    public function test_product_image_uses_storage_url(): void
    {
        Storage::disk('public')->put('products/url-test.jpg', 'binary');

        $this->createStorefrontProduct('url-test', enName: 'URL Product');

        $expected = Storage::disk('public')->url('products/url-test.jpg');

        $this->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee($expected, false);
    }

    public function test_navigation_includes_shop_link(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee(route('storefront.shop.index'), false)
            ->assertSee(__('storefront.nav.shop', [], 'en'), false);
    }

    public function test_arabic_shop_page_has_translation_keys(): void
    {
        $this->createStorefrontProduct('ar-keys', arName: 'منتج عربي');

        $this->from(route('storefront.shop.index'))
            ->post(route('storefront.locale'), ['locale' => 'ar']);

        $this->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee(__('storefront.shop.title', [], 'ar'), false);
    }

    public function test_locale_and_theme_preferences_still_work_on_shop(): void
    {
        $this->createStorefrontProduct('prefs');

        $this->from(route('storefront.shop.index'))
            ->post(route('storefront.theme'), ['theme' => 'dark'])
            ->assertRedirect(route('storefront.shop.index'));

        $this->withUnencryptedCookie('storefront_theme', 'dark')
            ->get(route('storefront.shop.index'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);
    }
}
