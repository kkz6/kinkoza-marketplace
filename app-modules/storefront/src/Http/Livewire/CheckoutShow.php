<?php

namespace Kinkoza\Storefront\Http\Livewire;

use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
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
#[Title('Review your order')]
class CheckoutShow extends Component
{
    #[Locked]
    public string $cartId;

    #[Locked]
    public string $idempotencyKey;

    #[Locked]
    public int $cartVersion;

    public function mount(
        CartServiceInterface $carts,
        CartIdentity $identity,
    ): void {
        $cart = $carts->getOrCreateFor(
            $identity->buyer(),
            $identity->guestToken(),
        );

        $this->cartId = (string) $cart->getKey();
        $this->cartVersion = $cart->version;
        $this->idempotencyKey = (string) Str::ulid();
    }

    #[Computed]
    public function cart(): Cart
    {
        $buyerId = Auth::id();

        return Cart::query()
            ->whereKey($this->cartId)
            ->where('buyer_id', $buyerId)
            ->with('items.listing:id,slug,image_url,inventory_quantity')
            ->firstOrFail();
    }

    public function placeOrder(CheckoutServiceInterface $checkout): void
    {
        $buyer = Auth::user();

        abort_unless($buyer instanceof User, 403);

        try {
            $order = $checkout->checkout(
                $this->cart,
                $buyer,
                $this->idempotencyKey,
                $this->cartVersion,
            );
        } catch (DomainException|StaleCartVersion $exception) {
            $this->addError('checkout', DomainErrorMessage::for($exception));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('checkout', __('We could not place your order. Please try again.'));

            return;
        }

        session()->flash('status', __('Your order and invoice are ready.'));

        $this->redirectRoute(
            'storefront.orders.show',
            ['order' => $order->getKey()],
            navigate: true,
        );
    }

    public function render(): View
    {
        return view('storefront::livewire.checkout-show', [
            'cart' => $this->cart,
        ]);
    }
}
