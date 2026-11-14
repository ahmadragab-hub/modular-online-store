<?php

namespace App\Domains\Cart\DTOs;

use App\Domains\Cart\Models\Cart;

final readonly class GuestCartResult
{
    public function __construct(
        public Cart $cart,
        public string $sessionToken,
        public bool $sessionTokenRotated,
    ) {}
}
