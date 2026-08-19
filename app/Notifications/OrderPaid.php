<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Төлбөр батлагдсан баримт — худалдан авагчид очно.
 */
class OrderPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Төлбөр батлагдлаа · {$this->order->number} — Хаана.mn")
            ->greeting('Сайн байна уу!')
            ->line("Захиалга {$this->order->number} — нийт ₮".number_format((float) $this->order->total).' төлөгдлөө.');

        foreach ($this->order->items as $item) {
            $mail->line('• '.$item->name.' — ₮'.number_format($item->amount - $item->discount));
        }

        return $mail
            ->line('Эрх шууд, онцлох байршил хэдхэн минутын дотор идэвхжинэ.')
            ->action('Нэхэмжлэхүүд', url('/console/invoices'));
    }
}
