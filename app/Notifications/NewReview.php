<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Шинэ сэтгэгдэл ирэхэд бизнесийн эзэнд очно.
 */
class NewReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Review $review)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $branch = $this->review->branch;
        $stars = str_repeat('★', (int) $this->review->rating);

        return (new MailMessage)
            ->subject('Шинэ сэтгэгдэл — Хаана.mn')
            ->greeting('Сайн байна уу!')
            ->line("«{$branch->business?->name} — {$branch->name}» салбарт {$stars} үнэлгээтэй шинэ сэтгэгдэл ирлээ.")
            ->lineIf(filled($this->review->comment), '«'.mb_substr((string) $this->review->comment, 0, 200).'»')
            ->action('Хариулах', url('/console/reviews'));
    }
}
