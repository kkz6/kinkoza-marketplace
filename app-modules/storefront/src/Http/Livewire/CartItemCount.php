<?php

namespace Kinkoza\Storefront\Http\Livewire;

use Illuminate\Contracts\View\View;
use Kinkoza\Cart\Actions\GetCartItemCount;
use Kinkoza\Storefront\Support\CartIdentity;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CartItemCount extends Component
{
    #[Locked]
    public int $count = 0;

    public function mount(CartIdentity $identity): void
    {
        $this->count = GetCartItemCount::make()->handle(
            $identity->buyer(),
            $identity->guestToken(),
        );
    }

    #[On('cart-updated')]
    public function refreshCount(?int $count = null): void
    {
        if ($count !== null) {
            $this->count = max(0, $count);

            return;
        }

        $identity = resolve(CartIdentity::class);

        $this->count = GetCartItemCount::make()->handle(
            $identity->buyer(),
            $identity->guestToken(),
        );
    }

    public function render(): View
    {
        return view('storefront::livewire.cart-item-count');
    }
}
