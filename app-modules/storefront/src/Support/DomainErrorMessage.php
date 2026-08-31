<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Support;

use Kinkoza\Cart\Exceptions\CartNotActive;
use Kinkoza\Cart\Exceptions\CurrencyMismatch;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Exceptions\SelfPurchaseNotAllowed;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Sales\Exceptions\CartChangedDuringCheckout;
use Kinkoza\Sales\Exceptions\EmptyCart;
use Throwable;

final class DomainErrorMessage
{
    public static function for(Throwable $exception): string
    {
        return (string) match (true) {
            $exception instanceof InsufficientInventory => __('The requested quantity is no longer available.'),
            $exception instanceof ListingUnavailable => __('This asset is no longer available.'),
            $exception instanceof CurrencyMismatch => __('Assets in different currencies need separate carts.'),
            $exception instanceof StaleCartVersion => __('Your cart changed while you were reviewing it. Refresh and try again.'),
            $exception instanceof CartChangedDuringCheckout => __('Your cart changed while you were reviewing it. Refresh and try again.'),
            $exception instanceof CartNotActive => __('This cart is no longer active.'),
            $exception instanceof SelfPurchaseNotAllowed => __('You cannot purchase your own listing.'),
            $exception instanceof EmptyCart => __('Your cart is empty. Return to the marketplace before checking out.'),
            default => __('We could not complete that marketplace action. Please refresh and try again.'),
        };
    }
}
