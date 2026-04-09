<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewWithdrawalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $user, public $amount, public $token, public $network, public $receiver) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Nouvelle demande de retrait")
            ->greeting("Nouvelle demande de retrait reçue")
            ->line("Utilisateur: {$this->user->name} ({$this->user->email})")
            ->line("Montant: {$this->amount} {$this->token}")
            ->line("Réseau: {$this->network}")
            ->line("Adresse: {$this->receiver}")
            ->line("Merci de traiter cette demande rapidement.");
    }
}

