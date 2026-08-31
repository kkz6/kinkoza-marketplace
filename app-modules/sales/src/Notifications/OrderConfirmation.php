<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $orderNumber,
        public readonly string $invoiceId,
        public readonly string $invoiceNumber,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Order :number confirmed', ['number' => $this->orderNumber]))
            ->greeting(__('Your order is confirmed'))
            ->line(__('Order reference: :number', ['number' => $this->orderNumber]))
            ->line(__('Invoice reference: :number', ['number' => $this->invoiceNumber]))
            ->line(__('We will contact you if any further information is required.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
        ];
    }
}
