<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Шинэ зурвас — хэрэглэгч бизнес рүү бичихэд эзэнд,
 * бизнес хариулахад хэрэглэгчид очно.
 */
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Business $business, protected bool $toOwner)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->toOwner) {
            return (new MailMessage)
                ->subject('Шинэ зурвас — Хаана.mn')
                ->greeting('Сайн байна уу!')
                ->line("«{$this->business->name}» бизнест хэрэглэгчээс шинэ зурвас ирлээ.")
                ->action('Хариулах', url('/console/messages'));
        }

        return (new MailMessage)
            ->subject('Танд хариу ирлээ — Хаана.mn')
            ->greeting('Сайн байна уу!')
            ->line("«{$this->business->name}» бизнес таны зурвист хариуллаа.")
            ->action('Уншиж үзэх', url('/account'));
    }
}
