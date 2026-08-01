<?php

namespace App\Core\Notifications;

use App\Core\Models\Family;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Einladungsmail an eine Adresse, die noch kein Konto haben muss (A-06).
 */
class FamilyInvitation extends Notification
{
    use Queueable;

    public function __construct(
        protected Family $family,
        protected string $inviterName,
        protected string $link,
        protected Carbon $expiresAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Einladung zu '.$this->family->name)
            ->greeting('Hallo!')
            ->line($this->inviterName.' lädt dich ein, bei "'.$this->family->name.'" mitzumachen.')
            ->action('Einladung annehmen', $this->link)
            ->line('Der Link gilt bis zum '.$this->expiresAt->timezone($this->family->timezone)->format('d.m.Y H:i').' Uhr.')
            ->line('Wenn du damit nichts anfangen kannst, ignoriere diese Nachricht einfach.');
    }
}
