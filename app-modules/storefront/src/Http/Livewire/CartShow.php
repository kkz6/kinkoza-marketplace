<?php

namespace Kinkoza\Storefront\Http\Livewire;

use DomainException;
use Illuminate\Contracts\View\View;
use Kinkoza\Cart\Actions\GetOrCreateCart;
use Kinkoza\Cart\Actions\RemoveCartItem;
use Kinkoza\Cart\Actions\UpdateCartItemQuantity;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Storefront\Support\CartIdentity;
use Kinkoza\Storefront\Support\DomainErrorMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** @property-read Cart $cart */
#[Layout('storefront::layouts.store')]
#[Title('Your cart')]
class CartShow extends Component
{
    #[Locked]
    public string $cartId;

    public function mount(
        CartIdentity $identity,
    ): void {
        $cart = GetOrCreateCart::run(
            $identity->buyer(),
            $identity->guestToken(),
        );

        $this->cartId = (string) $cart->getKey();
    }

    #[Computed]
    public function cart(): Cart
    {
        $identity = resolve(CartIdentity::class);
        $buyer = $identity->buyer();
        $query = Cart::query()
            ->whereKey($this->cartId)
            ->where('status', CartStatus::Active->value);

        if ($buyer) {
            $query->where('buyer_id', $buyer->getKey());
        } else {
            $query
                ->whereNull('buyer_id')
                ->where('guest_token', $identity->guestToken());
        }

        return $query
            ->with('items.listing:id,slug,image_url,inventory_quantity')
            ->firstOrFail();
    }

    public function updateQuantity(
        string $itemId,
        int $quantity,
        int $version,
    ): void {
        try {
            UpdateCartItemQuantity::run($this->cart, $itemId, $quantity, $version);
        } catch (DomainException|StaleCartVersion $exception) {
            $this->addError('cart', DomainErrorMessage::for($exception));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('cart', __('We could not update your cart. Please try again.'));

            return;
        }

        unset($this->cart);
        $this->dispatch('cart-updated');
    }

    public function remove(
        string $itemId,
        int $version,
    ): void {
        try {
            RemoveCartItem::run($this->cart, $itemId, $version);
        } catch (DomainException|StaleCartVersion $exception) {
            $this->addError('cart', DomainErrorMessage::for($exception));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('cart', __('We could not update your cart. Please try again.'));

            return;
        }

        unset($this->cart);
        $this->dispatch('cart-updated');
    }

    public function render(): View
    {
        return view('storefront::livewire.cart-show', [
            'cart' => $this->cart,
        ]);
    }
}
