<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class UserWithdrawalConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $amount, public $token, public $network, public $receiver) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Votre demande de retrait a été prise en compte")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre demande de retrait de {$this->amount} {$this->token} a bien été enregistrée.")
            ->line("Réseau: {$this->network}")
            ->line("Adresse: {$this->receiver}")
            ->line("Elle est en cours de traitement. Vous serez notifié une fois terminée.");
    }
}

