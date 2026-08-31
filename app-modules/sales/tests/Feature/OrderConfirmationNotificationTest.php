<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Actions\SendOrderConfirmation;
use Kinkoza\Sales\Events\OrderPlaced;
use Kinkoza\Sales\Models\Invoice;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Notifications\OrderConfirmation;
use Lorisleiva\Actions\Concerns\AsAction;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the sales provider explicitly registers an after-commit queued listener action', function () {
    Event::fake();

    Event::assertListening(OrderPlaced::class, SendOrderConfirmation::class);

    $action = resolve(SendOrderConfirmation::class);

    expect(is_subclass_of(OrderPlaced::class, ShouldDispatchAfterCommit::class))->toBeTrue()
        ->and(is_subclass_of(SendOrderConfirmation::class, ShouldQueueAfterCommit::class))->toBeTrue()
        ->and(class_uses_recursive(SendOrderConfirmation::class))->toContain(AsAction::class)
        ->and($action->tries)->toBe(3)
        ->and($action->backoff)->toBe([60, 300])
        ->and($action->deleteWhenMissingModels)->toBeTrue();
});

test('the queued listener sends order and invoice references to the buyer', function () {
    Notification::fake();

    $buyer = User::factory()->create();
    $cart = Cart::factory()->forBuyer($buyer)->converted()->create();
    $order = Order::factory()
        ->for($buyer, 'buyer')
        ->for($cart, 'cart')
        ->create([
            'number' => 'ORD-00000421',
            'idempotency_key' => (string) Str::ulid(),
        ]);
    $invoice = Invoice::factory()
        ->for($order, 'order')
        ->create(['number' => 'INV-00000422']);

    SendOrderConfirmation::run(new OrderPlaced($order));

    Notification::assertSentTo(
        $buyer,
        function (OrderConfirmation $notification, array $channels) use ($buyer, $invoice, $order): bool {
            $mail = $notification->toMail($buyer);

            return $channels === ['mail']
                && $notification->orderId === $order->getKey()
                && $notification->orderNumber === 'ORD-00000421'
                && $notification->invoiceId === $invoice->getKey()
                && $notification->invoiceNumber === 'INV-00000422'
                && $mail instanceof MailMessage
                && $mail->subject === 'Order ORD-00000421 confirmed'
                && in_array('Order reference: ORD-00000421', $mail->introLines, true)
                && in_array('Invoice reference: INV-00000422', $mail->introLines, true);
        },
    );
});

test('notification delivery uses the buyers preferred locale', function (): void {
    $buyer = User::factory()->create(['locale' => 'fr']);
    $deliveryLocale = null;
    $subject = null;

    Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$deliveryLocale, &$subject): void {
        $deliveryLocale = App::getLocale();
        $mail = $event->notification->toMail($event->notifiable);
        $subject = $mail->subject;
    });

    App::setLocale('en');
    $buyer->notify(new OrderConfirmation(
        orderId: (string) Str::ulid(),
        orderNumber: 'ORD-00000423',
        invoiceId: (string) Str::ulid(),
        invoiceNumber: 'INV-00000424',
    ));

    expect($deliveryLocale)->toBe('fr')
        ->and($subject)->toBe('Commande ORD-00000423 confirmée')
        ->and(App::getLocale())->toBe('en');
});
