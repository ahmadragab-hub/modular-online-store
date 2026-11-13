<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class StorefrontPreferencesTest extends TestCase
{
    public function test_homepage_renders_with_default_locale_and_theme(): void
    {
        $response = $this->get(route('storefront.home'));

        $response->assertOk();
        $response->assertSee(__('storefront.brand', [], 'en'), false);
        $response->assertSee('data-theme="light"', false);
        $response->assertSee('dir="ltr"', false);
    }

    public function test_customer_can_switch_to_arabic(): void
    {
        $response = $this->from(route('storefront.home'))
            ->post(route('storefront.locale'), ['locale' => 'ar']);

        $response->assertRedirect(route('storefront.home'));
        $response->assertCookie('storefront_locale', 'ar');

        $this->withUnencryptedCookie('storefront_locale', 'ar')
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('متجر الأزياء', false);
    }

    public function test_customer_can_switch_to_dark_theme(): void
    {
        $response = $this->from(route('storefront.home'))
            ->post(route('storefront.theme'), ['theme' => 'dark']);

        $response->assertRedirect(route('storefront.home'));
        $response->assertCookie('storefront_theme', 'dark');

        $this->withUnencryptedCookie('storefront_theme', 'dark')
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $response = $this->from(route('storefront.home'))
            ->post(route('storefront.locale'), ['locale' => 'fr']);

        $response->assertSessionHasErrors('locale');
    }
}
