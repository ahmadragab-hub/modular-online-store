<?php

namespace App\Shared\Support;

final class CartMergeFlash
{
    public const SESSION_KEY = 'cart_merge';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MULTIPLE_CARTS = 'multiple_carts';

    public const STATUS_NO_QUANTITY_APPLIED = 'no_quantity_applied';
}
