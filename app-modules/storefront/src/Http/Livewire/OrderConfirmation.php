<?php

namespace Kinkoza\Storefront\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Kinkoza\Sales\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** @property-read Order $order */
#[Layout('storefront::layouts.store')]
#[Title('Order confirmed')]
class OrderConfirmation extends Component
{
    #[Locked]
    public string $orderId;

    public function mount(string $order): void
    {
        $ownedOrder = Order::query()
            ->whereKey($order)
            ->where('buyer_id', Auth::id())
            ->firstOrFail();

        $this->orderId = $ownedOrder->id;
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()
            ->whereKey($this->orderId)
            ->where('buyer_id', Auth::id())
            ->with(['items', 'invoice.items'])
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('storefront::livewire.order-confirmation', [
            'order' => $this->order,
        ]);
    }
}
