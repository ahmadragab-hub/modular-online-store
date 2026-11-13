<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Inventory\Exceptions\ExpiredInventoryReservationException;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InvalidInventoryReservationStateException;
use App\Domains\Inventory\Exceptions\InventoryReservationNotFoundException;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Models\InventoryReservation;
use Tests\TestCase;

class InventoryReservationExceptionsTest extends TestCase
{
    public function test_missing_inventory_level_exception_exposes_variant_ids(): void
    {
        $exception = new MissingInventoryLevelException([5, 9]);

        $this->assertSame([5, 9], $exception->missingProductVariantIds);
        $this->assertStringContainsString('5, 9', $exception->getMessage());
    }

    public function test_insufficient_stock_exception_context(): void
    {
        $exception = new InsufficientStockException(42, 3, 1);

        $this->assertSame(42, $exception->productVariantId);
        $this->assertSame(3, $exception->requestedQuantity);
        $this->assertSame(1, $exception->availableQuantity);
    }

    public function test_reservation_not_found_by_id_and_order(): void
    {
        $byId = new InventoryReservationNotFoundException(reservationId: 7);
        $this->assertStringContainsString('7', $byId->getMessage());

        $byOrder = new InventoryReservationNotFoundException(orderId: 99);
        $this->assertStringContainsString('99', $byOrder->getMessage());
    }

    public function test_invalid_reservation_state_exception_context(): void
    {
        $exception = new InvalidInventoryReservationStateException(
            reservationId: 1,
            currentStatus: InventoryReservation::STATUS_RELEASED,
            operation: 'commit',
            expectedStatus: InventoryReservation::STATUS_ACTIVE,
        );

        $this->assertStringContainsString('commit', $exception->getMessage());
        $this->assertStringContainsString(InventoryReservation::STATUS_RELEASED, $exception->getMessage());
    }

    public function test_expired_reservation_exception_context(): void
    {
        $expiresAt = now()->subMinute();
        $exception = new ExpiredInventoryReservationException(
            reservationId: 3,
            currentStatus: InventoryReservation::STATUS_EXPIRED,
            expiresAt: $expiresAt,
            operation: 'commit',
        );

        $this->assertStringContainsString('expired', strtolower($exception->getMessage()));
        $this->assertTrue($expiresAt->equalTo($exception->expiresAt));
    }
}
