<?php

namespace App\Shared\Support;

final class GuestCartSession
{
    public const SESSION_KEY = 'cart_session_token';

    public static function generateToken(): string
    {
        return hash('sha256', random_bytes(32));
    }
}
