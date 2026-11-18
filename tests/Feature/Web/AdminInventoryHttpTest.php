<?php

namespace Tests\Feature\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAdminTestFixtures;
use Tests\Support\CreatesOrderPaymentFixtures;
use Tests\TestCase;

class AdminInventoryHttpTest extends TestCase
{
    use CreatesAdminTestFixtures;
    use CreatesOrderPaymentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAdminBaselines();
    }

    public function test_admin_can_adjust_stock_via_http(): void
    {
        $admin = $this->createActiveAdmin();
        $variant = $this->createCatalogVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.adjust', $variant), [
                'quantity_delta' => 2,
                'notes' => 'restock',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(12, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_on_hand'));
        $this->assertSame(1, InventoryMovement::query()->where('product_variant_id', $variant->getKey())->count());
    }

    public function test_invalid_adjustment_rejected_and_inventory_unchanged(): void
    {
        $admin = $this->createActiveAdmin();
        $variant = $this->createCatalogVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 4,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.adjust', $variant), [
                'quantity_delta' => -3,
                'notes' => 'bad',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity_delta');

        $this->assertSame(5, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_on_hand'));
        $this->assertSame(0, InventoryMovement::query()->where('product_variant_id', $variant->getKey())->count());
    }

    public function test_customer_cannot_adjust_inventory(): void
    {
        $customer = User::factory()->create();
        $variant = $this->createCatalogVariant();

        $this->actingAs($customer)
            ->post(route('admin.inventory.adjust', $variant), ['quantity_delta' => 1])
            ->assertForbidden();
    }
}
