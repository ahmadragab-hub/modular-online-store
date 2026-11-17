<?php

namespace App\Domains\Cart\DTOs;

final readonly class CartMergeResult
{
    /**
     * @param  list<AddCartItemMergeLineResult>  $lines
     */
    public function __construct(
        public bool $guestCartAbandoned,
        public array $lines,
    ) {}

    public function hasLineOutcomes(): bool
    {
        return $this->lines !== [];
    }

    public function appliedAnyQuantity(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->appliedAnyQuantity()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function flashPayload(): array
    {
        return array_map(static fn (AddCartItemMergeLineResult $line): array => [
            'status' => $line->status,
            'product_variant_id' => $line->productVariantId,
            'product_name' => $line->productName,
            'guest_quantity_requested' => $line->guestQuantityRequested,
            'quantity_added' => $line->quantityAdded,
            'final_quantity' => $line->finalQuantity,
        ], $this->lines);
    }
}
