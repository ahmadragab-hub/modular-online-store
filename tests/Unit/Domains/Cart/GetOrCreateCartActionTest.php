<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Actions\GetOrCreateCartAction;
use App\Domains\Cart\Models\Cart;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetOrCreateCartActionTest extends TestCase
{
    use RefreshDatabase;

    private GetOrCreateCartAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        $this->action = $this->app->make(GetOrCreateCartAction::class);
    }

    public function test_creates_cart_when_session_has_no_token(): void
    {
        $result = $this->action->execute('', 'USD');

        $this->assertTrue($result->sessionTokenRotated);
        $this->assertSame(Cart::STATUS_ACTIVE, $result->cart->status);
        $this->assertSame($result->sessionToken, $result->cart->session_token);
    }

    public function test_reuses_active_cart_for_same_session_token(): void
    {
        $token = GuestCartSession::generateToken();

        $first = $this->action->execute($token, 'USD');
        $second = $this->action->execute($token, 'USD');

        $this->assertSame($first->cart->getKey(), $second->cart->getKey());
        $this->assertFalse($second->sessionTokenRotated);
    }

    public function test_expired_active_cart_is_not_reused_and_rotates_token(): void
    {
        $token = GuestCartSession::generateToken();
        $first = $this->action->execute($token, 'USD');
        $first->cart->update(['expires_at' => now()->subMinute()]);

        $second = $this->action->execute($token, 'USD');

        $this->assertNotSame($first->cart->getKey(), $second->cart->getKey());
        $this->assertTrue($second->sessionTokenRotated);
        $this->assertNotSame($token, $second->sessionToken);
        $this->assertSame(Cart::STATUS_ABANDONED, $first->cart->fresh()->status);
        $this->assertNull($first->cart->fresh()->session_token);
    }
}
