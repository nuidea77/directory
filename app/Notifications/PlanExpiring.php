<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Эрхийн хугацаа дуусахын өмнөх сануулга (7 хоногийн өмнө).
 */
class PlanExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Organization $organization)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = config('billing.plans.'.$this->organization->plan.'.name', $this->organization->plan);
        $date = $this->organization->plan_expires_at?->format('Y-m-d');

        return (new MailMessage)
            ->subject('Эрхийн хугацаа дуусах гэж байна — Ойрхон.mn')
            ->greeting('Сайн байна уу!')
            ->line("«{$this->organization->name}» байгууллагын {$planName} эрх {$date}-нд дуусна.")
            ->line('Сунгахгүй бол баталгаажсан тэмдэг, нэмэлт боломжууд хаагдана (мэдээлэл устахгүй).')
            ->action('Эрх сунгах', url('/console/plan'));
    }
}
