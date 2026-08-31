<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Kinkoza\Sales\Events\OrderPlaced;
use Kinkoza\Sales\Notifications\OrderConfirmation;
use UnexpectedValueException;

class SendOrderConfirmation implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public bool $deleteWhenMissingModels = true;

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $buyer = $order->buyer()->firstOrFail();
        $invoice = $order->invoice()->firstOrFail();
        $orderNumber = $order->getAttribute('number');
        $invoiceNumber = $invoice->getAttribute('number');

        if (! is_string($orderNumber) || ! is_string($invoiceNumber)) {
            throw new UnexpectedValueException('Order and invoice references must be strings.');
        }

        $buyer->notify(new OrderConfirmation(
            orderId: (string) $order->getKey(),
            orderNumber: $orderNumber,
            invoiceId: (string) $invoice->getKey(),
            invoiceNumber: $invoiceNumber,
        ));
    }
}
