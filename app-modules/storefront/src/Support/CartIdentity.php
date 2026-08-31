<?php

namespace Kinkoza\Storefront\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class CartIdentity
{
    private const string SESSION_KEY = 'storefront.guest_cart_token';

    public function buyer(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function guestToken(): string
    {
        $token = session()->get(self::SESSION_KEY);

        if (is_string($token) && Str::isUlid($token)) {
            $token = strtolower($token);
            session()->put(self::SESSION_KEY, $token);

            return $token;
        }

        $token = strtolower((string) Str::ulid());

        session()->put(self::SESSION_KEY, $token);

        return $token;
    }
}
