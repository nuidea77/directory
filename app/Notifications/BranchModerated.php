<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Модерацын шийдвэр — салбар батлагдсан/татгалзсан үед эзэнд очно.
 */
class BranchModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Branch $branch, protected bool $approved)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->branch->business?->name.' — '.$this->branch->name;

        if ($this->approved) {
            return (new MailMessage)
                ->subject('Бүртгэл батлагдлаа — Ойрхон.mn')
                ->greeting('Сайн байна уу!')
                ->line("«{$name}» салбарын бүртгэл батлагдаж, хайлтад ил гарлаа.")
                ->action('Бүртгэлээ харах', url('/console'));
        }

        return (new MailMessage)
            ->subject('Бүртгэлд засвар шаардлагатай — Ойрхон.mn')
            ->greeting('Сайн байна уу!')
            ->line("«{$name}» салбарын бүртгэлийг редакц татгалзлаа.")
            ->lineIf(filled($this->branch->rejection_reason), 'Шалтгаан: '.$this->branch->rejection_reason)
            ->line('Мэдээллээ засаад дахин хяналтад илгээнэ үү.')
            ->action('Засварлах', url('/console/branches/'.$this->branch->id));
    }
}
